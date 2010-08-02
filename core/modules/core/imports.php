<?php

/**
 * Returns TRUE if class is a base_class.
 * Replacement for PHP is_subclass_of that refuses to return true
 * for two classes that are the same and is_a also refuses to take a
 * class name as it's first argument.
 * Methaphor for this function:
 * "Zebra", "Zebra" would return TRUE because a Zebra is a Zebra.
 * "Zebra", "Animal" would also return TRUE because a Zebra is an Animal.
 * "Zebra", "Reptile" would however return FALSE. A Zebra is not a Reptile.
 * @param mixed $class Class name or object to compare.
 * @param mixed $base_class The class name or object to compare with.
 * @return boolean
 * @see is_a(), is_subclass_of()
 */
function is($class, $base_class) {
    return \nmvc\core\is($class, $base_class);
}
/**
 * This function throws an E_USER_WARNING error if the application is in
 * developer mode, and proceeds to print all arguments that where
 * passed to it.
 */
function debug() {
    call_user_func_array('\nmvc\core\debug', func_get_args());
}
