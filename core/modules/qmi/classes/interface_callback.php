<?php namespace melt\qmi;

/**
 * Used for declaring callbacks to model interfaces.
 * Declare a callback by using the model interface name.
 */
abstract class InterfaceCallback_app_overrideable {
    private $interface_name;
    private $instances;
    private $instance_components;
    private $is_deleting;
    private $success_url;
    private $ajax_submit;
    private $original_model_interface;
    /** @var \DateTime When the interface was created. */
    private $time_created;

    /**
     * @var string Contains the message that will be displayed when form validation failed.
     */
    protected $validate_failed_message;

    public final function __construct($interface_name, $instances, $instance_components, $is_deleting, $success_url, $ajax_submit, $time_created, ModelInterface $original_model_interface) {
        $this->interface_name = $interface_name;
        $this->instances = $instances;
        $this->instance_components = $instance_components;
        $this->is_deleting = $is_deleting;
        $this->success_url = $success_url;
        $this->validate_failed_message = __("Validation failed. Please check your input.");
        $this->ajax_submit = $ajax_submit;
        $this->time_created = $time_created;
        $this->original_model_interface = $original_model_interface;
    }

    /**
     * Returns true if delete button was clicked for current interface.
     * @return boolean
     */
    protected final function isDeleting() {
        return $this->is_deleting;
    }

    /**
     * Returns the original (and best) model interface.
     * @return ModelInterface
     */
    protected final function getOriginalModelInterface() {
        return $this->original_model_interface;
    }

    /**
     * Returns the date time when the interface was created.
     * @return \DateTime
     */
    protected final function getTimeCreated() {
        return clone $this->time_created;
    }

    /**
     * Returns matrix of instances used for current interface.
     * It is [class_name][index] mapped.
     * @return array
     */
    protected final function getInstances() {
        // Compile instances as first parameter in callback arguments.
        $callback_instances = array();
        foreach ($this->instances as $instance)
            $callback_instances[\get_class($instance)][] = $instance;
        return $callback_instances;
    }

    /**
     * Returns the success url configured.
     * @return string
     */
    public final function getSuccessUrl() {
        return \str_replace("{id}", $this->iid, $this->success_url);
    }

    private $invalidation_data = array('errors' => array(), 'values' => array());

    protected final function doInvalidRedirect() {
        if ($this->ajax_submit)
            \melt\request\send_json_data(array("success" => false, "unlinked" => false, "errors" => $this->invalidation_data['errors']));
        $this->invalidation_data['values'] = \array_merge($this->invalidation_data['values'], $this->original_model_interface->getComponentFieldValues());
        // Store invalid and reload this URL.
        $_SESSION['qmi_invalid'][$this->interface_name] = $this->invalidation_data;
        \melt\messenger\redirect_message(REQ_URL, $this->validate_failed_message);
    }

    protected final function pushError(\melt\Model $instance, $field_name, $error) {
        $instance_key = \array_search($instance, $this->instances, true);
        if ($instance_key === false)
            trigger_error("Trying to push error to unknown instance.", \E_USER_ERROR);
        $component_key = $this->instance_components[$instance_key][$field_name];
        $this->invalidation_data['errors'][$component_key] = $error;
    }

    /**
     * This function returns true if the submit is of ajax flavour.
     * @return boolean
     */
    protected final function isAjaxSubmit() {
        return $this->ajax_submit;
    }

    /**
     * This function returns true if there are manually pushed errors that are
     * awaiting beeing forwarded by invalid redirecting.
     * @return boolean
     */
    protected final function isPushedErrorsPending() {
        return \count($this->invalidation_data['errors']) > 0;
    }

    /**
     * Validates instances and invalid redirect if invalid fields is found.
     * It will disregard fields that are not part of current generated
     * interface.
     * @param boolean $auto_redirect Set to false to return count of invalid
     * fields instead of automatically redirecting.
     * @param \Closure $should_validate_instance_fn Set to closure that
     * takes a model instance as a first argument and returns false
     * if the model instance should not be validated.
     */
    protected final function doValidate($auto_redirect = true, \Closure $should_validate_instance_fn = null) {
        // Validate all instances.
        $error_count = 0;
        foreach ($this->instances as $instance_key => $instance) {
            if (!($instance instanceof UserInterfaceProvider))
                continue;
            if ($should_validate_instance_fn !== null && !$should_validate_instance_fn($instance))
                continue;
            $error_fields = $instance->uiValidate($this->interface_name);
            // Validation not returning array = validation success.
            if (!\is_array($error_fields))
                continue;
            // No components for instance = validation success.
            if (!isset($this->instance_components[$instance_key]))
                continue;
            // Not interested in invalid fields we have not created interfaces for.
            $error_fields = \array_intersect_key($error_fields, $this->instance_components[$instance_key]);
            // Empty array = validation success.
            if (\count($error_fields) == 0)
                continue;
            foreach ($error_fields as $field_name => $error)
                $this->pushError($instance, $field_name, $error);
            $error_count += \count($error_fields);
        }
        if ($auto_redirect && $error_count > 0)
            $this->doInvalidRedirect();
        return $error_count;
    }

    private $iid = null;

    /**
     * Will store changes made to all instances.
     */
    protected final function doStore() {
        foreach ($this->instances as $instance_key => $instance) {
            $instance->store();
            if ($this->iid === null)
                $this->iid = $instance->getID();
        }
    }

    /**
     * Will delete changes made to all instances.
     */
    protected final function doDelete() {
        foreach ($this->instances as $instance)
            $instance->unlink();
    }

    /**
     * Contains default qmi interface callback handler.
     */
    public function __call($name, $arguments) {
        if (\melt\string\starts_with($name, "ic_")) {
            if ($this->doValidate() > 0)
                $this->doInvalidRedirect();
            if ($this->is_deleting)
                $this->doDelete();
            else
                $this->doStore();
        } else
            \trigger_error("Called unknown method $name on " . __CLASS__ . ".", \E_USER_ERROR);
    }
}