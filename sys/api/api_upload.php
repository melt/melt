<?php

/**
*@desc The upload handling API namespace.
*/
class api_upload {

    /**
    * @desc Imports a file from a remote location to the API.
    * @param String $path Local or remote file to import to API.
    * @param String $newname Requested new name of file, set to null for random name.
    * @return String The API filename, will differ from input newname but is guaranteed to have the same extention.
    */
    public static function import_upload($path, $newname = null) {
        if ($newname == null) {
            $fpath = '../upl/' . basename($path);
        } else {
            $ext = acms_file_ext($newname);
            if ($ext != '') $newname = substr($newname, 0, -strlen($ext) - 1);
            $newname .= '_' . strval(mt_rand(10000000, 99999999)) . '.' . $ext;
            $fpath = '../upl/' . $newname;
        }
        assert(copy($path, $fpath));
        return basename($fpath);
    }

    /**
    * @desc Returns a correct remote url to a file in the uploads api.
    * @desc String $file Filename (handle) to the file in the uploads api.
    * @return String Safe URL to the file or FALSE if no souch file exists.
    */
    public static function get_upload_url($file) {
        if ($file == null) return false;
        if (file_exists("../upl/" . $file))
            return CONFIG::$rooturl . "upl/" . $file;
        else
            return false;
    }

    /**
    * @desc Deletes a file from the uplaods api.
    * @desc String $file Filename (handle) to the file in the uploads api.
    * @return bool TRUE if file was removed, FALSE if file did not exist.
    */
    public static function delete_upload($file) {
        if ($file == null) return false;
        if (file_exists("../upl/" . $file))
            assert(unlink("../upl/" . $file));
        else return false;
        return true;
    }
}




?>