<?php

namespace nanomvc\cache;

/**
 * Blobs are stored in this separate model and referenced
 * so the data is only fetched when neccessary (when not cached locally).
 */
class BlobModel extends \nanomvc\Model {
    public $data = "cache\Blob";
    public $tag = "cache\Str8";
    public $ext = "cache\Str8";
}

