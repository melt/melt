<?php namespace nmvc\cache;

/**
 * Blob type - only designed to be used inside cache module.
 */
class BlobType extends \nmvc\Type {
    public function getSQLType() {
        return "MEDIUMBLOB";
    }

    public function getSQLValue() {
        return strfy($this->value);
    }

    public function view() {
        return "#BLOB_DATA#";
    }

    // Blobs are not designed to have interfaces.
    // They are binary data that can be anything.
    public function getInterface($name) { }

    public function readInterface($name) { }
}
