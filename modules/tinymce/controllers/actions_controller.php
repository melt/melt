<?php

namespace nmvc\tinymce;

class ActionsController extends \nmvc\Controller {

    /**
     * Spellchecker: Library provided by TinyMCE.
     */
    function spell_check() {
        $path = dirname(__DIR__) . "/spellchecker/rpc.php";
        \nmvc\request\forward($path);
    }

}