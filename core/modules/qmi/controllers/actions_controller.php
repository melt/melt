<?php namespace nmvc\qmi;

class ActionsController extends \nmvc\AppController {
    /**
     * Callback for qmi actions.
     * @see get_action_link
     */
    function set($data) {
        $data = \nmvc\string\simple_decrypt($data);
        if ($data === false)
            \nmvc\request\show_404();
        list($id, $model_name, $action, $url, $arguments, $uid) = unserialize(gzuncompress($data));
        if ($uid > 0 && $uid !== id(\nmvc\userx\get_user()))
            \nmvc\request\show_xyz(403);
        if ($id > 0) {
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
                if (!method_exists($instance, $action))
                    throw new \Exception("Invalid argument passed to get_action_link. Unknown action: '$action'");
                \call_user_func_array(array($instance, $action), $arguments);
            }
        } else {
            // Static function.
            \call_user_func_array(array($model_name, $action), $arguments);
        }
        if (\nmvc\request\is_ajax())
            \nmvc\request\send_json_data(true);
        else
            \nmvc\request\redirect($url);
    }
}