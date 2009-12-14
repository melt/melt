<?php

/**
* @desc The cache handling API namespace.
*/
class api_cache {

    private static function path($tag, $key) {
        $dir = "cache/" . $tag . "/";
        if (!file_exists($dir))
            mkdir($dir, 0777, true);
        return $dir . substr(md5($key), 0, 12) . ".tmp";
    }

    private static function dirpath($tag) {
        return "cache/" . $tag . "/";
    }

    /**
    * @param String $tag The cached resource tag identifier.
    * @param String $key The unique cached resource key identifier.
    * @return The local path for direct access to the cache.
    */
    static function get_cache_path($tag, $key) {
        return api_cache::path($tag, $key);
    }

    /**
    * @param String $tag The cached resource tag identifier.
    * @param String $key The unique cached resource key identifier.
    * @return The cached binary data or null if that data does not exist.
    */
    static function get_cache($tag, $key) {
        $path = api_cache::path($tag, $key);
        if (file_exists($path)) {
            $content = file_get_contents($path);
            if ($content === FALSE)
                throw new Exception("Could not access cached file '$path'.");
            return $content;
        } else {
            return null;
        }
    }

    /**
    * @param String $tag The cached resource tag identifier.
    * @param String $key The unique cached resource key identifier.
    * @param binary $data The data to be stored in this resource.
    */
    static function set_cache($tag, $key, $data) {
        $path = api_cache::path($tag, $key);
        $r = file_put_contents($path, $data);
        if ($r === false)
            throw new Exception("Could not access cached file '$path'.");
    }

    /**
    * @param String $tag The cached resource tag identifier.
    * @param String $key The unique cached resource key identifier.
    * @param String $file The path to a file that will be copied and set as the new resouce.
    */
    static function set_cache_file($tag, $key, $file) {
        $path = api_cache::path($tag, $key);
        api_cache::delete_cache($tag, $key);
        $r = copy($file, $path);
        if ($r === false)
            throw new Exception("Could not access either cached file '$path' or given file '$file'.");
    }

    /**
    * @param String $tag The cached resource tag identifier.
    * @param String $key The unique cached resource key identifier.
    * @return The UNIX time when resource was last modified.
    */
    static function cache_last_modified($tag, $key) {
        $path = api_cache::path($tag, $key);
        if (!file_exists($path))
            return false;
        return filemtime($path);
    }

    /**
    * @desc Attempts to remove all resources with specified tag from cache.
    * @param String $tag The cached resource tag identifier.
    */
    static function clear_cache($tag) {
        $path = api_cache::dirpath($tag);
        api_filesystem::dir_remove($path);
    }

    /**
    * @desc Attempts to remove any resource with specified tag and key from cache.
    * @desc Does not throw exception on access error. (preventing race condition)
    * @param String $tag The cached resource tag identifier.
    * @param String $key The unique cached resource key identifier.
    */
    static function delete_cache($tag, $key) {
        $path = api_cache::path($tag, $key);
        unlink($path);
    }

    /**
    * @param String $tag The cached resource tag identifier.
    * @param String $key The unique cached resource key identifier.
    * @return True if cache exists.
    */
    static function cache_exists($tag, $key) {
        $path = api_cache::path($tag, $key);
        return file_exists($path);
    }

    /**
    * @desc Sends the cached data, finalizes this request. Will fail if data has already been sent during this request.
    * @param String $tag The cached resource tag identifier.
    * @param String $key The unique cached resource key identifier.
    * @param String $mime [Optional] The resource type mime identifer.
    * @param String $filename [Optional] If not null, the resource will be sent as an attachment with this default filename.
    * @return Does not return, will kill this request on completion.
    */
    static function send_cache($tag, $key, $mime = 'application/octet-stream', $filename = null) {
        $path = api_cache::path($tag, $key);
        api_navigation::send_file($path, $mime, $filename);
        exit;
    }

    /**
    * @desc Runs cached data once.
    * @param String $tag The cached resource tag identifier.
    * @param String $key The unique cached resource key identifier.
    */
    static function run_cache($tag, $key) {
        $path = api_cache::path($tag, $key);
        require($path);
    }

}

?>