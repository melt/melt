<?php namespace nmvc\core;

class UploadType extends \nmvc\cache\BlobPointerType {
    public $allowed_extentions = ".zip|.gz|.tar|.rar|.7z|.png|.gif|.jpg|.jpeg";
    public $upload_status = null;
    /**
     * Returns the file upload status.
     * loaded = File was just successfully uploaded or previously uploaded.
     * fail = File upload failed. Invalid type.
     * clear = File was cleared or previously cleared.
     * @return string Status of image upload. One of either: "loaded" or "clear".
     */
    public function getUploadStatus() {
        if ($this->upload_status === null)
            $this->upload_status = $this->value > 0? "loaded": "clear";
        return $this->upload_status;
    }

    public function readInterface($name) {
        static $allowed_extentions = null;
        if ($allowed_extentions === null)
            $allowed_extentions = explode("|", $this->allowed_extentions);
        if (isset($_FILES[$name]) && intval($_FILES[$name]['size']) > 0) {
            $path = $_FILES[$name]['tmp_name'];
            // Extract and verify remote extention.
            $remote_name = $_FILES[$name]['name'];
            $is_ext = null;
            foreach ($allowed_extentions as $extention) {
                if (\nmvc\string\ends_with($remote_name, $extention)) {
                    $is_ext = $extention;
                    break;
                }
            }
            if ($is_ext === null) {
                $this->upload_status = "fail";
                return;
            }
            // Read data and import.
            $data = file_get_contents($path);
            $this->setBinaryData($data, $is_ext);
            $this->upload_status = "loaded";
        } else if (isset($_POST[$name . '_rem']) && $_POST[$name . '_rem'] == 'delete') {
            // Removing data.
            $this->setBinaryData(null);
            $this->upload_status = "clear";
        }
    }

    public function getInterface($name) {
        // Returns the status and a file upload control.
        $file_url = $this->getFileCacheLink();
        if ($file_url !== null) {
            $remname = $name."_rem";
            $status = "<br /><a target=\"_blank\" href=\"$file_url\">" . __("Download current file.") . "</a>"
                . "<br /><input title=\""
                . __("Check this box to remove the file.") . "\" type=\"checkbox\" name=\"$remname\" id=\"$remname\" value=\"delete\" /> "
                . __("Remove existing file.") . "<br /><br />" . __("Replace file:");
        } else
            $status = '<br />' . __('No uploaded file. Upload new one:');
        return $status."<br /><input type=\"file\" name=\"$name\" id=\"$name\" />";
    }

    public function __toString() {
        $file_url = $this->getFileCacheLink();
        if ($file_url !== null) {
            return "<a target=\"_blank\" href=\"$file_url\">$file_url</a>";
        } else
            return "<i>" . __("No file uploaded.") . "</i>";
    }
}

