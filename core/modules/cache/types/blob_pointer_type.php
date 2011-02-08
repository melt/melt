<?php namespace nmvc\cache;

/**
 * A binary data field that points to the binary data and uses
 * on demand file caching on linking.
 *
 * This is a ~very special~ type that uses special I/O.
 *
 * Be sure to understand how it works before you use it.
 */
class BlobPointerType extends \nmvc\core\PointerType {
    /** Constructs this typed field with this column name. */
    public function __construct($disconnect_reaction = "SET NULL") {
        parent::__construct('cache\BlobModel', $disconnect_reaction);
    }
    
    /*
     * Overloads get/set. This pointer should not be operated on directly.
     * Operating directly on this type would allow a non injective blob pointer
     * setup which might be useful but would require garbage collection.
     * Also non injective pointing can be achived anyway by using a middle
     * model with incomming pointers instead.
     */
    public function set($value) { }

    public function get() {
        if ($this->value > 0 || is_object($this->change_to))
            return "#BlobPointer#";
        else
            return null;
    }

    private $change_to = false;

    /** Returns TRUE if this type has changed since the last syncronization. */
    public final function hasChanged() {
        return $this->change_to !== false;
    }

    public function getSQLValue() {
        // At this point validation has already completed, so it's
        // safe to insert the data blob here.
        if ($this->change_to !== false) {
            if ($this->value > 0) {
                // Delete everything in cache.
                $meta = $this->getMeta();
                if ($meta !== null) {
                    list($tag, $extention) = $meta;
                    $glob = $this->getCachePath("*");
                    $glob = preg_replace("#\.[^\.]*$#", "", $glob);
                    $cache_files = glob($glob);
                    if (is_array($cache_files))
                    foreach ($cache_files as $file_name)
                        unlink($file_name);
                }
                // Need to delete the blob I'm pointing on.
                // As unlink would require loading the blob into memory
                // direct querying will be used instead.
                \nmvc\db\run("DELETE FROM " . \nmvc\db\table('cache\blob') . " WHERE id = " . intval($this->value));
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
        if (is_string($data) && strlen($data) > 0) {
            if (strlen($extention) == 0 || $extention[0] != ".")
                trigger_error("Extention MUST start with dot!", \E_USER_ERROR);
            // Prepare storing the binary data in a new blob model.
            $blob_model = new BlobModel();
            $blob_model->dta = $data;
            $blob_model->tag = \nmvc\string\random_alphanum_str(8);
            $blob_model->ext = $extention;
            $blob_model->one_way_key = \nmvc\string\random_alphanum_str(8);
            $this->change_to = $blob_model;
        } else {
            // Prepare clearing the binary data.
            $this->change_to = null;
        }
    }

    /**
     * Returns the binary data.
     */
    public function getBinaryData() {
        if (is_object($this->change_to))
            return $this->change_to->dta;
        $blob_model = BlobModel::selectByID($this->value);
        if ($blob_model === null)
            return null;
        else
            return $blob_model->dta;
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
        $result = \nmvc\db\query("SELECT tag,ext,one_way_key FROM " . \nmvc\db\table(BlobModel::getTableName()) . " WHERE id = " . intval($this->value));
        $result = \nmvc\db\next_array($result);
        if (!is_array($result))
            return null;
        return array($result[0], $result[1], $result[2]);
    }

    /**
     * Returns a local cache location for the requested filename of this blob.
     */
    protected function getCachePath($req_file_name, $custom_extention = null) {
        static $path = null;
        if ($path === null) {
            $path = APP_DIR . "/static/cache";
            if (!file_exists($path))
                mkdir($path, 0775, true);
        }
        $meta = $this->getMeta();
        if ($meta === null)
            return null;
        list($tag, $extention, $one_way_key) = $meta;
        // Allow custom extentions.
        if ($custom_extention !== null)
            $extention = $custom_extention;
        if ($req_file_name != null && strlen($req_file_name) > 0) {
            $assigned_filename = strtolower($req_file_name);
            $assigned_filename = preg_replace('#\s#', "_", $assigned_filename) . "_";
        } else
            $assigned_filename = "_";
        $one_way_key = substr(sha1($one_way_key . $assigned_filename), 0, 8);
        $ret_path = "$path/$tag" . "_$assigned_filename$one_way_key$extention";
        return $ret_path;
    }

    /**
     * Returns a link to the binary data. Will return
     * an absolute file system path to the file instead if $local_path
     * is true.
     * @param string $file_name The requested file name. If null, a tag will be used instead.
     * @param boolean $local_path
     * @return string The link or NULL if not set.
     */
    public function getFileCacheLink($file_name = null, $local_path = false) {
        $cache_path = $this->getCachePath($file_name);
        if ($cache_path == null)
            return null;
       if (!is_file($cache_path)) {
            // Dump blob to disk.
            $blob_model = BlobModel::selectByID($this->value);
            $cache_dir_path = dirname($cache_path);
            file_put_contents($cache_path, $blob_model->dta);
            /*// Also write tag to disk if it should.
            if ($file_tag_path !== null)
                file_put_contents($file_tag_path, $blob_model->tag);*/
        }
        if ($local_path)
            return $cache_path;
        // Convert local filesystem path to url.
        $path = substr($cache_path, strlen(APP_DIR));
        return url($path);
    }
}
