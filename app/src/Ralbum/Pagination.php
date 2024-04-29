<?php

namespace Ralbum;

class Pagination
{
    public $currentPage;
    public $totalItems;
    public $totalPages;
    public $lastPage;
    public $itemsPerPage;

    public function getNumberOfPages()
    {
        if ($this->itemsPerPage > 0) {
            return ceil($this->totalItems / $this->itemsPerPage);
        }

        return 0;
    }

    public function getBaseUrl()
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $params = $_GET;

        if (isset($params['page'])) {
            unset($params['page']);
        }

        if (count($params) > 0) {
            $path .= '?' . http_build_query($params);
        }

        return $path;
    }

}