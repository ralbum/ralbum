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
                throw new Exception('Could not create cache file directory: ' . $thumbnailDir);
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

        $metadataArray = [
            'date_taken' => date('Y-m-d H:i:s', $metadata->getDateTaken()),
            'make' => $metadata->getMake(),
            'model' => $metadata->getModel(),
            'aperture' => $metadata->getAperture(),
            'shutterspeed' => $metadata->getShutterSpeed(),
            'iso' => $metadata->getIso(),
            'focal_length' => $metadata->getFocalLength(),
            'lens' => $metadata->getLens(),
            'lat' => $metadata->getGpsData() ? $metadata->getGpsData()[0] : null,
            'long' => $metadata->getGpsData() ? $metadata->getGpsData()[1] : null,
            'keywords' => (array)$metadata->getKeywords()
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
                throw new Exception('Could not create cache file directory: ' . $detailDir);
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
}
