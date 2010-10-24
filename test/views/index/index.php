<?php namespace nmvc; ?>
<?php $this->layout->enterSection("head"); ?>
    <title>NanoMVC self-test</title>
    <style type="text/css">
        body {
            font: 14px arial;
        }

        .display_btn {
            font-family: monospace;
            display: inline-block;
            padding: 5px;
            color: white;
            border: black solid 1px;
            background-color: #f33;
            border-color: #550000;
            cursor: pointer;
        }

        .all_pass {
            background-color: #292;
            border-color: #005500;
        }

        .test_group_results {}

            .test_group_results li {
                list-style: none none;
                padding: 5px;
                margin-bottom: 2px;
                font-family: monospace;
            }
            .test_group_results iframe {
                display: none;
            }
            .test_group_results li span:first-child {
                display: block;
                margin-bottom: 2px;
                border: black solid 1px;
                padding: 5px;
                color: white;
            }
            .test_group_results li span.pass {
                background-color: #292;
                border-color: #005500;
            }
            .test_group_results li span.fail {
                background-color: #f33;
                border-color: #550000;
                cursor: pointer;
            }
        
    </style>
<?php $this->layout->exitSection(); ?>
<h1>nanomvc self-unit-test</h1>
<ul>
    <li>Test Version: <?php echo APP_CONFIG; ?></li>
    <li>NanoMVC Version: <?php echo internal\VERSION; ?></li>
    <li>Started: <?php echo date("r",  $this->started); ?></li>
    <li>Completed: <?php echo date("r"); ?></li>
</ul>
<h2>test groups</h2>
<?php echo $this->test_outcomes; ?>

<script type="text/javascript">
    $(function() {
        $("ul.test_group_results").each(function() {
            var ul = this;
            var label = document.createElement("div");
            $(label).addClass("display_btn");
            var test = $(ul).prev("h2").html();
            var fails = $(ul).find(".fail").length;
            if (fails == 0)
                $(label).addClass("all_pass");
            var tests = $(ul).find(".test_outcome").length;
            $(label).html(test + " - " + (tests - fails) +  '/' + tests + ' tests passed');
            $(ul).prev("h2").detach();
            $(ul).before(label);
            $(label).click(function() {
                $(ul).toggle();
            });
            $(ul).hide();
        });
    });
</script>