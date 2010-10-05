<?php namespace nmvc\core;

abstract class SessionDataModel_app_overrideable extends \nmvc\AppModel {
    public $session_key = array('core\BinaryType', 32);
    public $session_data = array('core\BinaryType');
    public $last_store_attempt = array('core\TimestampType');

    protected function beforeStore($is_linked) {
        parent::beforeStore($is_linked);
        $this->last_store_attempt = time();
    }
}
