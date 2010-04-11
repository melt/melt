<?php

namespace nanomvc\cache;

/**
 * Blob type - only designed to be used inside cache module.
 */
class BlobType extends \nanomvc\Type {
    public function getSQLValue() {
        return strfy($this->value);
    }
    
    public function getSQLType() {
        return "MEDIUMBLOB";
    }

    public function __toString() {
        return "#BLOB_DATA#";
    }

    // Blobs are not designed to have interfaces.
    // They are binary data that can be anything.
    public function getInterface($name, $label) { }

    public function readInterface($name) { }
}
