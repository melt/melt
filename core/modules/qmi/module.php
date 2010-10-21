<?php namespace nmvc\qmi;

class QmiModule extends \nmvc\CoreModule {

    public static function beforeRequestProcess() {
        ModelInterface::_interface_callback();
    }

}


