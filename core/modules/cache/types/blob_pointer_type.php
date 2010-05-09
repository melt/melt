<?php namespace nmvc\cache;

/**
 * A binary data field that points to the binary data and uses
 * on demand file caching on linking.
 *
 * This is a ~very special~ type that uses special I/O.
 *
 * Be sure to understand how it works before you use it.
 */
abstract class BlobPointerType extends \nmvc\Reference {
    // Always points to a blob model.
    const STATIC_TARGET_MODEL = 'cache\blob';
    
    /* Overloads get/set. This reference should not be operated on directly.
     * Operating directly on this type would allow a non injective blob pointer
     * setup which might be useful but would require garbage collection. */
    public function set($value) { }

    public function get() {
        return "#BLOBPTR#";
    }

    private $change_to = false;

    public function getSQLValue() {
        // At this point validation has already completed, so it's
        // safe to insert the data blob here.
        if ($this->change_to !== false) {
            if ($this->value > 0) {
                // Need to delete the blob I'm pointing on.
                // As unlink would require loading the blob into memory
                // direct querying will be used instead.
                \nmvc\db\run("DELETE FROM " . table('cache\blob') . " WHERE id = " . intval($this->value));
            }
            if ($this->change_to === null) {
                // Reset.
                $this->value = 0;
            } else {
                // Store new blob model.
                $blob_model = $this->change_to;
                $blob_model->store();
                $this->value = $blob_model->getID();
            }
        }
        // Returning the ID.
        return parent::getSQLValue();
    }
    /* Theese helper functions should be used instead of get/set. */

    /**
     * Sets the binary data.
     * @param string $data Binary data. Set to null to clear.
     * @param string $extention The extention to use for the data. Apache uses this to determine mime type.
     */
    public function setBinaryData($data = null, $extention = ".bin") {
        if (strlen($extention) == 0 || $extention[0] != ".")
            throw new \Exception("Extention MUST start with dot!");
        if (!is_string($data) || strlen($data) == 0) {
            // Prepare storing the binary data in a new blob model.
            $blob_model = BlobModel::insert();
            $blob_model->dta = $data;
            $blob_model->tag = \nmvc\string\random_alphanum_str(8);
            $blob_model->ext = $extention;
            $this->change_to = $blob_model;
        } else {
            // Prepare clearing the binary data.
            $this->change_to = null;
        }
    }

    /**
     * Returns the metadata for this blob.
     * @return array list(tag, extention)
     */
    public function getMeta() {
        if ($this->value <= 0)
            return null;
        // Returns the tag for the blob I'm pointing to.
        // Uses SQL querying to prevent loading BLOB into memory.
        $result = \nmvc\db\query("SELECT tag,ext FROM " . table('cache\blob') . " WHERE id = " . intval($this->value));
        $res = db\next_array($result);
        if (!is_array($result))
            return null;
        return array($result[0], $result[1]);
    }

    /**
     * Returns a local cache location for the requested filename of this blob.
     */
    protected function getCachePath($file_name, $custom_extention = null) {
        static $path = null;
        if ($path === null) {
            $path = \nmvc\config\APP_DIR . "/static/cache";
            if (!file_exists($path))
                mkdir($path, 0660, true);
        }
        $meta = $this->getMeta();
        if ($meta === null)
            return null;
        list($tag, $extention) = $meta;
        // Allow custom extentions.
        if ($custom_extention !== null)
            $extention = $custom_extention;
        if ($file_name != null && strlen($file_name) > 0) {
            $filename = strtolower($file_name);
            $filename = preg_replace('#\s#', "_", $filename);
            if ($file_name[0] == ".")
                throw new \Exception("File name may not start with dot (.)!");
        } else
            $filename = "_";
        $path = "$path/$tag$file_name$extention";
        return $path;
    }

    /**
     * Returns a link to the binary data.
     * @param string $file_name The requested file name. If null, a tag will be used instead.
     * @return string The link or NULL if not set.
     */
    public function getFileCacheLink($file_name = null) {
        $cache_path = $this->getCachePath($file_name);
        if ($cache_path == null)
            return null;
       if (!is_file($cache_path)) {
            // Dump blob to disk.
            $blob_model = BlobModel::selectByID($this->value);
            file_put_contents($file_path, $blob_model->dta);
            // Also write tag to disk if it should.
            if ($file_tag_path !== null)
                file_put_contents($file_tag_path, $blob_model->tag);
        }
        // Convert local filesystem path to url.
        $path = substr($file_path, strlen(\nmvc\config\APP_DIR));
        return url($path);
    }
}
