<?php
/**
* @desc This type requires CKEditor installed in the webroot /ckeditor/.
*
* Warning: Using this type enables the user to inject javascript so it's a potential XSS vunerability.
*          It should only be passed to authorized and trused users, such as administrators.
*/


class WysiwygType extends Type {
    /**
    *@desc The size of the wysiwyg, either small or full.
    */
    private $initialized = false;

    /**
    *@desc Set to full to use a full toolbar.
    */
    var $toolbar = "Basic";

    /**
    * @desc The ckeditor skin to use.
    */
    var $skin = "office2003";

    private function initialize() {
        // Write initialization stuff to the head section.
        api_application::$_application_controller->layout->enterSection("head");
        echo '<script type="text/javascript" src="' . api_navigation::make_local_url('/ckeditor/ckeditor.js') . '"></script>';
        api_application::$_application_controller->layout->exitSection();
        $this->initialized = true;
    }

    public function getSQLType() {
        return "text";
    }
    public function getSQLValue() {
        return api_database::strfy($this->value);
    }
    public function getInterface($label) {
        $name = $this->name;
        if (!$this->initialized)
            $this->initialize();
        $sms = (strtolower(@$this->toolbar) == "full")? ', full': ', small';
        $myid = "i_" . api_string::random_hex_str(8);
        $language = defined("LANGUAGE_SET")? "language : '" . LANGUAGE_SET . "'": "";
        return "$label
                <textarea id=\"$myid\" name=\"$name\">" .
                    api_html::escape($this->value) .
               "</textarea>" .
               "<script type=\"text/javascript\">CKEDITOR.replace('$myid',
                {
                    toolbar : '" . $this->toolbar . "',
                    skin : '" . $this->skin . "',
                    $language
                });</script>";
    }
    public function readInterface() {
        $this->value = @$_POST[$this->name];
    }

    public function __toString() {
        return strval($this->value);
    }
}

?>
