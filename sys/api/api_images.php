<?php

/**
* @desc The image handling API namespace.
*/
class api_images {
    private static $ready = false;

    const IMAGETYPE_UNKNOWN = 0;
    const IMAGETYPE_JPG = 1;
    const IMAGETYPE_GIF = 2;
    const IMAGETYPE_PNG = 3;

    /**
    * @desc Detects an image format by reading header signature.
    */
    private static function image_detector($path) {
        $h = fopen($path, 'r');
        if ($h === false)
            throw new Exception("Could not open '$path'.");
        $header = fread($h, 4);
        fclose($h);
        $formats = array("\x89\x50\x4E\x47" => api_images::IMAGETYPE_PNG,
                         "\x47\x49\x46\x38" => api_images::IMAGETYPE_GIF,
                         "\xff\xd8\xff\xe0" => api_images::IMAGETYPE_JPG);
        return isset($formats[$header])? $formats[$header]: api_images::IMAGETYPE_UNKNOWN;
    }

    private static function initialize() {
        $gd_info = gd_info();

        define('gd_supports_jpg',$gd_info['JPG Support'] === true || $gd_info['JPEG Support'] === true);
        define('gd_supports_png',$gd_info['PNG Support'] === true);
        define('gd_supports_gif',$gd_info['GIF Create Support'] === true && $gd_info['GIF Read Support'] === true);

        api_images::$ready = true;
    }

    /**
    * @desc Returns a string of text describing what picture formats are supported.
    * @return String "The x,y and z formats are supported or No image formats are supported."
    */
    public static function get_supported_formats() {
        if (!api_images::$ready)
            api_images::initialize();

        $sup = array();
        if (gd_supports_jpg)
            array_push($sup, "JPG");
        if (gd_supports_png)
            array_push($sup, "PNG");
        if (gd_supports_gif)
            array_push($sup, "GIF");
        switch (count($sup)) {
        case 0:
            return __("No picture formats are supported!");
        case 1:
            return __("Only %s pictures is supported.", $sup[0]);
        case 2:
            return __("The picture formats %s and %s is supported.", $sup[0], $sup[1]);
        case 3:
            return __("The picture formats %s, %s and %s is supported.", $sup[0], $sup[1], $sup[2]);
        }
    }

    /**
    * @desc Imports a picture that has currently been uploaded in a form.
    * @param String $name Name of form component that uploaded picture.
    * @return String A handle to the picture or FALSE if unsupported/unrecognized image type.
    */
    public static function import_uploaded_picture($name) {
        // Upload new file if new file given.
        if (isset($_FILES[$name]['tmp_name'])) {
            $path = $_FILES[$name]['tmp_name'];
            if (is_uploaded_file($path))
                return api_images::import_picture($path);
        }
        return false;
    }

    /**
    * @desc Imports a picture from a path on the local filesystem into picture database.
    * @param String $path Path to image to import.
    * @see get_picture_url() To generate a local url for picture/thumbnail access.
    * @return String A handle to the picture or FALSE if unsupported/unrecognized image type.
    */
    public static function import_picture($path) {
        if (!api_images::$ready)
            api_images::initialize();
        // Generates a new typed handle and imports image to location in cache.
        switch(api_images::image_detector($path)) {
            case api_images::IMAGETYPE_GIF:
                if (!gd_supports_gif)
                    return false;
                $key = 'g' . api_string::random_hex_str(24);
                $outname = api_cache::get_cache_path('img', $key);
                $img = @imagecreatefromgif($path);
                if (!$img) return false;
                if (!imagegif($img, $outname))
                    throw new Exception("GIF image could not be saved/imported.");
                break;
            case api_images::IMAGETYPE_JPG:
                if (!gd_supports_jpg)
                    return false;
                $key = 'j' . api_string::random_hex_str(24);
                $outname = api_cache::get_cache_path('img', $key);
                $img = @imagecreatefromjpeg($path);
                if (!$img) return false;
                if (!imagejpeg($img, $outname))
                    throw new Exception("JPEG image could not be saved/imported.");
                break;
            case api_images::IMAGETYPE_PNG:
                if (!gd_supports_png)
                    return false;
                $key = 'p' . api_string::random_hex_str(24);
                $outname = api_cache::get_cache_path('img', $key);
                $img = @imagecreatefrompng($path);
                if (!$img) return false;
                imagesavealpha($img, true);
                if (!imagepng($img, $outname))
                    throw new Exception("PNG image could not be saved/imported.");
                break;
            default:
                return false;
        }
        return $key;
    }

    /**
    * @desc Deletes a picture from the pictures api.
    * @desc String $pichandle Handle to the picture.
    * @return bool TRUE if picture was removed, FALSE if picture did not exist.
    */
    public static function remove_picture($pichandle) {
        if (api_cache::cache_exists('img', $pichandle)) {
            // Remove all thumbnails.
            api_cache::clear_cache('thumbs/'.$pichandle);
            // Remove the image itself.
            api_cache::delete_cache('img', $pichandle);
            return true;
        } else return false;
    }


    /**
    * @desc Sends the picture that corresponds to this request.
    * @desc Function is expected to be statically mapped.
    * @return void Does not return.
    */
    public static function send_picture() {
        // Different URL format depending on image or thumbnail.
        $parts = explode('/', REQURL);
        if (!isset($parts[1]))
            api_navigation::show_404();
        else if ($parts[1] == 'image' && count($parts) == 3) {
            $tag = 'img';
            $key = $parts[2];
            switch ($key[0]) {
            case 'j':
                $mime = 'image/jpeg';
                break;
            case 'g':
                $mime = 'image/gif';
                break;
            case 'p':
                $mime = 'image/png';
                break;
            default:
                api_navigation::show_404();
            }
        } else if ($parts[1] == 'thumbnail' && count($parts) == 4) {
            $tag = 'thumbs/' . $parts[2];
            $key = $parts[3];
            $mime = 'image/png';
        } else
            api_navigation::show_404();
        if (!api_cache::cache_exists($tag, $key))
            api_navigation::show_404();
        api_cache::send_cache($tag, $key, $mime, null);
    }

    /**
    * @desc Generates a remote URL to picture or thumbnail by the name returned when it was imported.
    * @param String $pichandle Handle to the picture.
    * @param Integer $max_width Optional: Maximum width of thumbnail, set to null for unlimited width.
    * @param Integer $max_height Optional: Maximum height of thumbnail, set to null for unlimited height.
    * @see import_picture() To import pictures.
    * @return String Remote and properly formated URL to thumbnail/picture or FALSE if picture did not exist.
    */
    public static function get_picture_url($pichandle, $max_width = null, $max_height = null) {
        if (!api_images::$ready)
            api_images::initialize();

        if (!api_cache::cache_exists('img', $pichandle))
            return false;

        $url = ($max_width == null && $max_height == null) ?
            '/image/' . $pichandle :
            '/thumbnail/' . api_images::get_thumb($pichandle, $max_width, $max_height);
        return api_navigation::make_local_url($url);
    }

    private static function get_thumb($pichandle, $max_width, $max_height) {
        // Read image from cache.
        $format = $pichandle[0];
        $img = false;
        $path = api_cache::get_cache_path('img', $pichandle);
        if ($format == 'j' && gd_supports_jpg)
            $img = @imagecreatefromjpeg($path);
        else if ($format == 'p' && gd_supports_png)
            $img = @imagecreatefrompng($path);
        else if ($format == 'g' && gd_supports_gif)
            $img = @imagecreatefromgif($path);
        if (!$img) throw new Exception("Unable to read image from cache!");

        // Calculate sizing.
        $img_width = imagesx($img);
        $img_height = imagesy($img);
        if ($max_width == null)
            $max_width = $img_width;
        if ($max_height == null)
            $max_height = $img_height;

        // Get the thumbnail tag and key.
        $tag = 'thumbs/' . $pichandle;
        $key = $max_width . "x" . $max_height;
        if (!api_cache::cache_exists($tag, $key)) {
            // Choose image scaling dimension.
            $ThumbWH = $max_width / $max_height;
            $ImgWH = ($img_width / $img_height);
            if ($ImgWH <= $ThumbWH) {
                $newwidth = round(($max_height / $img_height) * $img_width);
                $newheight = $max_height;
                $offset_x = floor($max_width / 2) - floor($newwidth / 2);
                $offset_y = 0;
            } else {
                $newwidth = $max_width;
                $newheight = round(($max_width / $img_width) * $img_height);
                $offset_x = 0;
                $offset_y = floor($max_height / 2) - floor($newheight / 2);
            }

            // Create image with correct dimensions, copysample over and save.
            $path = api_cache::get_cache_path($tag, $key);
            assert($thumb = @imagecreatetruecolor($newwidth, $newheight));
            imagealphablending($thumb, false); // No blending, just copy pixels.
            assert(imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newwidth, $newheight, $img_width, $img_height));
            imagesavealpha($thumb, true); // Save with alpha channel.
            assert(imagepng($thumb, $path));
        }
        return $pichandle . '/' . $key;
    }
}

?>