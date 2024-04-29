<?php

namespace Ralbum\Model;

class File
{
    protected $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function fileExists()
    {
        return file_exists($this->getPath());
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getName()
    {
        return basename($this->path);
    }

    public function getFolderName()
    {
        return str_replace($this->getName(), '', $this->getRelativeLocation());
    }

    public function getExtension()
    {
        $parts = explode('.', $this->path);
        return strtolower(end($parts));
    }

    public function getTitle()
    {
        return $this->getName();
    }

    public function getRelativeLocation()
    {
        return str_replace(\Ralbum\Setting::get('image_base_dir'), '', $this->path);
    }

    public function getOriginalUrl()
    {
        return BASE_URL_RALBUM . '/original' . $this->getRelativeLocation();
    }

    public function getUrl()
    {
        return BASE_URL . $this->getRelativeLocation();
    }

    public function getFileModificationTime($path)
    {
        if (file_exists($path)) {
            return filemtime($path);
        }

        return false;
    }

    public function isValidPath()
    {
        $path = realpath($this->path);

        $symlinkedDirs = \Ralbum\Setting::get('symlinked_directories');
        if (empty($symlinkedDirs)) {
            $symlinkedDirs = [];
        } else {
            $symlinkedDirs = (array)$symlinkedDirs;
        }

        $allowedPaths = [];
        $allowedPaths[] = \Ralbum\Setting::get('image_base_dir');
        $allowedPaths = array_merge($allowedPaths, $symlinkedDirs);

        foreach ($allowedPaths as $allowedPath) {

            if (substr($path, 0, strlen($allowedPath)) == $allowedPath) {
                return true;
            }
        }

        return false;
    }

    public function updateIndex(\Ralbum\Search $search)
    {
        $search->setEntry($this->getRelativeLocation(), basename($this->path), __CLASS__);
    }
}