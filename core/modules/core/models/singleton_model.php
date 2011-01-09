<?php namespace nmvc\core;

/**
 * A singleton model is a model that only and always has exactly one instance.
 * Singleton models does not have an unlinked state.
 * Use get() to get the instance.
 */
abstract class SingletonModel extends \nmvc\AppModel {
    private static $is_getting = false;

    /**
     * Returns this SingletonModel instance.
     * This function ensures that exactly one exists.
     */
    public static function get($for_update = false) {
        static $singleton_model_cache = array();
        $class_name = get_called_class();
        if (isset($singleton_model_cache[$class_name]))
            return $singleton_model_cache[$class_name];
        $selection = static::select();
        if ($for_update)
            $selection->forUpdate();
        $result = $selection->all();
        $singleton_model = \reset($result);
        if ($singleton_model === false) {
            // If there are no model instance, create new.
            self::$is_getting = true;
            $singleton_model = new $class_name();
            self::$is_getting = false;
            $singleton_model->store();
        } else {
            // If there are more than one model instance, unlink the rest.
            while (false !== ($instance = \next($result)))
                $instance->unlink();
        }
        return $singleton_model_cache[$class_name] = $singleton_model;
    }

    protected function initialize() {
        parent::initialize();
        if (!self::$is_getting)
            \trigger_error("Only SingletonModel may create new instance of itself. Access the instance trough the ::get() function.", \E_USER_ERROR);
    }
}
