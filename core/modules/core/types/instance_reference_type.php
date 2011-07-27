<?php namespace melt\core;

/**
 * A non garbage collected instance reference.
 * The reference is stored and read like array(class, id). It can be set
 * to an instance and will then be auto-converted to a reference.
 * ->getInstance() can be used to resolve the instance again. If the reference
 * is no longer valid it could return null even though the internal value
 * is not array(null, 0).
 */
class InstanceReferenceType extends \melt\AppType {
    protected $value = array(null, 0);

    public function get() {
        return $this->value;
    }

    public function getInstance() {
        if (!\is_array($this->value) || \count($this->value) != 2)
            return null;
        list($target_model, $id) = $this->value;
        if (!\is_subclass_of($target_model, 'melt\Model'))
            return null;
        $id = \intval($id);
        if ($id <= 0)
            return null;
        $this->value = array($target_model, $id);
        return $target_model::selectByID($id);
    }

    public function set($value) {
        if (\is_array($value)) {
            $this->value = $value;
            if ($this->getInstance() === null)
                $this->value = array(null, 0);
        } else if ($value === null) {
            $this->value = array(null, 0);
        } else if ($value instanceof \melt\Model) {
            $this->value = array(\get_class($value), $value->getID());
        } else
            \trigger_error("Attempted to set UniversialReferenceType to non Model instance.", \E_USER_ERROR);
    }

    public function setSQLValue($value) {
        $this->set(\unserialize($value));
    }

    public function getSQLType() {
        return "TINYTEXT";
    }

    public function getSQLValue() {
        return \melt\db\strfy(serialize($this->value));
    }
    
    public function getInterface($name) { }

    public function readInterface($name) { }

}
