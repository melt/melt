<?php namespace nmvc\qmi;

class ActionsController extends \nmvc\Controller {
    /**
     * Callback for qmi actions.
     * @see get_action_link
     */
    function set($data) {
        $data = \nmvc\string\simple_decrypt($data);
        if ($data === false)
            \nmvc\request\show_404();
        list($id, $model_name, $action, $url) = unserialize($data);
        $instance = call_user_func(array($model_name, "selectByID"), $id);
        if ($instance === null)
            \nmvc\request\show_404();
        switch ($action) {
        case "delete":
            $instance->unlink();
            break;
        case "copy":
            clone $instance;
            $instance->store();
            $url = str_replace("{id}", $instance->getID(), $url);
            break;
        default:
            throw new \Exception("Invalid argument passed to get_action_link. Unknown action: '$action'");
        }
        if ($url === null)
            \nmvc\request\go_back();
        else
            \nmvc\request\redirect($url);
    }
}