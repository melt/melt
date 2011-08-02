<?php namespace melt\core; ?>
<?php
    $fn_print_call_branch = function($calls) use (&$fn_print_call_branch) {
        static $start_time = 0;
        foreach ($calls as $call) {
            $extra_classes = "";
            if (\strpos($call->call_signature, "autoload") !== false)
                $extra_classes .= " autoload_call";
            echo '<div class="call' . $extra_classes . '">';
            if ($start_time === 0)
                $start_time = $call->call_time;
            $call_time = \round(($call->call_time - $start_time) * 1000, 4);
            echo '<span class="timing">' . $call_time . '</span>';
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
    <script type="text/javascript" src="<?php echo url("/static/cmod/js/jquery.js"); ?>"></script>
    <style type="text/css">
        body {
            margin-left: 110px;
        }
        .call {
            font-family: monospace;
            margin-left: 16px;
        }
        .timing {
            position: absolute;
            left: 0px;
        }
        .delta {
            color: red;
            position: absolute;
            left: 70px;
        }
    </style>
    <script type="text/javascript">
        function autoload_toggle(visible) {
            $(".autoload_call").toggle(visible);
        }
        $(function() {
            $(".call").click(function(event) {
                console.debug(event);
                $(this).find(".subcalls:first").toggle();
                return false;
            });
            $(".timing").each(function(id, element) {
                var time_a = $(this).text();
                var time_b = null;
                
                
                time_b = $(this).parent(".call").next().find(".timing").text();
                if(time_b != null){
                    $(this).parents().nextAll(".call").each(function () {
                       time_b = $(this).find(".timing").text();
                       if(time_b !== null) { return false; }
                    });
                }
                
                if(!isNaN(parseFloat(time_b)))
                {
                    var delta = parseFloat(time_b) - parseFloat(time_a);
                    $(this).after($("<span>").addClass("delta").text(Math.round(delta*1000)/1000));
                }
                
            });
        });
            
                
    </script>
</head>
<body>
    <p>
        Tracing is currently enabled by using _trace query key, the request was
        therefore halted after completion. Here is the full trace graph: (timings are in ms)
    </p>
    <input type="button" onclick="javascript: autoload_toggle(false)" value="Hide Autoloads" />
    <input type="button" onclick="javascript: autoload_toggle(true)" value="Show Autoloads" />
    <div>
        <?php $fn_print_call_branch($this->trace_graph->subcalls, 1); ?>
    </div>
</body>