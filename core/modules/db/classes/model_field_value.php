<?php namespace melt\db;

class ModelFieldValue {
    private $value;

    public function __construct($value) {
        $this->value = $value;
    }

    public function getValue() {
        return $this->value;
    }

    public function __sleep() {
        if ($this->value instanceof \melt\Model)
            $this->value = $this->value->id;
        return array("value");
    }
}