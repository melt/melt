<?php

namespace nanomvc\tinymce;

class ActionsController extends \nanomvc\Controller {

    /**
     * Spellchecker: Library provided by TinyMCE.
     */
    function spell_check() {
        $path = dirname(__DIR__) . "/spellchecker/rpc.php";
        \nanomvc\request\forward($path);
    }

}