<?php namespace nmvc\core;

/**
 * @internal
 */
class DeveloperController extends InternalController {
    public $layout = "/html/xhtml1.1";

    public function beforeFilter($action_name, $arguments) {
        // Allow internal invokes.
        if ($action_name[0] == "_")
            return;
        // Handle key entry.
        if (isset($_POST["devkey"])) {
            if (isset($_POST["year"]))
                $expire = time() + 60 * 60 * 24 * 356;
            else if (isset($_POST["day"]))
                $expire = time() + 60 * 60 * 24;
            else
                $expire = 0;
            setcookie("NMVC_DEVKEY", $_POST["devkey"], $expire, APP_ROOT_PATH, null, APP_ROOT_PROTOCOL == "https");
            \nmvc\request\redirect(url(REQ_URL));
        }
        // Block other invokes if not maintence and in developer mode.
        if (!\nmvc\core\config\MAINTENANCE_MODE) {
            \nmvc\View::render("/core/developer/no_access", $this, false, true);
            exit;
        } else if (!APP_IN_DEVELOPER_MODE) {
            \nmvc\View::render("/core/developer/enter_key", $this, false, true);
            exit;
        }
    }

    public function _maintenance_info() {
        header("HTTP/1.x 503 Service Temporarly Unavailable");
        header("Status: 503 Service Temporarly Unavailable");
        $this->est = (strlen(config\DOWN_MESSAGE) > 0)? "<p>" . config\DOWN_MESSAGE . "</p>": "<p>" . __("Please try again in a moment.") . "</p>";
    }
}