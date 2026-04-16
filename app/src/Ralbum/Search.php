<?php

namespace Ralbum;

use Ralbum\Model\Image;

class Search
{
    protected $index = [];
    protected $db = null;

    public static $forceUpdate = false;

    public $filters = [
        'camera' => 'model',
        'lens' => 'lens',
        'year' => 'date_taken',
        'month' => 'date_taken',
        'day' => 'date_taken',
    ];

    public static function isSupported()
    {
        return class_exists('SQLite3');
    }

    function __construct()
    {
        $this->db = new \SQLite3(BASE_DIR . '/data/database.db');
        $this->createTable();
    }

    public function createTable()
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS files (file_path STRING, file_name STRING, keywords STRING, file_type STRING, file_size INT, date_taken DATETIME, make STRING, model STRING, aperture DOUBLE, shutterspeed DOUBLE, iso INT, focal_length DOUBLE, lens STRING, lat DOUBLE, long DOUBLE)');
        $this->db->exec('CREATE UNIQUE INDEX IF NOT EXISTS file_path_unique ON files(file_path)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS file_type_name on files(file_type)');

        if (!$this->hasColumn('indexed_at')) {
            $this->db->exec('ALTER TABLE files ADD COLUMN indexed_at DATETIME');
        }

        if (!$this->hasColumn('hex')) {
            $this->db->exec('ALTER TABLE files ADD COLUMN hex STRING');
            $this->db->exec('ALTER TABLE files ADD COLUMN hue INT');
            $this->db->exec('ALTER TABLE files ADD COLUMN is_warm INT');
            $this->db->exec('ALTER TABLE files ADD COLUMN sat DOUBLE');
        }

    }

    public function hasColumn($column)
    {
        $result = $this->db->query('PRAGMA table_info(files)');

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === $column) {
                return true;
            }
        }
        return false;
    }

    public function initialize()
    {
        $this->createTable();
        $this->db->busyTimeout(5000);
    }

    public function begintTransaction()
    {
        $this->db->exec('BEGIN TRANSACTION');
    }

    public function commitTransaction()
    {
        $this->db->exec('COMMIT');
    }

    public function setEntry($key, $filename, $type, $metadata = [])
    {
        $dataKeys = [
            'file_path',
            'file_name',
            'keywords',
            'file_type',
            'file_size',
            // metadata
            'shutterspeed',
            'iso',
            'date_taken',
            'make',
            'model',
            'aperture',
            'focal_length',
            'lens',
            'lat',
            'long',
            'indexed_at',
            'hex',
            'hue',
            'is_warm',
            'sat'
        ];

        $dataPlaceHolders = [];
        foreach ($dataKeys as $i => $val) {
            $dataPlaceHolders[$i] = ':' . $val;
        }

        $statement = $this->db->prepare('INSERT OR REPLACE INTO files (' . implode(', ', $dataKeys) . ') VALUES (' .  implode(', ', $dataPlaceHolders) . ' )');

        $statement->bindValue(':file_path', $key);
        $statement->bindValue(':file_name', $filename);
        $statement->bindValue(':file_type', $type);
        $statement->bindValue(':indexed_at', date('Y-m-d H:i:s'));

        $baseDir = Setting::get('image_base_dir');

        $statement->bindValue(':file_size', filesize($baseDir. $key));

        foreach ($dataKeys as $val) {
            if (!in_array($val, ['file_path', 'file_name', 'file_type'])) {
                if (isset($metadata[$val])) {
                    if (is_array($metadata[$val])) {
                        $metadata[$val] = implode(',', $metadata[$val]);
                    }

                    $statement->bindValue(':'. $val, $this->replaceDiacritics($metadata[$val]));
                }
            }
        }

        $statement->execute();
    }

    public function removeFromIndex($key)
    {
        $statement = $this->db->prepare('DELETE FROM files WHERE file_path = :file_path');
        $statement->bindValue(':file_path', $key);
        return $statement->execute();
    }

    public function hasFilter()
    {
        $filters = array_keys($this->filters);
        $filters[] = 'limit_to_keyword_search';
        $filters[] = 'season';
        $filters[] = 'daytime';
        $filters[] = 'weekday';
        $filters[] = 'vibe';
        $filters[] = 'style';
        $filters[] = 'color';

        foreach ($filters as $filter) {
            if (isset($_REQUEST[$filter]) && strlen($_REQUEST[$filter]) > 0) {
                return true;
            }
        }
        return false;
    }

    public function search($q)
    {
        $results = [];

        $words = explode(' ', trim($q));
        $words = array_filter($words, 'strlen');

        $query = 'SELECT * FROM files WHERE 1=1 ';

        if (count($words) > 0) {

            $keywordSearches = [];
            $filenameSearches = [];
            foreach ($words as $i => $word) {
                if (substr($word, 0, 1) == '-') {
                    $keywordSearches[] = ' keywords NOT LIKE :word' . $i . ' ';
                    $filenameSearches[] = ' file_name NOT LIKE :word' . $i . ' ';
                } else {
                    $keywordSearches[] = ' keywords LIKE :word' . $i . ' ';
                    $filenameSearches[] = ' file_name LIKE :word' . $i . ' ';
                }
            }

            if (isset($_REQUEST['limit_to_keyword_search'])) {
                $query .= 'AND ' . implode(' AND ', $keywordSearches);
            } else {
                $query .= ' AND ( (' . implode(' AND ', $filenameSearches) . ') OR ( '. implode(' AND ', $keywordSearches) . ')) ';
            }

        }

        foreach ($this->filters as $requestParam => $filter) {
            if ($filter != 'date_taken') {
                if (isset($_REQUEST[$requestParam]) && strlen($_REQUEST[$requestParam]) > 0) {
                    $query .= ' AND ' . $filter . ' = :' . $filter . '' ;
                }
            }
        }

        if (isset($_REQUEST['year']) && strlen($_REQUEST['year']) > 0) {
            $query .= ' AND strftime("%Y", date_taken) = "' . (int)$_REQUEST['year'] . '" ';
        }
        if (isset($_REQUEST['month']) && strlen($_REQUEST['month']) > 0) {
            $query .= ' AND strftime("%m", date_taken) = "' . str_pad((int)$_REQUEST['month'], 2, '0', STR_PAD_LEFT) . '" ';
        }
        if (isset($_REQUEST['day']) && strlen($_REQUEST['day']) > 0) {
            $query .= ' AND strftime("%d", date_taken) = "' . str_pad((int)$_REQUEST['day'], 2, '0', STR_PAD_LEFT) . '" ';
        }

        if (isset($_REQUEST['season']) && strlen($_REQUEST['season']) > 0) {
            switch ($_REQUEST['season']) {
                case 'summer':
                    $query .= ' AND strftime("%m", date_taken) IN ("06","07","08") ';
                break;
                case 'winter';
                    $query .= ' AND strftime("%m", date_taken) IN ("12","01","02") ';
                break;
                case 'spring';
                    $query .= ' AND strftime("%m", date_taken) IN ("03","04","05") ';
                break;
                case 'autumn':
                    $query .= ' AND strftime("%m", date_taken) IN ("09","10","11") ';
                    break;
            }
        }

        if (isset($_REQUEST['weekday']) && strlen($_REQUEST['weekday']) > 0) {
            foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $index => $day) {
                if ($_REQUEST['weekday'] == $index+1) {
                    $query .= ' AND strftime("%w", date_taken) = "' . $index . '" ';
                }
            }
        }

        if (isset($_REQUEST['daytime']) && strlen($_REQUEST['daytime']) > 0) {
            switch ($_REQUEST['daytime']) {
                case 'morning':
                    $query .= ' AND strftime("%H", date_taken) IN ("06","07","08","09","10","11") ';
                    break;
                case 'afternoon':
                    $query .= ' AND strftime("%H", date_taken) IN ("12","13","14","15","16","17") ';
                    break;
                case 'evening':
                    $query .= ' AND strftime("%H", date_taken) IN ("18","19","20","21","22","23") ';
                    break;
                case 'night':
                    $query .= ' AND strftime("%H", date_taken) IN ("00","01","02","03","04","05") ';
                    break;
            }
        }

        if (isset($_REQUEST['vibe'])) {
            switch ($_REQUEST['vibe']) {
                case 'warm':
                    $query .= ' AND is_warm = 1 ';
                    break;
                case 'cool':
                    $query .= ' AND is_warm = 0 ';
                    break;
            }
        }

        if (isset($_REQUEST['color'])) {
            switch ($_REQUEST['color']) {
                case 'red':
                    $query .= ' AND (hue < 20 OR hue >= 340) ';
                    break;
                case 'orange':
                    $query .= ' AND hue BETWEEN 20 AND 49.99 ';
                    break;
                case 'yellow':
                    $query .= ' AND hue BETWEEN 50 AND 79.99 ';
                    break;
                case 'green':
                    $query .= ' AND hue BETWEEN 80 AND 159.99 ';
                    break;
                case 'blue':
                    $query .= ' AND hue BETWEEN 160 AND 259.99 ';
                    break;
                case 'purple':
                    $query .= ' AND hue BETWEEN 260 AND 309.99 ';
                    break;
                case 'pink':
                    $query .= ' AND hue BETWEEN 310 AND 339.99 ';
                    break;
            }
                
        }    

        if (isset($_REQUEST['style'])) {
             switch ($_REQUEST['style']) {
                case 'monochrome':
                        $query .= ' AND sat < 0.01 ';
                    break;
                case 'natural':
                    $query .= ' AND sat BETWEEN 0.01 AND 0.5 ';
                    break;
                case 'vibrant':
                    $query .= ' AND sat > 0.5';
                    break;
             }
        }

        $query .= ' ORDER BY date_taken DESC';
        $statement = $this->db->prepare($query);

        if (count($words) > 0) {
            foreach ($words as $i => $word) {
                if (substr($word, 0, 1) == '-') {
                    $statement->bindValue(':word' . $i, '%' . $this->replaceDiacritics(substr($word, 1)) . '%');
                } else {
                    $statement->bindValue(':word' . $i, '%' . $this->replaceDiacritics($word) . '%');
                }
            }
        }

        foreach ($this->filters as $requestParam => $filter) {
            if ($filter != 'date_taken') {
                if (isset($_REQUEST[$requestParam]) && strlen($_REQUEST[$requestParam]) > 0) {
                    $statement->bindValue(':' . $filter, $_REQUEST[$requestParam]);
                }
            }
        }

        $result = $statement->execute();

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $results[$row['file_path']] = $row;
        }

        return $results;

    }

    public function replaceDiacritics($string)
    {
        if (function_exists('iconv')) {
            return iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        }

        return $string;

    }

    public function getIndexCount()
    {
        return $this->db->querySingle('SELECT count(*) FROM files');
    }

    public function getLatestImages()
    {
        $limit = \Ralbum\Setting::get('latest_images_count');

        $statement = $this->db->prepare('SELECT * FROM files WHERE file_type = "Ralbum\Model\Image" ORDER BY date_taken DESC LIMIT '. intval($limit));
        $result = $statement->execute();
        $return = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $return[] = new Image($row['file_path']);
        }
        return $return;

    }

    public function getIndexStatus()
    {
        $indexed = [];
        $result = $this->db->query('SELECT file_path, indexed_at FROM files');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $indexed[$row['file_path']] = $row['indexed_at'];
        }
        return $indexed;
    }

    public function getImageCount()
    {
        return $this->db->querySingle('SELECT count(file_path) as file_count FROM files');
    }

    public function getImagesSize()
    {
        return $this->db->querySingle('SELECT sum(file_size) as file_size_total FROM files');
    }

    public function getStats()
    {
        $statement = $this->db->prepare('SELECT * FROM files');
        $result = $statement->execute();

        $keywords = [];
        $folders = [];
        $withoutKeywords = 0;

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {

            if (substr_count($row['file_path'], '/') >= 2) {
                $folder = substr($row['file_path'], 1, strpos($row['file_path'], '/', 1));
                if ($folder) {
                    if (isset($folders[$folder])) {
                        $folders[$folder]++;
                    } else {
                        $folders[$folder] = 1;
                    }
                }
            }

            $thisKeywords = explode(',', $row['keywords']);
            $thisKeywords = array_filter($thisKeywords, 'strlen');

            if (count($thisKeywords) == 0) {
                $withoutKeywords++;
            } else {
                foreach ($thisKeywords as $thisKeyword) {
                    if (isset($keywords[$thisKeyword])) {
                        $keywords[$thisKeyword]++;
                    } else {
                        $keywords[$thisKeyword] = 1;
                    }
                }
            }
        }

        $oldestPhoto = $this->db->querySingle('SELECT * FROM files WHERE date_taken > "1980-01-01" ORDER BY date_taken ASC LIMIT 1', true);
        if ($oldestPhoto) {
            $oldestPhoto['folder'] = dirname($oldestPhoto['file_path']);
            $oldestPhoto['basename'] = basename($oldestPhoto['file_path']);
        }

        $mostRecentPhoto = $this->db->querySingle('SELECT * FROM files WHERE date_taken > "1980-01-01" ORDER BY date_taken DESC LIMIT 1', true);
        if ($mostRecentPhoto) {
            $mostRecentPhoto['folder'] = dirname($mostRecentPhoto['file_path']);
            $mostRecentPhoto['basename'] = basename($mostRecentPhoto['file_path']);

        }

        arsort($keywords);

        return [
            'keywords' => $keywords,
            'folders' => $folders,
            'oldest_photo' => $oldestPhoto,
            'most_recent_photo' => $mostRecentPhoto,
            'count' => $this->getImageCount(),
            'image_file_size' => $this->bytesToSize($this->getImagesSize()),
            'popular_cameras' => $this->getUniqueCameras('usage'),
            'popular_lenses' => $this->getUniqueLenses('usage'),
            'without_keywords' => $withoutKeywords
        ];
    }

    function bytesToSize($bytes) {
        $sizes = [4 => 'TB', 3 => 'GB', 2 => 'MB', 1 => 'kB'];
        foreach ($sizes as $pow => $description) {
            $value = pow(1024, $pow);
            if ($bytes >= $value) {
                return round($bytes /$value, 1) . ' ' . $description;
            }
        }

        return round($bytes, 1) . ' Bytes';
    }

    function getOnThisDay()
    {
        $statement = $this->db->prepare('SELECT * FROM files WHERE file_type = "Ralbum\Model\Image" AND strftime("%m-%d", date_taken) = strftime("%m-%d", "now") ORDER BY date_taken DESC');
        $result = $statement->execute();
        return $this->groupImages($result);
    }

    function getFromThisWeek()
    {
        $statement = $this->db->prepare('SELECT * FROM files WHERE file_type = "Ralbum\Model\Image" AND strftime("%W", date_taken) = strftime("%W", "NOW") ORDER BY date_taken DESC');
        $result = $statement->execute();
        return $this->groupImages($result);
    }

    function groupImages($result)
    {
        $images = [];
        $baseDir = \Ralbum\Setting::get('image_base_dir');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $year = substr($row['date_taken'], 0, 4);
            $image = new Image($baseDir . $row['file_path']);
            if (file_exists($image->getDetailPath()) && file_exists($image->getThumbnailPath())) {
                $images[$year][] = $image;
            }
        }
        return $images;
    }
    
    function getRandom() {

        $max = \Ralbum\Setting::get('random_images_count');

        $images = [];
        $statement = $this->db->prepare('SELECT * FROM files WHERE file_type = "Ralbum\Model\Image" ORDER BY RANDOM() LIMIT ' . intval($max));
        $result = $statement->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $image = new Image($row['file_path']);
            if (file_exists($image->getDetailPath()) && file_exists($image->getThumbnailPath())) {
                $images[] = $image;
            }
        }

        return $images;
    }

    public function getUniqueCameras($sortBy = 'name')
    {
        $cams = [];
        $camsPopular = [];
        $statement = $this->db->prepare('SELECT make, model, count(model) as model_count FROM files WHERE length(TRIM(make)) > 1 AND length(TRIM(model)) > 1 GROUP BY model ORDER BY model_count DESC');
        $result = $statement->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $make = $row['make'];
            $model = $row['model'];

            // if make not in model name, add it to the camName;
            if (strpos($model, $make) === false) {
                $camName = $make . ' ' . $model;
            } else {
                $camName = $model;
            }

            if (strlen($camName) > 0) {
                $cams[$model] = $camName;
                $camsPopular[$model] = ['count' => $row['model_count'], 'name' => $camName];
            }
        }

        if ($sortBy == 'usage') {
            return array_slice($camsPopular, 0, 10);
        }

        asort($cams);

        return $cams;
    }

    public function getUniqueLenses($sortBy = 'name')
    {
        $lenses = [];
        $lensesPopular = [];

        $statement = $this->db->prepare('SELECT lens, count(lens) as lens_count FROM files WHERE length(TRIM(lens)) > 1 GROUP BY lens ORDER BY lens_count DESC');
        $result = $statement->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $lens = $row['lens'];
            $lens = trim($lens);
            $lens = trim($lens, '-');
            if (strlen($lens) > 0) {
                $lenses[$lens] = $lens;
                $lensesPopular[$lens] = $row['lens_count'];
            }
        }

        if ($sortBy == 'usage') {
            return array_slice($lensesPopular, 0, 10);
        }

        asort($lenses);
        return $lenses;
    }

    public function getImagesWithGeo()
    {
        $images = [];

        $statement = $this->db->prepare('SELECT * FROM files WHERE length(lat) > 0');
        $result = $statement->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $images[] = $row;
        }

        return $images;
    }
}