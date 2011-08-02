<?php namespace melt\core;

/**
 * Represents data from an controller action invoke.
 */
class InvokeData {
    private $controller_class;
    private $action_name;
    private $arguments;

    public function getControllerClass() {
        return $this->controller_class;
    }

    public function getActionName() {
        return $this->action_name;
    }

    public function getArguments() {
        return $this->arguments;
    }

    /**
     * Constructs an InvokeData object.
     * @param string $controller_class
     * @param string $action_name
     * @param array $arguments
     */
    public function __construct($controller_class, $action_name, $arguments) {
        $this->controller_class = strval($controller_class);
        $this->action_name = strval($action_name);
        if (!is_array($arguments)) {
            trigger_error("InvokeData expects arguments to be passed in array form. Assuming passing one argument.", \E_USER_NOTICE);
            $arguments = array($arguments);
        }
        $this->arguments = $arguments;
    }
}