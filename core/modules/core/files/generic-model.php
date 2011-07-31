<?php namespace melt;

/**
 * <Description>
 * @author <Author>
 */
class __template_class_nameModel extends AppModel {
    public $name = array('core\TextType', 64);
    public $weight = array('core\FloatType');
    public $quantity = array('core\IntegerType');
    
    public $when_created = array('core\TimestampType');
    public $when_edited = array('core\TimestampType');
    
    protected function initialize() {
        parent::initialize();
        // HINT: Runs after the model instance is created in memory
        // via "new" keyword.
    }
    
    protected function afterLoad() {
        parent::afterLoad();
        // HINT: Runs after the model instance is loaded from the database.
    }
    
    protected function beforeStore($is_linked) {
        parent::beforeStore($is_linked);
        // HINT: Runs before the model instance is ->store()'d.
        if (!$is_linked)
            $this->when_created = time();
        $this->when_edited = time();
    }
    
    protected function afterStore($was_linked) {
        parent::afterStore($was_linked);
        // HINT: Runs after the model instance is ->store()'d.
    }
}
