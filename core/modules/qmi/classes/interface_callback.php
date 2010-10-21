<?php namespace nmvc\qmi;

/**
 * Used for declaring callbacks to model interfaces.
 * Declare a callback by using the model interface name.
 */
abstract class InterfaceCallback_app_overrideable {
    private $interface_name;
    private $instances;
    private $instance_fields;
    private $is_deleting;
    private $success_url;

    /**
     * @var string Contains the message that will be displayed when form validation failed.
     */
    protected $validate_failed_message;

    public final function __construct($interface_name, $instances, $instance_fields, $is_deleting, $success_url) {
        $this->interface_name = $interface_name;
        $this->instances = $instances;
        $this->instance_fields = $instance_fields;
        $this->is_deleting = $is_deleting;
        $this->success_url = $success_url;
        $this->validate_failed_message = __("Validation failed. Please check your input.");
    }

    /**
     * Returns true if delete button was clicked for current interface.
     * @return boolean
     */
    protected final function isDeleting() {
        return $this->is_deleting;
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
    protected final function getSuccessUrl() {
        return $this->success_url;
    }

    private $invalidation_data = array();

    protected final function doInvalidRedirect() {
        // Fetch all interface values and return them.
        foreach ($this->instances as $instance_key => $instance) {
            $instance_db_key = ModelInterface::getDatabaseInstanceKey($instance);
            foreach ($instance as $field_name => $field) {
                $value = $field->get();
                if ($value instanceof Type)
                    $value = $field->getSQLValue();
                $this->invalidation_data[$instance_db_key]['values'][$field_name] = $value;
            }
        }
        // Store invalid and reload this URL.
        $_SESSION['qmi_invalid'] = $this->invalidation_data;
        \nmvc\messenger\redirect_message(REQ_URL, $this->validate_failed_message);
    }

    protected final function pushError(\nmvc\Model $instance, $field_name, $error) {
        $instance_db_key = ModelInterface::getDatabaseInstanceKey($instance);
        $this->invalidation_data[$instance_db_key]['errors'][$field_name] = $error;
    }

    /**
     * Validates instances and returns the count of incorrect fields found.
     * It will disregard fields that are not part of current generated
     * interface.
     */
    protected final function doValidate() {
        // Validate all instances.
        $error_count = 0;
        foreach ($this->instances as $instance_key => $instance) {
            $error_fields = $instance->uiValidate($this->interface_name);
            // Validation not returning array = validation success.
            if (!is_array($error_fields))
                continue;
            // No components for instance = validation success.
            if (!isset($this->instance_fields[$instance_key]))
                continue;
            // Not interested in invalid fields we have not created interfaces for.
            $error_fields = array_intersect_key($error_fields, $this->instance_fields[$instance_key]);
            // Empty array = validation success.
            if (count($error_fields) == 0)
                continue;
            foreach ($error_fields as $field_name => $error)
                $this->pushError($instance, $field_name, $error);
            $error_count += \count($error_fields);
        }
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
        if (\nmvc\string\starts_with($name, "ic_")) {
            if ($this->doValidate() > 0)
                $this->doInvalidRedirect();
            if ($this->is_deleting)
                $this->doDelete();
            else
                $this->doStore();
            // Redirect to success url.
            \nmvc\request\reset();
            $success_url = $this->success_url;
            $success_url = \str_replace("{id}", $this->iid, $success_url);
            \nmvc\request\redirect($success_url);
        } else
            \trigger_error("Called unknown method $name on " . __CLASS__ . ".", \E_USER_ERROR);
    }
}