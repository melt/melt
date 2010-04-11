<?php

namespace nanomvc\core;

class ActionController extends \nanomvc\Controller {
    public function fork() {
        /* Only accepts incomming forks if request is trusted.
        Definition of trusted request: Localhost that can read our fork key.
        Technically, this would allow a localhost on a shared server
        that can read but not write each others directories to inject
        function calls.
        This is however not a security breach as read permission would allow
        you to read other sensitive data anyway, like passwords.
        Read permission therefore indicates a sufficient level of trust.*/
        if (gethostbyaddr($_SERVER['REMOTE_ADDR']) != "localhost")
            \nanomvc\request\show_xyz(403);
        $data = unserialize(file_get_contents("php://input"));
        if (!is_array($data))
            \nanomvc\request\show_xyz(403);
        if (!file_exists(".forkkey") || file_get_contents(".forkkey") != $data['forkkey'])
            \nanomvc\request\show_xyz(403);
        // Fork accepted, unhook from the current request to prevent
        // the parent from waiting for this request to finish, allowing
        // parallell execution.
        \nanomvc\http\unhook_current_request();
        // Commence execution.
        $callback = $data['callback'];
        $parameters = $data['parameters'];
        call_user_func_array($callback, $parameters);
        exit;
    }

    public function sync() {
        if (!APP_IN_DEVELOPER_MODE)
            request\show_xyz(403);
        // Display all SQL queries made during syncronization.
        \nanomvc\db\enable_display();
        \nanomvc\Model::syncronize_all_models();
        die("Database syncronization complete!");
    }

    public function export() {
        if (!APP_IN_DEVELOPER_MODE)
            \nanomvc\request\show_xyz(403);
        \nanomvc\translate\TranslateModule::export();
    }

    public function set_key() {
        // Allow all requests to a special URL that sets the developer cookie for 10 years.
        if (isset($_POST["devkey"])) {
            setcookie("devkey", $_POST["devkey"], intval(time() + 60 * 60 * 24 * 365.242199 * 10), APP_ROOT_PATH);
            \nanomvc\request\redirect(url("/"));
        }
    }
    
}