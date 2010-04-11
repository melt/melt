<?php

namespace nanomvc\qmi;

class ActionsController {
    /**
     * Callback for qmi actions.
     * @see get_action_link
     */
    function set($data) {
        $data = \nanomvc\string\simple_decrypt($data, get_qmi_key());
        if ($data === false)
            \nanomvc\request\show_404();
        list($id, $model_name, $action, $url) = unserialize($data);
        $instance = call_user_func(array($model_name, "selectID"), $id);
        if ($instance === false)
            \nanomvc\request\show_404();
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
            \nanomvc\request\go_back();
        else
            \nanomvc\request\redirect($url);
    }
}