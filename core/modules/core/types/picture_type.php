<?php namespace melt\core;

class PictureType extends \melt\cache\BlobPointerType {
    public $thumb_width = 35;
    public $thumb_height = 35;
    public $image_upload_status = null;
    private static $ready = false;

    private static function initialize() {
        $gd_info = gd_info();
        define('GD_SUPPORTS_JPG', @$gd_info['JPG Support'] === true || @$gd_info['JPEG Support'] === true);
        define('GD_SUPPORTS_PNG', @$gd_info['PNG Support'] === true);
        define('GD_SUPPORTS_GIF', @$gd_info['GIF Create Support'] === true && @$gd_info['GIF Read Support'] === true);
        self::$ready = true;
    }

    /**
     * Returns the image upload status.
     * loaded = Image was just successfully uploaded or previously uploaded.
     * fail = The upload failed just now. Invalid format.
     * clear = Image was cleared or previously cleared.
     * @return string Status of image upload. One of either: "loaded", "fail" or "clear".
     */
    public function getImageUploadStatus() {
        if ($this->image_upload_status === null)
            $this->image_upload_status = $this->value > 0? "loaded": "clear";
        return $this->image_upload_status;
    }

    public function readInterface($name) {
        if (!self::$ready)
            self::initialize();
        if (isset($_FILES[$name]) && intval($_FILES[$name]['size']) > 0) {
            $path = $_FILES[$name]['tmp_name'];
            // Read image data and import.
            $data = file_get_contents($path);
            $image_ext = $this->imageDetect($data);
            if (($image_ext == false)
            || ($image_ext == "gif" && !GD_SUPPORTS_GIF)
            || ($image_ext == "jpg" && !GD_SUPPORTS_JPG)
            || ($image_ext == "png" && !GD_SUPPORTS_PNG)) {
                $this->image_upload_status = "fail";
            } else {
                $this->setBinaryData($data, ".$image_ext");
                $this->image_upload_status = "loaded";
            }
        } else if (isset($_POST[$name . '_rem']) && $_POST[$name . '_rem'] == 'delete') {
            // Removing image.
            $this->setBinaryData(null);
            $this->image_upload_status = "clear";
        }
    }

    public function getInterface($name) {
        // Returns the status and a file upload control.
        $picture_url = $this->getUrl();
        if ($picture_url !== null) {
            $thumburl = $this->getUrl($this->thumb_width, $this->thumb_height);
            $remname = $name."_rem";
            $status = "<br /><a target=\"_blank\" href=\"$picture_url\">" . __("Current image:")
                . " <img alt=\"" . __("Current image") . "\" src=\"$thumburl\" /></a><input title=\""
                . __("Check this box to remove the image.") . "\" type=\"checkbox\" name=\"$remname\" value=\"delete\" /> "
                . __("Remove existing image.") . "<br /><br />" . __("Replace image:");
        } else
            $status = '<br />' . __('No uploaded image. Upload new one:');
        $supported = $this->getSupportedFormats();
        return "<div class=\"picture_type_interface\">" . $status . "<br /><input type=\"file\" name=\"$name\" id=\"$name\" /><br /><small>$supported</small></div>";
    }

    public function __toString() {
        $purl = $this->getUrl();
        if ($purl !== false) {
            $thumburl = $this->getUrl($this->thumb_width, $this->thumb_height);
            return "<a target=\"_blank\" href=\"$purl\"><img alt=\"" . __("Current image") . "\" src=\"$thumburl\" /></a>";
        } else
            return "<i>" . __("No image uploaded.") . "</i>";
    }

    /**
    * Returns a string of text describing what picture formats are supported.
    * @return String "The x,y and z formats are supported or No image formats are supported."
    */
    public function getSupportedFormats() {
        if (!self::$ready)
            self::initialize();
        $sup = array();
        if (GD_SUPPORTS_JPG)
            array_push($sup, "JPG");
        if (GD_SUPPORTS_PNG)
            array_push($sup, "PNG");
        if (GD_SUPPORTS_GIF)
            array_push($sup, "GIF");
        switch (count($sup)) {
        case 0:
            return __("No picture formats are supported!");
        case 1:
            return __("Only %s pictures is supported.", $sup[0]);
        case 2:
            return __("Picture formats %s and %s are supported.", $sup[0], $sup[1]);
        case 3:
            return __("Picture formats %s, %s and %s are supported.", $sup[0], $sup[1], $sup[2]);
        }
    }

    /**
     * Like setBinaryData but verifies the data and determines the extention.
     * If the picture type can not be recognized (incorrect data),
     * the set will be canceled and FALSE returned.
     * @param string $data Binary picture data.
     * @return boolean TRUE if data was NULL or recognized,
     * FALSE if data was not recognized.
     */
    public function setPictureData($data = null) {
        if ($data !== null) {
            $extention = self::imageDetect($data);
            if ($extention == null)
                return false;
            parent::setBinaryData($data, "." . $extention);
            return true;
        }
        parent::setBinaryData(null, null);
        return $data === null;
    }

    /**
     * Returns a URL to a thumbnail of this picture, or a URL to the
     * full picture if no picture size limits is specified. Will return
     * an absolute file system path to the picture instead if $local_path
     * is true.
     * @param integer $max_width
     * @param integer $max_height
     * @param string $file_name
     * @param string $local_path
     * @return string
     */
    public function getUrl($max_width = 0, $max_height = 0, $file_name = null, $local_path = false) {
        // Cannot return image if not set.
        if ($this->value <= 0)
            return null;
        // ! is not allowed in filename as it separates thumbnail dimensions from filenames.
        $file_name = str_replace("!", "", $file_name);
        // Not thumbnail and just a normal link?
        if ($max_width <= 0 && $max_height <= 0)
            return $this->getFileCacheLink($file_name, $local_path);
        // Get the thumbnail path.
        if ($max_width < 0 || !is_integer($max_width))
            $max_width == 0;
        if ($max_height < 0 || !is_integer($max_height))
            $max_height == 0;
        $file_name = $max_width . "x" . $max_height . "!" . $file_name;
        $thumb_path = $this->getCachePath($file_name, ".png");
        // Generate thumbnail if it doesn't exist.
        if (!is_file($thumb_path)) {
            // Get blob from database.
            $blob_model = \melt\cache\BlobModel::selectByID($this->value);
            if ($blob_model === null)
                return null;
            $data = $blob_model->dta;
            $img = @imagecreatefromstring($data);
            if (!$img)
                return null;
            // Calculate sizing.
            $img_width = imagesx($img);
            $img_height = imagesy($img);
            if ($max_width == 0)
                $max_width = $img_width;
            if ($max_height == 0)
                $max_height = $img_height;
            // Choose image scaling dimension.
            $thumb_wh = $max_width / $max_height;
            $img_wh = ($img_width / $img_height);
            if ($img_wh <= $thumb_wh) {
                $new_width = round(($max_height / $img_height) * $img_width);
                $new_height = $max_height;
                $offset_x = floor($max_width / 2) - floor($new_width / 2);
                $offset_y = 0;
            } else {
                $new_width = $max_width;
                $new_height = round(($max_width / $img_width) * $img_height);
                $offset_x = 0;
                $offset_y = floor($max_height / 2) - floor($new_height / 2);
            }
            // Create image with correct dimensions, copysample over and save.
            assert($thumb = imagecreatetruecolor($new_width, $new_height));
            imagealphablending($thumb, false); // No blending, just copy pixels.
            assert(imagecopyresampled($thumb, $img, 0, 0, 0, 0, $new_width, $new_height, $img_width, $img_height));
            imagesavealpha($thumb, true); // Save with alpha channel.
            assert(imagepng($thumb, $thumb_path, 9));
        }
        if ($local_path)
            return $thumb_path;
        // Convert local filesystem path to url.
        $path = substr($thumb_path, strlen(APP_DIR));
        return url($path);
    }

    /** Detects an image format by reading header signature. */
    private function imageDetect($data) {
        $header = substr($data, 0, 3);
        $formats = array("\x89\x50\x4E" => "png",
                         "\x47\x49\x46" => "gif",
                         "\xff\xd8\xff" => "jpg");
        return isset($formats[$header])? $formats[$header]: false;
    }
}

