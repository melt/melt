<?php namespace nmvc\core;

/**
 * @internal
 */
class CallbackController extends \nmvc\core\InternalController {
    private function dropRequest() {
        \nmvc\request\reset();
        if (!headers_sent()) {
            header("HTTP/1.0 403 Forbidden");
            header("Status: 403 Forbidden");
        }
        die("403 Forbidden");
    }


    public function beforeFilter($action_name, $parameters) {
        /* Only accepts incomming forks if request is trusted.
        Definition of trusted request: Localhost that can read our fork key.
        Technically, this would allow a localhost on a shared server
        that can read but not write each others directories to inject
        function calls.
        This is however not a security breach as read permission would allow
        you to read other sensitive data anyway, like passwords or session keys.
        Read permission therefore indicates a sufficient level of trust.*/
        if (gethostbyaddr($_SERVER['REMOTE_ADDR']) != "localhost")
            $this->dropRequest();
        $data = unserialize(file_get_contents("php://input"));
        if (!is_array($data))
            $this->dropRequest();
        if (!file_exists(".forkkey") || file_get_contents(".forkkey") != $data['forkkey'])
            $this->dropRequest();
    }

    public function fork() {
        // Fork accepted, unhook from the current request to prevent
        // the parent from waiting for this request to finish, allowing
        // parallell execution.
        \nmvc\http\unhook_current_request();
        // Commence execution.
        $callback = $data['callback'];
        $parameters = $data['parameters'];
        call_user_func_array($callback, $parameters);
        exit;
    }
}