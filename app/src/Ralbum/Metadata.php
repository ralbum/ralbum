<?php

namespace Ralbum;

class Metadata
{
    protected $exif = false;
    protected $iptc = false;

    public function __construct($path)
    {
        if (file_exists($path)) {
            $this->exif = @exif_read_data($path);

            @getimagesize($path, $info);

            $arrData = array();
            if (isset($info['APP13'])) {
                $this->iptc = iptcparse($info['APP13']);
            }
        }
    }

    public function getOrientation()
    {
        if ($this->exif && isset($this->exif['Orientation'])) {
            return $this->exif['Orientation'];
        }

        return false;
    }

    public function getKeywords()
    {
        if ($this->iptc && isset($this->iptc['2#025'])) {
            return $this->iptc['2#025'];
        }
        return false;
    }

    public function getRawExifData()
    {
        if ($this->exif) {
            return $this->exif;
        }

        return false;
    }

    public function getMake()
    {
        if (isset($this->exif['Make'])) {
            return $this->exif['Make'];
        }
        return false;
    }

    public function getModel()
    {
        if (isset($this->exif['Model'])) {
            return $this->exif['Model'];
        }
        return false;
    }

    public function getExposureMode()
    {
        $modes = [
            0 => 'Auto exposure',
            1 => 'Manual exposure',
            2 => 'Auto bracket',
        ];
        if (isset($this->exif['ExposureMode']) && isset($modes[$this->exif['ExposureMode']])) {
            return $modes[$this->exif['ExposureMode']];
        }
        return false;
    }

    public function getExposureProgram()
    {
        $programs = [
            1 => 'Manual',
            2 => 'Normal program',
            3 => 'Aperture priority',
            4 => 'Shutter priority',
            5 => 'Creative program',
            6 => 'Action program',
            7 => 'Portrait mode',
            8 => 'Landscape mode',
        ];
        if (isset($this->exif['ExposureProgram']) && isset($programs[$this->exif['ExposureProgram']])) {
            return $programs[$this->exif['ExposureProgram']];
        }
        return false;
    }

    // maybe just Canon, idk
    public function getLens()
    {
        if (isset($this->exif['UndefinedTag:0xA434'])) {
            return $this->exif['UndefinedTag:0xA434'];
        }
        return false;
    }

    public function getFormattedShutterSpeed($value)
    {
        if ($value == 0) {
            return false;
        }

        if ($value > 1) {
            return $value . 'sec';
        }

        $value = 1 / $value;

        if ($value > 1000) {
            $value = round($value/50) * 50;
        } elseif ($value > 100) {
            $value = round($value/10)*10;
        } else {
            $value = round($value/5)*5;
        }
        return '1/' . $value . ' sec';
    }

    public function getShutterSpeed()
    {
        if (isset($this->exif['ExposureTime'])) {

            if (strpos($this->exif['ExposureTime'], '/') !== false) {
                $parts = explode('/', $this->exif['ExposureTime']);
                if (count($parts) == 2 && $parts[1] > 0) {
                    return $parts[0] / $parts[1];
                }
            } else {
                return intval(trim(str_replace('sec','', $this->exif['ExposureTime'])));
            }
        }

        if (isset($this->exif['ShutterSpeedValue'])) {
            $parts = explode('/', $this->exif['ShutterSpeedValue']);
            if (count($parts) == 2 && $parts[1] > 0) {
                return pow(2, (abs($parts[0])*-1) / $parts[1]);
            }
        }

        return false;
    }

    public function getFormattedAperture($fnum)
    {
        if (!$fnum) {
            return false;
        }
        $fnum = strtolower($fnum);
        if (substr($fnum, 0, 1) != 'f') {
            $fnum = 'f/' . $fnum;
        }
        return $fnum;
    }

    public function getAperture()
    {
        if (isset($this->exif['FNumber'])) {
            $parts = explode('/', $this->exif['FNumber']);
            if (count($parts) == 2 && $parts[1] > 0) {
                return round($parts[0] / $parts[1], 1);
            }
        }

        if (isset($this->exif['COMPUTED']) && isset($this->exif['COMPUTED']['ApertureFNumber'])) {
            return $this->exif['COMPUTED']['ApertureFNumber'];
        }

        if (isset($this->exif['ApertureValue'])) {
            $parts = explode('/', $this->exif['ApertureValue']);
            if (count($parts) == 2 && $parts[1] > 0) {
                return round(sqrt(pow(2, $parts[0] / $parts[1], 1)), 1);
            }
        }

        return false;
    }

    public function getIso()
    {
        if (isset($this->exif['ISOSpeedRatings'])) {
            return $this->exif['ISOSpeedRatings'];
        }
        return false;
    }

    public function getFocalLength()
    {
        if (isset($this->exif['FocalLength'])) {

            if (strpos($this->exif['FocalLength'], '/') !== false) {
                $parts = explode('/', $this->exif['FocalLength']);
                if (count($parts) == 2 && $parts[1] > 0) {
                    return round($parts[0]/$parts[1]);
                }
            }

            return $this->exif['FocalLength'];
        }
        return false;
    }

    public function getFormattedFocalLength($length)
    {
        if (!$length) {
            return false;
        }

        return $length . ' mm';
    }

    public function getRawIptcData()
    {
        if ($this->iptc) {
            return $this->iptc;
        }

        return false;
    }

    public function getFileSize()
    {
        if (isset($this->exif['FileSize'])) {
            return $this->exif['FileSize'];
        }
        return false;
    }

    public function getWidth()
    {
        if (isset($this->exif['ImageWidth'])) {
            return $this->exif['ImageWidth'];
        }
        if (isset($this->exif['COMPUTED']) && isset($this->exif['COMPUTED']['Width'])) {
            return $this->exif['COMPUTED']['Width'];
        }
        return false;
    }

    public function getHeight()
    {
        if (isset($this->exif['ImageLength'])) {
            return $this->exif['ImageLength'];
        }
        if (isset($this->exif['COMPUTED']) && isset($this->exif['COMPUTED']['Height'])) {
            return $this->exif['COMPUTED']['Height'];
        }
        return false;
    }

    public function getDateTaken()
    {
        if (isset($this->exif['DateTimeOriginal'])) {
            return strtotime($this->exif['DateTimeOriginal']);
        }
        return false;
    }

    public function getDateFile()
    {
        $date1 = 0;
        if (isset($this->exif['FileDateTime']) && $this->exif['FileDateTime'] > 0) {
            $date1 = $this->exif['FileDateTime'];

        }
        $date2 = 0;
        if (isset($this->exif['DateTime'])) {
            $date2 = strtotime($this->exif['DateTime']);
        }

        $date = max($date1, $date2);

        if ($date > 0) {
            return $date;
        }

        return false;
    }

    function getGpsData()
    {
        if (isset($this->exif['GPSLongitude']) && isset($this->exif['GPSLatitude'])) {
            $lon = $this->getGps($this->exif['GPSLongitude'], $this->exif['GPSLongitudeRef']);
            $lat = $this->getGps($this->exif['GPSLatitude'], $this->exif['GPSLatitudeRef']);
            return [$lat, $lon];
        }

        return false;
    }

    protected function getGps($exifCoord, $hemi) {

        $degrees = count($exifCoord) > 0 ? $this->gps2Num($exifCoord[0]) : 0;
        $minutes = count($exifCoord) > 1 ? $this->gps2Num($exifCoord[1]) : 0;
        $seconds = count($exifCoord) > 2 ? $this->gps2Num($exifCoord[2]) : 0;

        $flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;

        return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
    }

    protected function gps2Num($coordPart) {

        $parts = explode('/', $coordPart);

        if (count($parts) <= 0)
            return 0;

        if (count($parts) == 1)
            return $parts[0];

        return floatval($parts[0]) / floatval($parts[1]);
    }
}
