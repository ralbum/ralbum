<?php

namespace Ralbum;

use Ralbum\Model\Image;

class Search
{
    protected $index = [];
    protected $db = null;

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

        if (isset($_GET['debug_database'])) {
            echo '<pre>';
            $statement = $this->db->prepare('SELECT *, datetime(date_taken) FROM files LIMIT 1000');
            $result = $statement->execute();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                var_dump($row);
            }
            die();
        }
    }

    public function createTable()
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS files (file_path STRING, file_name STRING, keywords STRING, file_type STRING, date_taken DATETIME, make STRING, model STRING, aperture DOUBLE, shutterspeed DOUBLE, iso INT, focal_length DOUBLE, lens STRING, lat DOUBLE, long DOUBLE)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS file_type_name on files(file_type)');
    }

    public function resetIndex()
    {
        $this->db->query('DROP TABLE files');
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
        ];

        $dataPlaceHolders = [];
        foreach ($dataKeys as $i => $val) {
            $dataPlaceHolders[$i] = ':' . $val;
        }

        $statement = $this->db->prepare('INSERT INTO files (' . implode(', ', $dataKeys) . ') VALUES (' .  implode(', ', $dataPlaceHolders) . ' )');

        $statement->bindValue(':file_path', $key);
        $statement->bindValue(':file_name', $filename);
        $statement->bindValue(':file_type', $type);

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

    public function hasFilter()
    {
        $filters = array_keys($this->filters);
        $filters[] = 'limit_to_keyword_search';

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
                    $query .= 'AND strftime("%m", date_taken) IN ("06","07","08") ';
                break;
                case 'winter';
                    $query .= 'AND strftime("%m", date_taken) IN ("12","01","02") ';
                break;
                case 'spring';
                    $query .= 'AND strftime("%m", date_taken) IN ("03","04","05") ';
                break;
                case 'autumn':
                    $query .= 'AND strftime("%m", date_taken) IN ("09","10","11") ';
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
        $statement = $this->db->prepare('SELECT count(*) FROM files');
        $result = $statement->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            return $row['count(*)'];
        }
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

    public function getImageCount()
    {
        $statement = $this->db->prepare('SELECT count(file_path) as file_count FROM files');
        $result = $statement->execute();

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            return $row['file_count'];
        }
    }

    public function getStats()
    {
        $statement = $this->db->prepare('SELECT * FROM files');
        $result = $statement->execute();

        $keywords = [];
        $folders = [];
        $count = 0;
        $keywordCount = 0;
        $withoutKeywords = 0;
        $imagesWithKeywords = 0;

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {

            $count++;

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
                $imagesWithKeywords++;
                foreach ($thisKeywords as $thisKeyword) {
                    $keywordCount++;
                    if (isset($keywords[$thisKeyword])) {
                        $keywords[$thisKeyword]++;
                    } else {
                        $keywords[$thisKeyword] = 1;
                    }
                }
            }


        }

        arsort($keywords);

        return [
            'keywords' => $keywords,
            'folders' => $folders,
            'count' => $count,
            'keyword_count' => $keywordCount,
            'without_keywords' => $withoutKeywords,
            'average_number_keywords' => round($imagesWithKeywords > 0 ? ($keywordCount/$imagesWithKeywords) : 0, 3)
        ];
    }

    function getOnThisDay()
    {
        $month = date('m');
        $day = date('d');
        $statement = $this->db->prepare('SELECT * FROM files WHERE file_type = "Ralbum\Model\Image" AND date(date_taken) LIKE "%-' . $month . '-' . $day . '" ORDER BY date_taken DESC LIMIT 50');
        $result = $statement->execute();
        $images = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $year = substr($row['date_taken'], 0, 4);
            $image = new Image($row['file_path']);
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

    public function getUniqueCameras()
    {
        $cams = [];
        $statement = $this->db->prepare('SELECT make, model FROM files WHERE length(TRIM(make)) > 1 AND length(TRIM(model)) > 1');
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
            }
        }

        asort($cams);

        return $cams;
    }

    public function getUniqueLenses()
    {
        $lenses = [];

        $statement = $this->db->prepare('SELECT lens FROM files WHERE length(TRIM(lens)) > 1');
        $result = $statement->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $lens = $row['lens'];
            $lens = trim($lens);
            $lens = trim($lens, '-');
            if (strlen($lens) > 0) {
                $lenses[$lens] = $lens;
            }
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