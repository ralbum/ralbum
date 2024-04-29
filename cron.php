<?php

use Ralbum\Search;
use Ralbum\Setting;
use Ralbum\Model\Image;
use Ralbum\Model\File;

define('BASE_DIR', dirname(__FILE__));

require 'app/vendor/autoload.php';

if (php_sapi_name() != 'cli') {
    die('This file should be run on the commandline');
}

echo "\n\nStart\n";
echo "--------------------------\n";
echo date('Y-m-d H:i:s') . "\n";

// reset search index
$search = new Search();
$search->resetIndex();

function updateRecursively($baseDir, $search)
{
    foreach (scandir($baseDir) as $file) {
        if (substr($file, 0, 1) == '.') {
            continue;
        }

        $fullPath = $baseDir . '/' . $file;

        if (is_dir($fullPath)) {
            updateRecursively($fullPath, $search);
            continue;
        }

        $file = new File($fullPath);

        if (in_array($file->getExtension(), Setting::get('supported_extensions'))) {
            $file = new Image($fullPath);

            echo "Processing image " . $fullPath . "\n";

            // to speed up the cron process the detail images are not generated if the default option is full size
            if (!Setting::get('full_size_by_default')) {
                $file->updateDetail();
            }
            $file->updateThumbnail();

        }

        $file->updateIndex($search);
    }
}

updateRecursively(Setting::get('image_base_dir'), $search);
$search->save();

echo "\n\nFinished\n";
echo "--------------------------\n";
echo "Make sure you set the correct permissions on the cache folder (or use sudo -u)\n";
echo "and all its subfolders and files so the user the apache\n";
echo "process runs on is able to write to the folder\n";
