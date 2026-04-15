<?php

namespace Ralbum\Model;


class Image extends File
{
    public function getPreferredUrl()
    {
        if (isset($_SESSION['full-size']) && $_SESSION['full-size'] === true) {
            return $this->getOriginalUrl();
        } else {
            return $this->getDetailUrl();
        }
    }

    public function getDetailPath()
    {
        $detailCacheFile = BASE_DIR . '/' . 'cache' . '/' . 'detail';
        $detailCacheFile .= $this->getRelativeLocation();

        return $detailCacheFile;
    }

    public function getDetailUrl()
    {
        $detailCacheFile = $this->getDetailPath();

        if (file_exists($detailCacheFile)) {
            return BASE_URL . '/cache/detail' . $this->getRelativeLocation() . '?t=' . $this->getFileModificationTime($this->getDetailPath());
        } else {
            return BASE_URL_RALBUM . '/detail' . $this->getRelativeLocation() . '?t=' . $this->getFileModificationTime($this->getDetailPath());
        }
    }

    public function isDetailCurrent()
    {
        return file_exists($this->getDetailPath()) && $this->getFileModificationTime($this->getDetailPath()) >= $this->getFileModificationTime($this->getPath());
    }

    public function getOriginalUrl()
    {
        return BASE_URL_RALBUM . '/original' . $this->getRelativeLocation() . '?t=' . $this->getFileModificationTime($this->getPath());
    }

    public function getThumbnailPath()
    {
        $thumbnailPath = BASE_DIR . '/' . 'cache' . '/' . 'thumbnail';

        $thumbnailPath .= $this->getRelativeLocation();

        return $thumbnailPath;
    }

    public function getThumbnailUrl()
    {
        if (file_exists($this->getThumbnailPath())) {
            return BASE_URL . '/cache/thumbnail' . str_replace(\Ralbum\Setting::get('image_base_dir'), '',
                    $this->path) . '?t=' . $this->getFileModificationTime($this->getThumbnailPath());
        } else {
            return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        }
    }

    public function isThumbnailCurrent()
    {
        return file_exists($this->getThumbnailPath()) && $this->getFileModificationTime($this->getThumbnailPath()) >= $this->getFileModificationTime($this->getPath());
    }

    public function updateThumbnail($updateFromDetailImage = false)
    {
        if (!$this->fileExists()) {
            \Ralbum\Log::addEntry('error', 'Cannot create thumbnail, file: ' . $this->getPath() . ' does not exist');
            return false;
        }

        if (!$this->isValidPath()) {
            \Ralbum\Log::addEntry('error', 'Image does not have a valid path: ' . $this->getPath());
            return false;
        }

        $thumbnailPath = $this->getThumbnailPath();

        // thumbnail file exits and is newer than the original, no action required
        if ($updateFromDetailImage === false && file_exists($thumbnailPath) && filemtime($thumbnailPath) >= filemtime($this->getPath())) {
            return true;
        }

        $thumbnailDir = dirname($thumbnailPath);

        if (!is_dir($thumbnailDir)) {
            if (!mkdir($thumbnailDir, 0777, true)) {
                throw new \Exception('Could not create cache file directory: ' . $thumbnailDir);
            }
        }

        try {
            if ($updateFromDetailImage) {
                $image = new \vakata\image\Image(file_get_contents($this->getDetailPath()));
            } else {
                $image = new \vakata\image\Image(file_get_contents($this->getPath()));

                if (\Ralbum\Setting::get('auto_rotate')) {
                    $image = $this->fixOrientation($image);
                }
            }

            $resizedImage = $image->thumbnail(
                \Ralbum\Setting::get('thumbnail_width'),
                \Ralbum\Setting::get('thumbnail_height')
            )->toJpg();

            if (!file_put_contents($this->getThumbnailPath(), $resizedImage)) {
                \Ralbum\Log::addEntry('error', 'Could not save thumbnail from image: ' . $this->getRelativeLocation());
            }
        } catch (\Exception $e) {
            \Ralbum\Log::addEntry('error',
                'Could not create thumbnail from image: ' . $this->getRelativeLocation() . '. ' . $e->getMessage());
            return false;
        }

        return file_exists($this->getThumbnailPath());
    }

    public function updateIndex(\Ralbum\Search $search)
    {
        $metadata = $this->getMetadata();

        $photoAnalysis = $this->getPhotoAnalysis();

        $metadataArray = [
            'date_taken' => $metadata->getDateTaken('Y-m-d H:i:s'),
            'make' => $metadata->getMake(),
            'model' => $metadata->getModel(),
            'aperture' => $metadata->getAperture(),
            'shutterspeed' => $metadata->getShutterSpeed(),
            'iso' => $metadata->getIso(),
            'focal_length' => $metadata->getFocalLength(),
            'lens' => $metadata->getLens(),
            'lat' => $metadata->getGpsData() ? $metadata->getGpsData()[0] : null,
            'long' => $metadata->getGpsData() ? $metadata->getGpsData()[1] : null,
            'keywords' => (array)$metadata->getKeywords(),
            'hex' => $photoAnalysis['hex'],
            'hue' => $photoAnalysis['hue'],
            'is_warm' => $photoAnalysis['is_warm'],
            'sat' => $photoAnalysis['sat'],
        ];

        $search->setEntry($this->getRelativeLocation(), basename($this->path), __CLASS__, $metadataArray);
    }

    public function updateDetail($updateCurrent = false, $fixedRotate = null)
    {
        if (!$this->fileExists()) {
            \Ralbum\Log::addEntry('error', 'Cannot create detail, file: ' . $this->getPath() . ' does not exist');
            return false;
        }

        if (!$this->isValidPath()) {
            \Ralbum\Log::addEntry('error', 'Image does not have a valid path: ' . $this->getPath());
            return false;
        }

        $detailPath = $this->getDetailPath();

        // detail file exists and original file has not been modified since last update of detail file[
        if ($updateCurrent === false && file_exists($detailPath) && filemtime($detailPath) >= filemtime($this->getPath())) {
            return true;
        }

        $detailDir = dirname($detailPath);

        if (!is_dir($detailDir)) {
            if (!mkdir($detailDir, 0777, true)) {
                throw new \Exception('Could not create cache file directory: ' . $detailDir);
            }
        }

        try {
            if ($updateCurrent) {
                if (file_exists($this->getDetailPath())) {
                    $image = new \vakata\image\Image(file_get_contents($this->getDetailPath()));
                } else {
                    $image = new \vakata\image\Image(file_get_contents($this->getPath()));
                }
            } else {
                $image = new \vakata\image\Image(file_get_contents($this->getPath()));
            }

            if ($fixedRotate == null) {
                if (\Ralbum\Setting::get('auto_rotate')) {
                    $image = $this->fixOrientation($image);
                }
            } else {
                $image->rotate($fixedRotate);
            }

            $resizedImage = $image->resize(\Ralbum\Setting::get('detail_width'))
                ->toJpg();

            if (!file_put_contents($this->getDetailPath(), $resizedImage)) {

                \Ralbum\Log::addEntry('error', 'Could not save detail image from image: ' . $this->getRelativeLocation());
            }
        } catch (\Exception $e) {
            \Ralbum\Log::addEntry('error',
                'Could not create detail image from image: ' . $this->getRelativeLocation() . '. ' . $e->getMessage());
            return false;
        }

        return file_exists($this->getDetailPath());
    }

    public function getMetadata()
    {
        return new \Ralbum\Metadata($this->getPath());
    }

    public function fixOrientation(\vakata\image\Image $image)
    {
        $metadata = $this->getMetadata();

        // imagick and GD handle this different
        if (extension_loaded('imagick')) {
            $degrees = [3 => 180, 6 => 90, 8 => -90];
        } else {
            $degrees = [3 => 180, 6 => -90, 8 => 90];
        }

        if ($metadata && $metadata->getOrientation() > 0) {
            if (array_key_exists($metadata->getOrientation(), $degrees)) {
                $image->rotate($degrees[$metadata->getOrientation()]);
            }
        }
        
        return $image;

    }

    /**
     * RGB toHSL (Hue, Saturation, Lightness)
     * Output: h (0-360), s (0-1), l (0-1)
     */
    function rgbToHsl(int $r, int $g, int $b): array {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        
        $l = ($max + $min) / 2;
        $h = 0;
        $s = 0;

        if ($max !== $min) {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

            switch ($max) {
                case $r:
                    $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
                    break;
                case $g:
                    $h = ($b - $r) / $d + 2;
                    break;
                case $b:
                    $h = ($r - $g) / $d + 4;
                    break;
            }
            $h /= 6;
        }

        return [
            'h' => $h * 360,
            's' => $s,
            'l' => $l
        ];
    }

    function getPhotoAnalysis()
    {
        $img = @imagecreatefromjpeg($this->getThumbnailPath());
        if (!$img) return null;

        $tmp = imagecreatetruecolor(1, 1);
        
        imagecopyresampled($tmp, $img, 0, 0, 0, 0, 1, 1, imagesx($img), imagesy($img));

        $rgb = imagecolorat($tmp, 0, 0);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        $hsl = $this->rgbToHsl($r, $g, $b);
        
        $isWarm = ($hsl['h'] <= 90 || $hsl['h'] >= 300);

        return [
            'hex'     => sprintf("#%02x%02x%02x", $r, $g, $b),
            'hue'     => (int)round($hsl['h']),
            'is_warm' => $isWarm ? 1 : 0,
            'sat'     => round($hsl['s'], 2),
        ];
    }
}
