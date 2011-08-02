<?php namespace melt\cache;

/**
 * Blob type - only designed to be used internally.
 */
class BlobType extends \melt\AppType {
    public function getSQLType() {
        return "MEDIUMBLOB";
    }

    public function getSQLValue() {
        return \melt\db\strfy($this->value);
    }

    public function  __toString() {
        return "#BLOB_DATA#";
    }

    // Blobs are not designed to have interfaces.
    // They are binary data that can be anything.
    public function getInterface($name) { }

    public function readInterface($name) { }
}
