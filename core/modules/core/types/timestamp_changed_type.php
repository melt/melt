<?php

namespace nmvc\core;

class ChangedTimestampType extends TimestampType {
    public function getInterface($name) {
        return false;
    }
    public function readInterface($name) {
        $this->value = time();
    }
}

