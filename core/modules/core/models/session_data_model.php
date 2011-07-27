<?php namespace melt\core;

abstract class SessionDataModel_app_overrideable extends \melt\AppModel {
    public $session_key = array(INDEXED_UNIQUE, 'core\BinaryType', 32);
    public $session_data = array('core\BinaryType');
    public $last_store_attempt = array(INDEXED, 'core\TimestampType');

    protected function beforeStore($is_linked) {
        parent::beforeStore($is_linked);
        $this->last_store_attempt = time();
    }
}
