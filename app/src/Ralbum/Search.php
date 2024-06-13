<?php

namespace Ralbum;

use Ralbum\Model\Image;

class Search
{
    protected $index = null;
    protected $indexFile = null;

    public $filters = [
        'camera' => 'model',
        'lens' => 'lens',
        'year' => 'date_taken',
        'month' => 'date_taken',
        'day' => 'date_taken',
    ];

    function __construct($indexFile = null)
    {
        if ($indexFile === null) {
            $indexFile = BASE_DIR . '/data/search.json';
        }

        $this->indexFile = $indexFile;

        if (!file_exists($this->indexFile) || strlen(file_get_contents($this->indexFile)) == 0) {
            if (!file_put_contents($this->indexFile, json_encode([], JSON_PRETTY_PRINT))) {
                throw new \Exception('Could not create index file: ' . $this->indexFile);
            }
        }

        if (!$this->loadIndex()) {
            throw new \Exception('Could not read index file: ' . $this->indexFile);
        }
    }

    function resetIndex()
    {
        $this->index = [];
    }

    function loadIndex()
    {
        $index = json_decode(file_get_contents($this->indexFile), true);

        if ($index === false) {
            return false;
        }

        $this->index = $index;

        return true;

    }

    function setEntry($key, $value, $type, $metadata = [])
    {
        $data = array_merge(['search_data' => $value, 'type' => $type, 'metadata' => $metadata]);

        $this->index[$key] = $data;
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

    function matchesMetadataFilter($value, $requestParameter, $metadataProperty)
    {
        if (!isset($_REQUEST[$requestParameter]) || strlen($_REQUEST[$requestParameter]) == 0) {
            return true;
        }

        if (!isset($value['metadata']) || !isset($value['metadata'][$metadataProperty]) || strlen($value['metadata'][$metadataProperty]) == 0) {
            return false;
        }

        if (in_array($requestParameter, ['year', 'month', 'day'])) {

            switch ($requestParameter) {
                case 'year':
                    return date('Y', $value['metadata'][$metadataProperty]) == $_REQUEST[$requestParameter];
                    break;
                case 'month':
                    return date('m', $value['metadata'][$metadataProperty]) == $_REQUEST[$requestParameter];
                    break;
                case 'day':
                    return date('d', $value['metadata'][$metadataProperty]) == $_REQUEST[$requestParameter];
                    break;
                default:
                    return false;
                    break;
            }
        }

        if ($value['metadata'][$metadataProperty] == $_REQUEST[$requestParameter]) {
            return true;
        }

        return false;
    }

    function search($q)
    {
        $results = [];

        $words = explode(' ', trim($q));
        $words = array_filter($words,'strlen');

        $sortedIndex = $this->index;

        uasort($sortedIndex, [$this, 'sortByDateTaken']);

        foreach ($sortedIndex as $key => $value) {

            if (is_string($value)) {
                $searchValue = $value;
            } else {
                $searchValue = $value['search_data'];
            }

            if (!isset($_REQUEST['limit_to_keyword_search'])) {
                $searchValue .= $key;
            }

            foreach ($this->filters as $requestParam => $filter) {
                if (!$this->matchesMetadataFilter($value, $requestParam, $filter)) {
                    continue 2;
                }
            }

            if (!empty($words)) {
                foreach ($words as $word) {
                    if (stripos($searchValue, $word) === false && stripos($this->replaceDiacritics($searchValue), $word) === false) {
                        continue 2;
                    }
                }
            }

            $results[$key] = $searchValue;

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
        return count($this->index);
    }

    function save()
    {
        file_put_contents($this->indexFile, json_encode($this->index, JSON_PRETTY_PRINT));
    }

    function sortByDateTaken($a, $b)
    {
        if (!isset($a['metadata']) || !isset($a['metadata']['date_taken'])) {
            return 0;
        }

        if (!isset($b['metadata']) || !isset($b['metadata']['date_taken'])) {
            return 0;
        }

        $dateA = $a['metadata']['date_taken'];
        $dateB = $b['metadata']['date_taken'];

        if ($dateA == $dateB) {
            return 0;
        }

        return $dateA > $dateB ? -1 : 1;
    }

    function getLatestImages()
    {
        $limit = \Ralbum\Setting::get('latest_images_count');
        $sortedIndex = $this->index;

        $sortedIndex = array_filter($sortedIndex, function($element) {
            if (isset($element['type'])) {
                return $element['type'] == Image::class;
            }
            return true;
        });

        uasort($sortedIndex, [$this, 'sortByDateTaken']);

        $sliced = array_slice($sortedIndex, 0, $limit);

        $return = [];

        foreach ($sliced as $key => $image) {
            $return[] = new Image($key);
        }

        return $return;

    }

    function getOnThisDay()
    {
        $images = [];
        foreach ($this->index as $key => $item) {

            if (!isset($item['metadata']) || !isset($item['metadata']['date_taken'])) {
                continue;
            }

            if (date('m-d', $item['metadata']['date_taken']) != date('m-d')) {
                continue;
            }
            $year = date('Y', $item['metadata']['date_taken']);
            $images[$year][] = new Image($key);
        }

        krsort($images);
        return $images;

    }
    
    function getRandom() {
        $keys = array_keys($this->index);
        shuffle($keys);
        $slice = array_slice($keys, 0, 20);
        $images = [];
        $i = 0;
        $max = \Ralbum\Setting::get('random_images_count');
        foreach ($slice as $key) {
            $image = new Image($key);
            // just to be sure we don't load any images on the homepage that need to be generated
            if (file_exists($image->getDetailPath()) && file_exists($image->getThumbnailPath())) {
                $i++;
                $images[] = $image;
            }
            if ($i >= $max) {
                break;
            }
        }
        return $images;
    }

    public function getUniqueCameras()
    {
        $cams = [];
        foreach ($this->index as $item) {
            if ($item['metadata']) {
                $make = isset($item['metadata']['make']) ? $item['metadata']['make'] : '';
                $model = isset($item['metadata']['model']) ? $item['metadata']['model'] : '';

                if (strlen($make) > 0 && strlen($model) > 0) {

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
            }
        }

        asort($cams);
        return $cams;
    }

    public function getUniqueLenses()
    {
        $lenses = [];
        foreach ($this->index as $item) {
            if ($item['metadata']) {
                if (isset($item['metadata']['lens'])) {
                    $lens = $item['metadata']['lens'];
                    $lens = trim($lens);
                    $lens = trim($lens, '-');
                    if (strlen($lens) > 0) {
                        $lenses[$lens] = $lens;
                    }
                }
            }
        }

        asort($lenses);
        return $lenses;
    }

    public function getImagesWithGeo()
    {
        $images = [];
        foreach ($this->index as $key => $item) {
            if ($item['metadata']) {
                if (isset($item['metadata']['lat']) && $item['metadata']['lat'] > 0) {
                    $images[$key] = $item;
                }
            }
        }
        return $images;
    }
}