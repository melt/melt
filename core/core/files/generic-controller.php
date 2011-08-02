<?php namespace melt;

/**
 * <Description>
 * @author <Author>
 */
class __template_class_nameController extends AppController {   
    public function beforeFilter($action_name, $arguments) {
        parent::beforeFilter($action_name, $arguments);
        // HINT: Code here runs before the action.
    }
    
    public function beforeRender($action_name, $arguments) {
        parent::beforeRender($action_name, $arguments);
        // HINT: Code here runs after the action and before the view is
        // rendered (if actions specifies a view to be rendered).
    }
    
    public function afterRender($action_name, $arguments) {
        parent::afterRender($action_name, $arguments);
        // HINT: Code here runs after the view was rendered.
    }
    
    public function view($id) {
        // HINT: Example action.
    }
}
