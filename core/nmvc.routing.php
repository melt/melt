<?php namespace nmvc\internal;

\call_user_func(function() {
    // Enable call tracing if requested.
    static $trace_graph = null;
    \define('APP_IN_TRACE_MODE', APP_IN_DEVELOPER_MODE && isset($_GET["_trace"]));
    if (APP_IN_TRACE_MODE) {
        $trace_graph = new \nmvc\core\CallTraceNode(array("call_signature" => "ROOT"));
        \register_tick_function(function() use ($trace_graph) {
            static $done = false;
            if ($done)
                return;
            static $call_stack = array();
            //static $last_level = 0;
            static $last_backtrace;
            static $start_point = null;
            $backtrace = \array_reverse(\debug_backtrace(false));
            $level = \count($backtrace) - 1;
            if ($start_point === null) {
                $start_point = $level;
                $call_stack[$start_point] = $trace_graph;
                //$last_level = $start_point;
            } else if ($level < $start_point) {
                $done = true;
            } else {
                // Find the last common node.
                for ($i = $start_point + 1; $i <= $level - 1; $i++) {
                    if (isset($last_backtrace[$i]) && $last_backtrace[$i] == $backtrace[$i])
                        continue;
                    $at_call = $call_stack[$i - 1];
                    for (; $i <= $level; $i++) {
                        $backtrace_call = $backtrace[$i];
                        $new_call = new \nmvc\core\CallTraceNode(array(
                            "call_signature" => get_call_signature($backtrace_call),
                            "call_time" => \microtime(true)
                        ));
                        $at_call->subcalls[] = $new_call;
                        $call_stack[$i] = $new_call;
                        $at_call = $new_call;
                    }
                    break;
                }
                /*
                // Call detected.
                $backtrace_call_position = $level - $i;
                $backtrace_call = $backtrace[$backtrace_call_position];
                $new_call = new \nmvc\core\CallTraceNode(array(
                    "call_signature" => get_call_signature($backtrace_call),
                    "call_time" => \microtime(true)
                ));
                $at_call->subcalls[] = $new_call;
                $call_stack[$i] = $new_call;
                $at_call = $new_call;*/
            }
            /*
            } else if ($level > $last_level) {
                for ($i = $last_level + 1; $i <= $level; $i++) {
                    // Call detected.
                    $backtrace_call_position = $level - $i;
                    $backtrace_call = $backtrace[$backtrace_call_position];
                    $new_call = new \nmvc\core\CallTraceNode(array(
                        "call_signature" => get_call_signature($backtrace_call),
                        "call_time" => \microtime(true)
                    ));
                    $at_call->subcalls[] = $new_call;
                    $call_stack[$i] = $new_call;
                    $at_call = $new_call;
                }
            } else if ($level < $last_level) {
                // Return detected.
                $at_call->return_time = \microtime(true);
                $at_call = $call_stack[$level];
            }*/
            $last_backtrace = $backtrace;
            //$last_level = $level;
        });
    }
    // Routing of required actions to take to gracefully complete a request.
    \register_shutdown_function(function() use ($trace_graph) {
        \register_shutdown_function(function() use ($trace_graph) {
            $crash = \defined("NMVC_REQUEST_CRASHED") && NMVC_REQUEST_CRASHED;
            if (!$crash) {
                // Detect a PHP fatal error.
                $last_error = \error_get_last();
                if ($last_error !== null) {
                    $crash = \in_array($last_error["type"]
                    , array(E_ERROR, E_PARSE, E_CORE_ERROR,	E_COMPILE_ERROR, E_USER_ERROR));
                    // Tries to send internal server error here as these crashes
                    // bypass error handler.
                    if ($crash && !\headers_sent()) {
                        \header("HTTP/1.x 500 Internal Server Error");
                        \header("Status: 500 Internal Server Error");
                    }
                }
            }
            // Display trace graph results.
            if (!$crash && APP_IN_TRACE_MODE) {
                \ob_end_clean();
                development_crash("trace", array("trace_graph" => $trace_graph));
            }
            // Skip graceful completion of request that crash.
            // At those we'd rather forget everything we done and rollback.
            if (!$crash) {
                // End request handler. Making all side effects permanent.
                \ignore_user_abort(true);
                // Write updated session data. (Can't do this automatically
                // as we need to still require object instancing at that point.)
                \session_write_close();
                // If using request level transactionality, now is the time to commit.
                if (\nmvc\db\config\REQUEST_LEVEL_TRANSACTIONALITY)
                    \nmvc\db\query("COMMIT");
                // Process any unsent mails in mail queue.
                if (!REQ_IS_CORE_DEV_ACTION)
                    \nmvc\mail\SpooledMailModel::processMailQueue(true);
            }
            \define("NMVC_REQUEST_COMPLETE", true);
        });
    });
    // Start tracing here if tracing.
    if (APP_IN_TRACE_MODE)
        declare(ticks=1);
    // Stuff can be rendered beyond this point, so reset output buffer.
    \nmvc\request\reset();
    // Parse the request and redirect if the url is invalid.
    $url_tokens = \explode("/", \substr(REQ_URL, 1));
    // If any arguments are empty the URL is invalid, remove them and
    // redirect the browser. This prevents double URL mapped to the same
    // things so it is good for consistancy and SEO.
    if (\count($url_tokens) > 1) {
        $clear_arg = array();
        foreach ($url_tokens as $url_token)
        if (\strlen($url_token) > 0)
            $clear_arg[] = $url_token;
        if (\count($url_tokens) != \count($clear_arg)) {
            $clear_arg = \count($clear_arg) > 0? "/" . \implode("/", $clear_arg): "";
            \nmvc\request\redirect(\nmvc\request\url($clear_arg));
            exit;
        }
    }
    // Handle apache special code pages.
    $redir_status = isset($_SERVER["REDIRECT_STATUS"])? $_SERVER["REDIRECT_STATUS"]: null;
    if ($redir_status != "200" && $redir_status != null)
        \nmvc\request\show_xyz($redir_status);
    // Stop request here if in developer mode and not developer controller.
    if (\nmvc\core\config\MAINTENANCE_MODE && !APP_IN_DEVELOPER_MODE) {
        if (\nmvc\Controller::invokeFromExternalRequest($url_tokens, 'nmvc\core\InternalController'))
            exit;
        \nmvc\core\DeveloperController::invoke("_maintenance_info", array(), true);
        exit;
    }
    // Invoke all modules before request processors.
    foreach (get_all_modules() as $module_name => $module_parameters) {
        $class_name = $module_parameters[0];
        \call_user_func(array($class_name, "beforeRequestProcess"));
    }
    // Inject request into standard MVC handling.
    if (\nmvc\Controller::invokeFromExternalRequest($url_tokens))
        exit;
    // See if any module is interested in catching the request instead.
    foreach (get_all_modules() as $module_name => $module_parameters) {
        $class_name = $module_parameters[0];
        \call_user_func(array($class_name, "catchRequest"), $url_tokens);
    }
    // Finally show 404.
    \nmvc\request\show_404();
});