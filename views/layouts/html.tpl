<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
        {$head}
        {if $flash.message != null}
        <style type="text/css">
        .flasher {
            {if $flash.status == FLASH_BAD}
                background-color: #ffcfcf;
                border-color: #7f0000;
                color: #300000;
            {elseif $flash.status == FLASH_GOOD}
                background-color: #afffaf;
                border-color: #007f00;
                color: #005f00;
            {else}
                background-color: #afafaf;
                border-color: #7f7f7f;
                color: #000000;
            {/if}
            border-style: solid;
            border-width: 1px;
            padding: 5px;
            margin-bottom: 5px;
        }
        </style>
        <script type="text/javascript" src="{url path="/etc/blend.js"}"></script>
        <script type="text/javascript">
            setTimeout('blendFlasher()', 3500);
            setTimeout('hideFlasher()', 4000);
            function blendFlasher() {
                // Blend out the flasher from the screen.
                opacity('flasher', 100, 0, 450);
            }
            function hideFlasher() {
                // Make sure it's hidden. Even on older browsers.
                var e = document.getElementById('flasher');
                e.style.visibility = 'hidden';
                e.style.width = 0;
                e.style.height = 0;
            }
        </script>
        {/if}
    </head>
    <body>
        {$body_head}
        {if $flash.message != null}
        <div id="flasher" class="flasher">{$flash.message}</div>
        {/if}
        {$content}
        {$body_foot}
    </body>
</html>