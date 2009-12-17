<?php
/**
* @desc This type requires OpenWYSIWYG installed in the webroot /openwysiwyg/.
*
* Warning: Using this type enables the user to inject javascript so it's a potential XSS vunerability.
*          It should only be passed to authorized and trused users, such as administrators.
*/


class WysiwygType extends Type {
    /**
    *@desc The size of the wysiwyg, either small or full.
    */
    private $initialized = false;
    var $size = "small";

    private function initialize() {
        // Write initialization stuff to the head section.
        api_application::$_application_controller->layout->enterSection("head");
        echo '<script type="text/javascript" src="'.api_navigation::make_local_url('/openwysiwyg/scripts/wysiwyg.js').'"></script>';
        echo '<script type="text/javascript">
                var full = new WYSIWYG.Settings();
                full.ImagesDir = "'.api_navigation::make_local_url('/openwysiwyg/images/').'";
                full.PopupsDir = "'.api_navigation::make_local_url('/openwysiwyg/popups/').'";
                full.CSSFile = "'.api_navigation::make_local_url('/openwysiwyg/styles/wysiwyg.css').'";
                full.Width = "100%";
                full.Height = "500px";
                full.addToolbarElement("font", 3, 1);
                full.addToolbarElement("fontsize", 3, 2);
                full.addToolbarElement("headings", 3, 3);

                var small = new WYSIWYG.Settings();
                small.ImagesDir = "'.api_navigation::make_local_url('/openwysiwyg/images/').'";
                small.PopupsDir = "'.api_navigation::make_local_url('/openwysiwyg/popups/').'";
                small.CSSFile = "'.api_navigation::make_local_url('/openwysiwyg/styles/wysiwyg.css').'";
                small.Width = "100%";
                small.Height = "100px";
                small.DefaultStyle = "font-family: Arial; font-size: 12px; background-color: #ffffff;";
                small.Toolbar[0] = new Array("font", "fontsize", "bold", "italic", "underline");
                small.Toolbar[1] = "";
                small.StatusBarEnabled = false;
            </script>';
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
        $sms = (strtolower(@$this->size) == "full")? ', full': ', small';
        $myid = "i_" . api_string::random_hex_str(8);
        return "$label
                <textarea id=\"$myid\" name=\"$name\">" .
                    api_html::escape($this->value) .
               "</textarea>" .
               "<script type=\"text/javascript\">WYSIWYG.attach('$myid'$sms);</script>";
    }
    public function readInterface() {
        $this->value = @$_POST[$this->name];
    }

    public function __toString() {
        return strval($this->value);
    }
}

?>
