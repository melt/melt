<?php namespace melt;

/**
 * <Description>
 * @author <Author>
 */
class __template_class_nameType extends AppType {
    public function get() {
        // HINT: Runs when type is read.
        return $this->value;
    }
    
    public function set($value) {
        // HINT: Runs when type is written to.
        $this->value = $value;
    }
    
    public function getSQLValue() {
        // HINT: Runs when melt writes type to database, returns an SQL token.
        return db\strfy($this->value);
    }
    
    public function setSQLValue($value) {
        // HINT: Runs when melt reads type from database.
        $this->value = $value;
    }
    
    public function getSQLType() {
        // HINT: Runs when melt wants to know MySQL declaration.
        return "text";
    }
    
    public function getInterface($name) {
        // HINT: Returns an interface for the type when rendered in browser.
        $value = escape($this->value);
        return "<input type=\"text\" name=\"$name\" id=\"$name\" value=\"$value\" />";
    }
    
    public function readInterface($name) {
        // HINT: Reads interface for type when posted back from browser.
        $this->value = \trim(@$_POST[$name]);
    }
}
