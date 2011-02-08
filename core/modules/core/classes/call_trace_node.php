<?php namespace nmvc\core;

class CallTraceNode extends FactoryConstructor {
    public $call_signature = null;
    public $call_time = 0;
    public $return_time = 0;
    public $subcalls = array();

    public function __construct($properties) {
        parent::__construct($properties);
    }
}