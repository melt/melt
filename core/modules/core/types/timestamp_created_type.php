<?php

namespace nmvc\core;

class CreatedTimestampType extends TimestampType {
    public function getInterface($name) {
        return false;
    }
    public function readInterface($name) {
        if ($this->value == null)
            $this->value = time();
    }
}

