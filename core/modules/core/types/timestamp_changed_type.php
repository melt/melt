<?php

namespace nanomvc\core;

class ChangedTimestampType extends TimestampType {
    public function getInterface($name, $label) {
        return false;
    }
    public function readInterface($name) {
        $this->value = time();
    }
}

