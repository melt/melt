<?php

class PictureType extends Type {
    public $thumb_width = 35;
    public $thumb_height = 35;

    public function getSQLType() {
        return "text";
    }
    public function getSQLValue() {
        return api_database::strfy($this->value);
    }
    public function getInterface($label) {
        $name = $this->name;
        // Returns the status and a file upload control.
        $purl = api_images::get_picture_url($this->value);
        if ($purl !== FALSE) {
            $thumburl = api_images::get_picture_url($this->value, $this->thumb_width, $this->thumb_height);
            $remname = $name."_rem";
            $status = "<br /><a target=\"_blank\" href=\"$purl\">" . __("Current image:")
                . " <img alt=\"" . __("Current image") . "\" src=\"$thumburl\" /></a><br /><input title=\""
                . __("Check this box to remove the image.") . "\" type=\"checkbox\" name=\"$remname\" value=\"delete\" /> "
                . __("Remove existing image.") . "<br /><br />" . __("Replace image:");
        } else
            $status = '<br />' . __('No uploaded image. Upload new one:');
        $supported = api_images::get_supported_formats();
        return $status."<br /><input type=\"file\" name=\"$name\" /><br /><small>$supported</small>";
    }
    public function readInterface() {
        $name = $this->name;
        if (isset($_FILES[$name]) && intval($_FILES[$name]['size']) > 0) {
            // Replacing image.
            api_images::remove_picture($this->value);
            $this->value = api_images::import_uploaded_picture($name);
            if ($this->value === false)
                Flash::doFlashRedirect(REQURL, __("Unable to import image, invalid format."));
        } else if (isset($_POST[$name . '_rem']) && $_POST[$name . '_rem'] == 'delete') {
            // Removing image.
            api_images::remove_picture($this->value);
            $this->value = null;
        }
    }
    public function __toString() {
        $purl = api_images::get_picture_url($this->value);
        if ($purl !== false) {
            $thumburl = api_images::get_picture_url($this->value, $this->thumb_width, $this->thumb_height);
            return "<a target=\"_blank\" href=\"$purl\"><img alt=\"" . __("Current image") . "\" src=\"$thumburl\" /></a>";
        } else
            return "<i>" . __("No image uploaded.") . "</i>";
    }
}
?>
