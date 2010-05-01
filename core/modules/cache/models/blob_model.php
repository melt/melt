<?php namespace nanomvc\cache;

/**
 * Blobs are stored in this separate model and referenced
 * so the data is only fetched when neccessary (when not cached locally).
 */
class BlobModel extends \nanomvc\Model {
    public $dta = "cache\BlobType";
    public $tag = "cache\Str8Type";
    public $ext = "cache\Str8Type";
}

