<?php namespace nmvc\db;

class ModelField {
    private $name;

    public function __construct($name) {
        $pattern = '#^(<-)*[a-z][a-z0-9_]*(->[a-z][a-z0-9_]*)*$#';
        $name = (string) $name;
        if (!preg_match($pattern, $name))
            trigger_error("Invalid field name: $name Correct field names matches the following regex pattern: $pattern", \E_USER_ERROR);
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }
}