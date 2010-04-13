<?php namespace nanomvc\url_mapper;

class CallbackController extends \nanomvc\Controller {

    /** Internal callback function that forwards a URL mapped model invoke path. */
    public function _model($model_classname, $id) {
        $model = $model_classname::selectByID($id);
        if ($model === false)
            \nanomvc\request\show_404();
        $invoke_path = $model_classname::getInvokePath($ivp);
        Controller::invoke($invoke_path, false);
    }

}
