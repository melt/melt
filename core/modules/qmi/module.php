<?php namespace melt\qmi;

class QmiModule extends \melt\CoreModule {
    public static function beforeRequestProcess() {
        ModelInterface::_checkSubmit();
        // Auto include required scripts.
        \melt\View::render("/qmi/include_mutate", null, false, true);
    }
}
