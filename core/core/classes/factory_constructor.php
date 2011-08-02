<?php namespace melt\core;

class FactoryConstructor {
    public function __construct(array $properties = array()) {
        foreach ($properties as $property => $value)
            $this->$property = $value;
    }
}