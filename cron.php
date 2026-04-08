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

if (!Search::isSupported()) {
    echo 'Search not supported, requires SQLite3' . "\n";
    return;
}


function updateRecursively($baseDir, $search, $indexed, &$processedFiles)
{
    foreach (scandir($baseDir) as $file) {
        if (substr($file, 0, 1) == '.') {
            continue;
        }

        if (substr($file, -4) == '.xmp') {
            continue;
        }

        $fullPath = $baseDir . '/' . $file;

        if (is_dir($fullPath)) {
            updateRecursively($fullPath, $search, $indexed, $processedFiles);
            continue;
        }

        echo "Processing file " . $fullPath . "\n";

        $file = new File($fullPath);

        $indexUpdateNeeded = false;

        $relativePath = $file->getRelativeLocation();
        $processedFiles[] = $relativePath;

        if (in_array($file->getExtension(), Setting::get('supported_extensions'))) {
            
            $file = new Image($fullPath);

            $filePath = $file->getPath();
            $xmlFilePath1 = $file->getPath() . '.xmp';
            $xmlFilePath2 = preg_replace('/\.[^.]+$/', '.xmp', $file->getPath());

            if (isset($indexed[$relativePath])) {
                foreach ([$filePath, $xmlFilePath1, $xmlFilePath2] as $filePathCheck) {
                    if (file_exists($filePathCheck) && filemtime($filePathCheck) > $indexed[$relativePath]) {
                        $indexUpdateNeeded = true;
                    }
                }
            } else {
                $indexUpdateNeeded = true;
            }

            // to speed up the cron process the detail images are not generated if the default option is full size
            if (!Setting::get('full_size_by_default')) {
                $file->updateDetail();
            }
            $file->updateThumbnail();

        } else {
            $indexUpdateNeeded = true;
        }

        if ($indexUpdateNeeded) {
            $file->updateIndex($search);
        }
    }
}


echo "\n\nStart\n";
echo "--------------------------\n";
echo date('Y-m-d H:i:s') . "\n";

$processedFiles = [];

// reset search index
$search = new Search();
$search->initialize();
$indexed = $search->getIndexStatus();
$search->begintTransaction();
updateRecursively(Setting::get('image_base_dir'), $search, $indexed, $processedFiles);

$toDelete = array_diff(array_keys($indexed), $processedFiles);
foreach ($toDelete as $path) {
    echo "Removing deleted file from index: $path\n";
    $search->removeFromIndex($path);
}


$search->commitTransaction();


echo "\n\nFinished\n";
echo "--------------------------\n";
echo "Make sure you set the correct permissions on the cache folder (or use sudo -u)\n";
echo "and all its subfolders and files so the user the apache\n";
echo "process runs on is able to write to the folder\n";
