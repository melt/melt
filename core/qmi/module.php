<?php namespace melt\qmi;

class QmiModule extends \melt\CoreModule {
    public static function beforeRequestProcess() {
        ModelInterface::_checkSubmit();
    }
    
    public static function beforeLayoutRender() {
        // Auto include required scripts.
        \melt\View::render("/qmi/include_mutate", null, false, true);
    }    
}
