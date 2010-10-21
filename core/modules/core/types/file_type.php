<?php namespace nmvc\core;

class FileType extends \nmvc\AppType {
    public $only_read_path = false;
    public $max_file_size = -1;

    private $remote_name = null;

    public function getRemoteName() {
        return $this->remote_name;
    }

    public function getSQLValue() {
        return \nmvc\db\strfy($this->value);
    }

    public function getSQLType() {
        return "mediumblob";
    }

    public function getInterface($name) {
        return "<input type=\"file\" name=\"$name\" id=\"$name\" />";
    }

    public function readInterface($name) {
        $path = get_uploaded_file($name, $this->remote_name, true);
        if ($path == null)
            $this->value = false;
        else if ($this->max_file_size >= 0 && \filesize($path) > $this->max_file_size)
            $this->value = false;
        else if ($this->only_read_path)
            $this->value = $path;
        else
            $this->value = get_uploaded_file("file", $name, false);
    }
}

