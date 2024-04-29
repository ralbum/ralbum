<?php

namespace Ralbum\Model;

class Folder extends File
{
    function getUrl()
    {
        $url = parent::getUrl();

        if (empty($url)) {
            return '/';
        }

        return $url;
    }

}