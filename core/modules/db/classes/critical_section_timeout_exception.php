<?php namespace nmvc\db;

class CriticalSectionTimeoutException extends \Exception {

    public function __construct() {
        parent::__construct("Request to enter critical section timed out!", \E_USER_ERROR);
    }

}