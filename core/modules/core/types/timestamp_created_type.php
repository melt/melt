<?php

namespace nanomvc\core;

class CreatedTimestampType extends TimestampType {
    public function getInterface($name, $label) {
        return false;
    }
    public function readInterface($name) {
        if ($this->value == null)
            $this->value = time();
    }
}

