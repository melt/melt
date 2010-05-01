<?php namespace nanomvc\tinymce;

/**
 * tinyMCE wysiwyg with HTML Purifyer and spelling attached.
 * You need the html_purifyer module for html injection protection to work!
 * If you use the jquery module, the tinyMCE jquery version will be used instead.
 * @see http://tinymce.moxiecode.com/
 * @see http://htmlpurifier.org/live/configdoc/plain.html
 */
class WysiwygType extends \nanomvc\Type {
    // The Tiny Mce class of initialization/attach configuration to use.
    public $config_class = "/tinymce/config_simple";

    // Controls HTMLPurifyer: Disables HTMLPurifyer (WARNING: NOT RECOMENDED)
    public $disable_purify = "false";
    // Controls HTMLPurifyer: Allows embedding objects with some additional safety.
    public $allow_objects = "false";
    // Controls HTMLPurifyer: Allows the "embed" tag. (NOT W3C COMPATIBLE - but increases browser interoperability)
    public $allow_embed = "false";
    // Controls HTMLPurifyer: Elements that are allowed. Can not override other settings.
    public $allowed_elements = null;
    // Controls HTMLPurifyer: Elements that are forbidden.
    public $forbidden_elements = null;
    //  Controls HTMLPurifyer: Disables html attributes. This property has a special syntax.
    //  See http://htmlpurifier.org/live/configdoc/plain.html#HTML.ForbiddenAttributes
    public $forbidden_attributes = "";
    // Controls HTMLPurifyer: Indicates whether or not the user input is trusted.
    // If the input is trusted, a more expansive set of allowed tags and attributes will be used.
    public $trusted_input = "false";
    // Prevents HTMLPurifyer from escaping <object> tags.
    public $object_hack = "false";

    public function getSQLType() {
        return "mediumtext";
    }

    public function getSQLValue() {
        return strfy($this->value);
    }

    public function getInterface($name, $label) {
        static $initialized = false;
        if (!$initialized) {
            // If not using jquery, then load the standard module.
            $version = \nanomvc\core\module_loaded("jquery")? "jquery": "standard";
            \nanomvc\View::render("/tinymce/include_$version", null, false, true);
            $initialized = true;
        }
        $textarea_id = "i" . \nanomvc\string\random_hex_str(8);
        // Call the tiny_mce_init_$STYLE intitalizer element that
        // is modified by the user to match this site.
        $controller = new \nanomvc\Controller();
        $controller->textarea_id = $textarea_id;
        $controller->config_class = $this->config_class;
        \nanomvc\View::render("/tinymce/tiny_mce_init", $controller, false);
        return "$label<textarea rows=\"0\" cols=\"0\" id=\"$textarea_id\" name=\"" . $name . "\">" . escape($this->value) . "</textarea>";
    }

    private static $objectElements;
    public static function objectHack($matches) {
        $replaceStr = api_string::random_hex_str(16);
        self::$objectElements[$replaceStr] = $matches[0];
        return $replaceStr;
    }

    public function readInterface($name) {
        $this->value = @$_POST[$name];
        if ($this->disable_purify == "true" || !\nanomvc\core\module_loaded("html_purifyer"))
            return;
        // Purify incomming data. Prevents XSS and crap code that breaks layout.
        // It can however do this with the html_purifyer module loaded.
        $purifyer = new \HTMLPurifier();
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.TidyLevel', 'heavy' );
        $config->set('HTML.Doctype', 'XHTML 1.1' );
        $cache_path = dirname(__DIR__) . '/cache/HTMLPurifier';
        if (!file_exists($cache_path))
            mkdir($cache_path, 0770, true);
        $config->set('Cache.SerializerPath', $cache_path);
        if ($this->allowed_elements != null)
            $config->set('HTML.ForbiddenElements', $this->allowed_elements);
        if ($this->forbidden_elements != null)
            $config->set('HTML.ForbiddenElements', $this->forbidden_elements);
        if ($this->forbidden_attributes != null)
            $config->set('HTML.ForbiddenAttributes', $this->forbidden_attributes);
        $config->set('HTML.SafeObject', $this->allow_objects == "true");
        $config->set('HTML.SafeEmbed', $this->allow_embed == "true");
        $config->set('HTML.Trusted', $this->trusted_input == "true");
        if ($this->object_hack == "true") {
            self::$objectElements = array();
            $this->value = preg_replace_callback("#<object.*?</object>#si", array("TinyMceWysiwygType", "objectHack"), $this->value);
        }
        $this->value = $purifyer->purify($this->value, $config);
        if ($this->object_hack == "true")
            foreach (self::$objectElements as $replaceStr => $element)
                $this->value = str_replace($replaceStr, $element, $this->value);
    }

    public function view() {
        return strval($this->value);
    }
}


?>