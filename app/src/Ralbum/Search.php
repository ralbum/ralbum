<?php

namespace Ralbum;

use Ralbum\Model\Image;

class Search
{
    protected $index = [];
    protected $indexFile = null;
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

    function __construct($indexFile = null)
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

    function createTable()
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS files (file_path STRING, file_name STRING, keywords STRING, file_type STRING, date_taken DATETIME, make STRING, model STRING, aperture DOUBLE, shutterspeed DOUBLE, iso INT, focal_length DOUBLE, lens STRING, lat DOUBLE, long DOUBLE)');
        $this->db->exec('CREATE INDEX file_type_name on files(file_type)');
    }

    function resetIndex()
    {
        $this->db->query('DROP TABLE files');
        $this->createTable();
    }

    function setEntry($key, $filename, $type, $metadata = [])
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
                        $metadata[$val] = implode(' ', $metadata[$val]);
                    }
                    $statement->bindValue(':'. $val, $metadata[$val]);
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

    function search($q)
    {
        $results = [];

        $words = explode(' ', trim($q));
        $words = array_filter($words,'strlen');

        $query = 'SELECT * FROM files WHERE 1=1 ';

        if (count($words) > 0) {

            $keywordSearches = [];
            $filenameSearches = [];
            foreach ($words as $i => $word) {
                $keywordSearches[] = ' keywords LIKE :word' . $i . ' ';
                $filenameSearches[] = ' file_name LIKE :word' . $i . ' ';
            }

            if (isset($_REQUEST['limit_to_keyword_search'])) {
                $query .= 'AND ' . implode(' AND ', $keywordSearches);
            } else {
                $query .= ' AND ( (' . implode(' AND ', $filenameSearches) . ') OR ( '. implode(' AND ', $keywordSearches) . ')) ';
            }
        }


        foreach ($this->filters as $requestParam => $filter) {
            if ($filter == 'date_taken') {
                //TODO
            } else {
                if (isset($_REQUEST[$requestParam]) && strlen($_REQUEST[$requestParam]) > 0) {
                    $query .= ' AND ' . $filter . ' LIKE "%' . $_REQUEST[$requestParam] . '%"';
                }
            }
        }

//        $perPage = 100;
//        $page = max(1, isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1);
//        $offset = ($page-1)*$perPage;
//        $query .= ' ORDER BY date_taken DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $query .= ' ORDER BY date_taken DESC';
        $statement = $this->db->prepare($query);

//        var_dump($query); die();
//        var_dump($this->db->lastErrorMsg());
//        die();

        if (count($words) > 0) {
            foreach ($words as $i => $word) {
                $statement->bindValue(':word' . $i, '%' . $word . '%');
            }
        }

        $result = $statement->execute();

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $results[$row['file_path']] = $row;
        }

        return $results;

    }

    function replaceDiacritics($string)
    {
        if (function_exists('iconv')) {
            return iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        }

        return $string;

    }

    function getIndexCount()
    {
        $statement = $this->db->prepare('SELECT count(*) FROM files');
        $result = $statement->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            return $row['count(*)'];
        }
    }

//    function sortByDateTaken($a, $b)
//    {
//        if (!isset($a['metadata']) || !isset($a['metadata']['date_taken'])) {
//            return 0;
//        }
//
//        if (!isset($b['metadata']) || !isset($b['metadata']['date_taken'])) {
//            return 0;
//        }
//
//        $dateA = $a['metadata']['date_taken'];
//        $dateB = $b['metadata']['date_taken'];
//
//        if ($dateA == $dateB) {
//            return 0;
//        }
//
//        return $dateA > $dateB ? -1 : 1;
//    }

    function getLatestImages()
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

    function getPopularKeywords()
    {
        $statement = $this->db->prepare('SELECT count(*) FROM files');
        $result = $statement->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            return $row;
        }
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
            $images[$row['file_name']] = $row;
        }

        return $images;
    }
}