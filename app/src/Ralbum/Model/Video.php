<?php

namespace Ralbum\Model;


class Video extends File
{
    public $playable = true;

    public function getPlayUrl()
    {
        return BASE_URL_RALBUM . '/video_stream' . $this->getRelativeLocation();
    }
}