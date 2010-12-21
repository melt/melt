<?php namespace nmvc\core;

/**
 * @internal
 */
class CallbackController extends \nmvc\core\InternalController {
    private $rpc_data;

    public function beforeFilter($action_name, $parameters) {
        /* Only accepts incomming forks if request is trusted.
        Definition of trusted request: Localhost that can read our fork key.
        Technically, this would allow a localhost on a shared server
        that can read but not write each others directories to inject
        function calls.
        This is however not a security breach as read permission would allow
        you to read other sensitive data anyway, like passwords or session keys.
        Read permission therefore indicates a sufficient level of trust.*/
        $this->rpc_data = \nmvc\string\simple_decrypt(
            \file_get_contents("php://input")
            , get_fork_key()
        );
        if ($this->rpc_data === false)
            $this->dropRequest();
        $this->rpc_data = \unserialize($this->rpc_data);
        // Only allow fresh rpc data as an extra security measure.
        if (($this->rpc_data["time"] + NMVC_CORE_FORK_TIMEOUT + 1) < time())
            $this->dropRequest();
    }

    public function fork() {
        $callback = $this->rpc_data['callback'];
        if (!\is_callable($callback))
            \trigger_error("Fork callback got uncallable callback: " . \print_r($callback, true), \E_USER_ERROR);
        $parameters = $this->rpc_data['parameters'];
        // Fork accepted, unhook from the current request to prevent
        // the parent from waiting for this request to finish, allowing
        // parallell execution.
        \nmvc\http\unhook_current_request();
        \define("REQ_IS_FORK", true);
        // Commence execution.
        \call_user_func_array($callback, $parameters);
        exit;
    }

    private function dropRequest() {
        if (!headers_sent()) {
            header("HTTP/1.0 403 Forbidden");
            header("Status: 403 Forbidden");
        }
        die("403 Forbidden");
    }
}