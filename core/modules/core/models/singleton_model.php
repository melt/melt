<?php namespace nmvc\core;

/**
 * A singleton model is a model that only and always has exactly one instance.
 * Singleton models does not have an unlinked state.
 * Use get() to get the instance.
 */
abstract class SingletonModel extends \nmvc\AppModel {
    /**
     * Returns this SingletonModel instance.
     * This function ensures that exactly one exists.
     */
    public static function get() {
        static $singleton_model_cache = array();
        $class_name = get_called_class();
        if (isset($singleton_model_cache[$class_name]))
            return $singleton_model_cache[$class_name];
        // Fetching singleton model is done in an atomic operations to ensure no duplicates.
        static::lock();
        $result = parent::selectWhere();
        $singleton_model = \reset($result);
        // If there are more than one model instance, unlink the rest.
        while (false !== ($instance = \next($result)))
            $instance->unlink();
        if ($singleton_model === null) {
            $singleton_model = self::insert();
            $singleton_model->store();
        }
        // Exiting critical section.
        \nmvc\db\unlock();
        return $singleton_model_cache[$class_name] = $singleton_model;
    }

    public static function selectByID($id) {
        return self::get();
    }

    public static function insert() {
        return self::get();
    }

    

    // This functions is meaningless when used directly but still
    // useful for automated operations that don't care if this is a singelton
    // or not. Enforce single instance behaviour by never
    // returning more than one result.
    public static function selectFreely($sqldata) {
        $results = parent::selectFreely($sqldata);
        if (count($results) > 1)
            $results = array(reset($results));
        return $results;
    }

    public static function count($where = "") {
        $count = parent::count($where);
        if ($count > 1)
            $count = 1;
        return $count;
    }
}
