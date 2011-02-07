<?php namespace nmvc\core;

class PropertyManager {
    private $read_only_storage = array();
    protected $read_only_properties = array();

    public function __construct(array $properties = array()) {
        foreach ($properties as $property => $value)
            $this->$property = $value;
        foreach ($this->read_only_properties as $property) {
            if (\property_exists($this, $property)) {
                $this->read_only_storage[$property] = $this->$property;
                unset($this->read_only_storage[$property]);
            } else {
                $this->read_only_storage[$property] = null;
            }
        }
    }

    public function __get($name) {
        if (\array_key_exists($this->read_only_storage, $name))
            return $this->read_only_storage[$name];
        else
            return $this->$name;
    }

    public function  __set($name,  $value) {
        if (\array_key_exists($this->read_only_storage, $name))
            \trigger_error("Trying to write read only property!", \E_USER_ERROR);
        $this->$name = $value;
    }
}