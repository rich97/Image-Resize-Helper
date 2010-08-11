<?php
/**
 * Rewrite of the Image Resize Helper in the bakery (http://bakery.cakephp.org/articles/view/image-resize-helper)
 * made to be a bit more flexible so more resize methods can be added at a later date.
 * Currently only supports maxDimension, which, as the name suggests resizes if it's dimensions are greater than $nexX or $newY.
 * NOTE: sort out variable casing. Some local variables use under_scores others use camelCase.
 */
class ImageHelper extends Helper {

    public $helpers = array('Html');

    public $cacheName = 'resized';
    public $baseDir = null;
    public $subDir = null;

    private $__imageFile = null;
    private $__cacheFile = null;
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

    public function maxDimension($file, $newW = 0, $newH = 0, $htmlAttributes = array(), $maintainAspect = true) {
        $this->__imageFile = $file;
        $this->__cacheFile = 't0' .
                             '_l0' .
                             '_r' . $newW .
                             '_b' . $newH .
                             '_' . basename($file);

        if (is_string($cached = $this->__getCached())) {
            return $this->Html->image($cached, $htmlAttributes);
        }

        $full_path = $this->__imageFolder . $this->__imageFile;
        if ($p = @getimagesize($full_path)) {
            list ($width, $height) = $p;
            if ($width > $newW || $height > $newH) {
                if ($maintainAspect) {
                    list($newW, $newH) = $this->__getAspectResize($newW, $newH, $width, $height);
                } else {
                    $newW = ($newW === 0) ? $width : $newW;
                    $newH = ($newH === 0) ? $height : $newH;
                }
            }
        }

        if (($return = $this->__createResized($newW, $newH)) !== false) {
            return $this->Html->image($return, $htmlAttributes);
        } else {
            return '<div class="error">Unable to resize image.</div>';
        }
    }

    public function crop($file, $left = 0, $right = 0, $top = 0, $bottom = 0, $htmlAttributes = array()) {
        $this->__imageFile = $file;
        $this->__cacheFile = 't' . $top .
                             '_l' . $left .
                             '_r' . $right .
                             '_b' . $bottom .
                             '_' . basename($file);

        if (is_string($cached = $this->__getCached())) {
            return $this->Html->image($cached, $htmlAttributes);
        }

        $width = $right - $left;
        $height = $bottom - $top;

        if (($return = $this->__createCropped($left, $top, $width, $height)) !== false) {
            return $this->Html->image($return, $htmlAttributes);
        } else {
            return '<div class="error">Unable to crop image.</div>';
        }
    }

    private function __createResized($newW, $newH) {
        $srcImg = $this->__imageFolder . $this->__imageFile;
        $copyTo = $this->__cacheFolder . $this->__cacheFile;

        list($width, $height) = @getimagesize($srcImg);
        $create = $this->__createImage(
            0, 0, 0, 0,
            $newW, $newH, $width, $height
        );

        if ($create) {
            return $this->__getCached();
        } else {
            die('Unable to create image "' . $copyTo . '" from "' . $srcImg . '".');
        }
    }

    public function __createCropped($left, $top, $width, $height) {
        $srcImg = $this->__imageFolder . $this->__imageFile;
        $copyTo = $this->__cacheFolder . $this->__cacheFile;

        $create = $this->__createImage(
            0, 0, $left, $top,
            $width, $height, $width, $height
        );

        if ($create) {
            return $this->__getCached();
        } else {
            die('Unable to create image "' . $copyTo . '" from "' . $srcImg . '".');
        }
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
 *
 * NOTE: Move cache retrival to this function
 * and create option for turnung off the cache.
 */
    private function __createImage(
        $dstX = 0, $dstY = 0,
        $srcX = 0, $srcY = 0,
        $dstW = 0, $dstH = 0,
        $srcW = 0, $srcH = 0
    ) {
        $create = false;
        $srcImg = $this->__imageFolder . $this->__imageFile;
        $copyTo = $this->__cacheFolder . $this->__cacheFile;

        if ($p = @getimagesize($srcImg)) {
            $extension = $p[2];
            $resource = call_user_func('imagecreatefrom' . $this->__fileTypes[$extension], $srcImg);
            if ($resource) {
                $resample = false;
                if (function_exists('imagecreatetruecolor') && ($temp = @imagecreatetruecolor($dstW, $dstH))) {
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

/**
 * There is no REAL way I can find to tell if the image has been modified
 * without either using a flag in either a database or a flat file. Without
 * these it's possible (though unlikley) that an old cached image will get
 * served up instead of generating a new one, as this method relies on naming
 * conventions, which are liable to naming conflicts.
 */
    private function __getCached() {
        $image = $this->__imageFolder . $this->__imageFile;
        $cache = $this->__cacheFolder . $this->__cacheFile;

        if (file_exists($cache) && file_exists($image)) {
            if (date("YmdHis", @filemtime($image)) > date("YmdHis", @filemtime($cached))) {
                $rel_dir = DS . IMAGES_URL .  $this->subDir .
                           DS . $this->cacheName  . DS;
                return $rel_dir . basename($this->__cacheFile);
            }
        }

        return false;
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
