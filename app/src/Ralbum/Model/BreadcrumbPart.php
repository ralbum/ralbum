<?php

namespace Ralbum\Model;

class BreadcrumbPart
{
    protected $path;
    protected $title;
    protected $isLast;

    public function __construct($path, $title, $isLast = false)
    {
        $this->path = $path;
        $this->title = $title;
        $this->isLast = $isLast;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getIsLast()
    {
        return $this->isLast;
    }

}