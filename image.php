<?php
/**
 * Rewrite of the Image Resize Helper in the bakery (http://bakery.cakephp.org/articles/view/image-resize-helper)
 * made to be a bit more flexible so more resize methods can be added at a later date.
 * Currently only supports maxDimension, which, as the name suggests resizes if it's dimensions are greater than $nexX or $newY.
 */
class ImageHelper extends Helper {

    public $helpers = array('Html');

    public $cacheName = 'resized';
    public $baseDir = null;
    public $subDir = null;

    private $__imageFolder = null;
    private $__cacheFolder = null;
    private $__fileTypes = array(
        1 => 'gif',
             'jpeg',
             'png',
             'swf',
             'psd',
             'wbmp'
    );

    public function beforeRender() {
        if (!$this->baseDir) {
            $this->baseDir = IMAGES;
        }

        $folder =& new Folder();
        if ($this->__makeDir($this->baseDir)) {
            $this->__imageFolder = $folder->slashTerm($this->baseDir);
            if ($this->subDir) {
                $folder->slashTerm($this->subDir);
            }
        }

        $this->__cacheFolder = $this->__imageFolder . $this->cacheName . DS;
        $this->__makeDir($this->__cacheFolder);
    }

    public function maxDimension($file, $newX = 0, $newY = 0, $htmlAttributes = array(), $maintainAspect = true) {
        $full_path = $this->__imageFolder . $file;

        if ($p = @getimagesize($full_path)) {
            list ($width, $height) = $p;
            if ($width > $newX || $height > $newY) {
                if ($maintainAspect) {
                    list($newX, $newY) = $this->__getAspectResize($newX, $newY, $width, $height);
                } else {
                    $newX = ($newX === 0) ? $width : $newX;
                    $newY = ($newY === 0) ? $height : $newY;
                }
            }
        }

        if (is_string($c = $this->__getCached($file, $newX, $newY))) {
            return $this->Html->image($c, $htmlAttributes);
        }

        if (($return = $this->__createResized($file, $newX, $newY)) !== false) {
            return $this->Html->image($return, $htmlAttributes);
        } else {
            return '<div class="error">Unable to resize image.</div>';
        }
    }


    private function __createResized($createFrom, $newW, $newH) {
        $srcImg = $this->__imageFolder . basename($createFrom);
        $copyTo = $this->__cacheFolder . $newW . 'x' . $newH . '_' . basename($createFrom);

        list($width, $height) = @getimagesize($srcImg);
        if ($this->__createImage($srcImg, $copyTo, 0, 0, 0, 0, $newW, $newH, $width, $height)) {
            return $this->__getCached($createFrom, $newW, $newH);
        } else {
            die('Unable to create image "' . $copyTo . '" from "' . $srcImg . '".');
        }
    }

    public function __createCropped() {

    }

    private function __getAspectResize($nX, $nY, $cX, $cY) {
        if ($nX == 0) {
            $factor = $nY / $cY;
        }
        elseif ($nY == 0) {
            $factor = $nX / $cX;
        }
        else {
            $factor = min($nX / $cX, $nY / $cY);
        }

        return array(
            floor ($cX * $factor),
            floor ($cY * $factor),
        );
    }

    private function __getCached($file, $targetX, $targetY) {
        $image = $this->__imageFolder . $file;
        $cImage = $this->__cacheFolder . $targetX . 'x' . $targetY . '_' . $file;

        if (file_exists($cImage) && file_exists($image)) {
            list($width, $height) = @getimagesize($cImage);

            if ($width == $targetX  && $height == $targetY) {
                if (@filemtime($cImage) > @filemtime($image)) {
                    $rel_dir = DS . IMAGES_URL .  $this->subDir .
                               DS . $this->cacheName  . DS;
                    $file_name = $width . 'x' . $height . '_' . basename($file);
                    return $rel_dir . $file_name;
                }
            }
        }

        return false;
    }

/**
 * Resize Example
 * $this->__createImage(
 *     $temp, $resource,
 *     0, 0,
 *     0, 0,
 *     $newX, $newY,
 *     $width, $height
 * );
 *
 * Crop Example:
 * $this->__createImage(
 *     $temp, $resource,
 *     0, 0,
 *     1600, 800,
 *     500, 700,
 *     2100-1600, 1500-800
 * );
 */
    private function __createImage(
        $srcImg, $copyTo,
        $dstX = 0, $dstY = 0,
        $srcX = 0, $srcY = 0,
        $dstW = 0, $dstH = 0,
        $srcW = 0, $srcH = 0
    ) {
        $create = false;
        if ($p = @getimagesize($srcImg)) {
            $extension = $p[2];
            $resource = call_user_func('imagecreatefrom' . $this->__fileTypes[$extension], $srcImg);
            if ($resource) {
                $resample = false;
                if (function_exists('imagecreatetruecolor') && ($temp = @imagecreatetruecolor($dstW, $srcX))) {
                    $resample = imagecopyresampled(
                        $temp, $resource,
                        $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH
                    );
                } elseif ($temp = imagecreate($dstW, $dstH)) {
                    $resample = imagecopyresized(
                        $temp, $resource,
                        $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH
                    );
                }

                if ($resample) {
                    $create = call_user_func("image" . $this->__fileTypes[$extension], $temp, $copyTo);
                }

                imagedestroy($temp);
            }

            imagedestroy($resource);
        }

        return $create;
    }

    private function __makeDir($dir) {
        if (!is_writable($dir)) {
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    die($dir . ': Unable to create directory.');
                }
            } else {
                die($dir . ': Cannot write to this directory.');
            }
        }
        return true;
    }

}
