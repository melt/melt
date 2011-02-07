<?php namespace nmvc\core; ?>
<?php
    $fn_print_call_branch = function($calls) use (&$fn_print_call_branch) {
        foreach ($calls as $call) {
            $extra_classes = "";
            if (\strpos($call->call_signature, "autoload") !== false)
                $extra_classes .= " autoload_call";
            echo '<div class="call' . $extra_classes . '">';
            echo escape($call->call_signature);
            if (\count($call->subcalls) > 0) {
                echo '<span class="subcalls">';
                $fn_print_call_branch($call->subcalls);
                echo '</span>';
            }
            echo '</div>';
        }
    };
?>
<head>
    <style type="text/css">
        .call {
            font-family: monospace;
            margin-left: 16px;
        }
    </style>
    <script type="text/javascript">
        function autoload_toggle(visible) {
            var elements = document.getElementsByClassName("autoload_call");
            for (var i in elements) {
                if (!elements[i].style)
                    continue;
                elements[i].style.display = visible? "block": "none";
            }
        }
    </script>
</head>
<body>
    <p>
        Tracing is currently enabled by using _trace query key, the request was
        therefore halted after completion. Here is the full trace graph:
    </p>
    <input type="button" onclick="javascript: autoload_toggle(false)" value="Hide Autoloads" />
    <input type="button" onclick="javascript: autoload_toggle(true)" value="Show Autoloads" />
    <div>
        <?php $fn_print_call_branch($this->trace_graph->subcalls, 1); ?>
    </div>
</body>