<?php namespace melt\qmi;

interface UserInterfaceProvider {
    /**
     * Responsible for validating instance.
     * QMI calls this function when it needs information about what
     * fields are invalid.
     * If invalid, should return an array of all fields
     * name => reason mapped, otherwise, returns an empty array.
     * Designed to be overriden.
     * @param string $interface_name
     * @return array All invalid fields, name => reason mapped.
     */
    public function uiValidate($interface_name);

    /**
     * Should return an array of all interfaces defined for this model
     * with the interface name mapped to an array of field names
     * mapped to additional data which is used when rendering the interface.
     * An additional _style variable can be present which defines the
     * render style for all interfaced instances of the interface.
     * @param string $interface_name
     * @param string $field_set
     * @return array
     */
    public static function uiGetInterface($interface_name, $field_set);
}