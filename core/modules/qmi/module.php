<?php namespace nmvc\qmi;

class QmiModule extends \nmvc\CoreModule {
    public static function beforeRequestProcess() {
        ModelInterface::_checkSubmit();
        // Auto include required scripts.
        \nmvc\View::render("/qmi/include_mutate", null, false, true);
    }
}
