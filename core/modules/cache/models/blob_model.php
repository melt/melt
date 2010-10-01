<?php namespace nmvc\cache;

/**
 * Blobs are stored in this separate model and referenced
 * so the data is only fetched when neccessary (when not cached locally).
 */
abstract class BlobModel_app_overrideable extends \nmvc\AppModel {
    // Blob data.
    public $dta = "cache\BlobType";
    // Extention enables apache to determine mime type.
    public $ext = "cache\Str8Type";
    // Makes this instance unique when caching.
    public $tag = "cache\Str8Type";
    // Secret key that prevents reverse lookups.
    public $one_way_key = "cache\Str8Type";
}

