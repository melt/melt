<?php

class FailedTestException extends Exception { }

function print_var($var) {
    if (is_object($var))
        return "object {" . get_class($var) . "}";
    else if (is_bool($var))
        return $var? "boolean {TRUE}": "boolean {FALSE}";
    else if (is_null($var))
        return "NULL";
    else if (is_array($var))
        return print_r($var, true);
    else if (is_string($var))
        return "string {\"$var\"}";
    else
        return gettype($var) . " {" . print_r($var, true) . "}";
}

function assert_not_reach() {
    throw new FailedTestException("assert_not_reach fail: point reached.");
}

function assert_equality($a, $b) {
    if ($a != $b)
        throw new FailedTestException("assert_equality fail:\na = " . print_var($a) . "\nb = " . print_var($b));
}

function assert_relation($a, $b, $relation) {
    if (!eval("return \$a $relation \$b;"))
        throw new FailedTestException("assert_relation fail:\na = " . print_var($a) . "\nb = " . print_var($b));
}

function assert_($a) {
    if (!$a)
        throw new FailedTestException("assert_ fail: " . print_var($a));
}

function assert_type($a, $type = "object") {
    if (gettype($a) != $type)
        throw new FailedTestException("assert_type fail: Expected type '$type'. Got:\n" . print_var($a));
}

function assert_array($array, $length = -1, $length_relation = "==") {
    if (!is_array($array))
        throw new FailedTestException("assert_array fail: Expected array. Got:\n" . print_var($array));
    if ($length >= 0) {
        $count = count($array);
        if (!eval("return $count $length_relation $length;"))
            throw new FailedTestException("assert_array fail:\nLength is $count.\nExpected a length $length_relation $length.\n\n" . print_var($array));
    }
}

function assert_string($string, $length = -1, $length_relation = "==") {
    if (!is_string($string))
        throw new FailedTestException("assert_string fail: Expected string. Got:\n" . print_var($string));
    if ($length >= 0) {
        $count = strlen($string);
        if (!eval("return $count $length_relation $length;"))
            throw new FailedTestException("assert_string fail:\nLength is $count.\nExpected a length $length_relation $length.\n\n" . print_var($string));
    }
}

function start_test_group($group_name, $first = false) {
    static $last = null;
    if ($first) {
        $closer = "";
    } else {
        $closer = "Battery completed in " . round((microtime(true) - $last) * 1000) . ' ms.</div>';
    }
    $last = microtime(true);
    echo "$closer<div class=\"test\">Testing '$group_name':";
}

function do_test($test_name, $test_class, $test_method) {
    $message = "<b>$test_name</b>: ";
    try {
        call_user_func(array($test_class, $test_method));
        $passed = true;
    } catch (Exception $e) {
        $fail_msg = "";
        if (!is_a($e, "FailedTestException"))
            $fail_msg = "test failure: Exception caught: ";
        $fail_msg .= $e->getMessage() . "\n\n";
        $fail_msg = str_replace(" ", "&nbsp;", $fail_msg);
        $fail_msg = str_replace("\n", "<br />", $fail_msg);
        $path_len = strlen(getcwd()) + 1;
        foreach ($e->getTrace() as $trace) {
            $file = substr(@$trace['file'], $path_len);
            $line = @$trace['line'];
            $func = @$trace['function'];
            $args = array();
            foreach ($trace['args'] as $arg) {
                if (is_array($arg) || is_object($arg)) {
                    $object = is_array($arg)? "Array": "Object";
                    $args[] = "$object{<span class=\"hidden_arg\">" . print_var($arg) . " </span>}";
                } else
                    $args[] = print_var($arg, true);
            }
            $args = implode(", ", $args);
            $fail_msg .= "<u>$file</u>:<b>$line</b> <i>in</i> $func($args)<br />";
            if (strpos($func, $test_method) !== false)
                break;
        }
        $passed = false;
    }
    if ($passed) {
        $result = "pass";
        $message .= "pass";
    } else {
        $result = "fail";
        $message .= $fail_msg;
    }
    ?><div class="<?php echo $result; ?>"><?php echo $message; ?></div><?
}



?>
