<?php

namespace Ralbum;

class ImageProcessor {
    private $img;
    private $isIm;

    public function __construct($data) {
        $this->isIm = extension_loaded('imagick');
        if ($this->isIm) {
            $this->img = new \Imagick();
            $this->img->readImageBlob($data);
        } else {
            $this->img = imagecreatefromstring($data);
        }
    }

    public function rotate($d) {
        if ($this->isIm) $this->img->rotateImage(new \ImagickPixel('none'), $d);
        else $this->img = imagerotate($this->img, -$d, 0);
        return $this;
    }

    public function fixOrientation($orientation) {
        // Imagick en GD roteren andersom bij positieve/negatieve graden
        $deg = $this->isIm ? [3=>180, 6=>90, 8=>-90] : [3=>180, 6=>-90, 8=>90];
        if (isset($deg[$orientation])) {
            $this->rotate($deg[$orientation]);
        }
        return $this;
    }

    public function resize($w) {
        if ($this->isIm) $this->img->resizeImage($w, 0, \Imagick::FILTER_LANCZOS, 1);
        else {
            $h = imagesy($this->img) * ($w / imagesx($this->img));
            $t = imagecreatetruecolor($w, $h);
            imagecopyresampled($t, $this->img, 0, 0, 0, 0, $w, $h, imagesx($this->img), imagesy($this->img));
            $this->img = $t;
        }
        return $this;
    }

    public function thumbnail($w, $h) {
        if ($this->isIm) $this->img->cropThumbnailImage($w, $h);
        else {
            $t = imagecreatetruecolor($w, $h);
            $r = max($w / imagesx($this->img), $h / imagesy($this->img));
            imagecopyresampled($t, $this->img, 0, 0, (imagesx($this->img) - $w / $r) / 2, (imagesy($this->img) - $h / $r) / 2, $w, $h, $w / $r, $h / $r);
            $this->img = $t;
        }
        return $this;
    }

    public function toJpg() {
        if ($this->isIm) {
            $this->img->setImageFormat('jpg');
            return $this->img->getImageBlob();
        }
        ob_start(); imagejpeg($this->img, null, 85); return ob_get_clean();
    }

    public function analyze() {
        if ($this->isIm) {
            $c = clone $this->img; $c->resizeImage(1, 1, \Imagick::FILTER_BOX, 1);
            $p = $c->getImagePixelColor(0, 0)->getColor();
            $r = $p['r']; $g = $p['g']; $b = $p['b'];
        } else {
            $t = imagecreatetruecolor(1, 1);
            imagecopyresampled($t, $this->img, 0, 0, 0, 0, 1, 1, imagesx($this->img), imagesy($this->img));
            $c = imagecolorat($t, 0, 0);
            $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
        }

        $hsl = $this->rgbToHsl($r, $g, $b);
        return [
            'hex'     => sprintf("#%02x%02x%02x", $r, $g, $b),
            'hue'     => (int)round($hsl['h']),
            'is_warm' => ($hsl['h'] <= 90 || $hsl['h'] >= 300) ? 1 : 0,
            'sat'     => round($hsl['s'], 2)
        ];
    }

    private function rgbToHsl($r, $g, $b) {
        $r /= 255; $g /= 255; $b /= 255;
        $max = max($r, $g, $b); $min = min($r, $g, $b);
        $l = ($max + $min) / 2; $h = $s = 0;
        if ($max !== $min) {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch ($max) {
                case $r: $h = ($g - $b) / $d + ($g < $b ? 6 : 0); break;
                case $g: $h = ($b - $r) / $d + 2; break;
                case $b: $h = ($r - $g) / $d + 4; break;
            }
            $h /= 6;
        }
        return ['h' => $h * 360, 's' => $s, 'l' => $l];
    }
}