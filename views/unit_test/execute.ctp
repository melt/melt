<h1 style="margin: 10px;">nanoMVC - Automated System Unit Testing</h1>
<?php

// Get all test classes and execute their test functions.
$classes = get_declared_classes();
$first = true;
foreach ($classes as $class) {
    if (substr($class, -4) == "Test") {
        $test_group = substr($class, 0, -4);
        start_test_group($test_group, $first);
        $first = false;
        $methods = get_class_methods($class);
        foreach ($methods as $method) {
            if (substr($method, 0, 4) == "test") {
                $test_what = api_string::cased_to_underline(substr($method, 4));
                do_test($test_what, $class, $method);
            }
        }
    }
}

start_test_group("Final Test (test complete)");
$this->element("unit_test_styling");
?>