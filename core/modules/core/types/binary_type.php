<?php namespace nmvc\core;

class BinaryType extends \nmvc\AppType {
    private $varbinary_size = null;

    public function __construct($varbinary_size = null) {
        parent::__construct();
        if ($varbinary_size !== null && (!is_integer($varbinary_size) || $varbinary_size < 0 || $varbinary_size > 65535))
            trigger_error("varbinary_size must be a number between 0 and 65535.", \E_USER_ERROR);
        $this->varbinary_size = $varbinary_size;
    }

    public function getSQLType() {
        return ($this->varbinary_size !== null)? "varbinary(" . $this->varbinary_size . ")": "mediumblob";
    }

    public function getSQLValue() {
        if ($this->varbinary_size !== null)
            $this->value = substr($this->value, 0, $this->varbinary_size);
        return \nmvc\db\strfy($this->value);
    }

    public function get() {
        return $this->value;
    }

    public function set($value) {
        $this->value = $value;
        if ($this->varbinary_size !== null)
            $this->value = \substr($this->value, 0, $this->varbinary_size);
    }

    public function getInterface($name) {
        return null;
    }

    public function readInterface($name) {
        return null;
    }

    public function __toString() {
        return "#BINARY_DATA#";
    }
}
