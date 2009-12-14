<?php

define("FLASH_BAD", 0);
define("FLASH_GOOD", 1);
define("FLASH_NEUTRAL", 2);

class Controller {
    /**
    * @desc The name of the layout file to render the view inside of. The name specified is the filename of the layout in /app/layouts without the ctp, php or tpl extension.
    *       The default layout simply uses the api_html buffer to view the page.
    */
    public $layout = null;

    /**
    * @desc This function is executed before every action in the controller. It's a handy place to check for an active session or inspect user permissions.
    */
    function beforeFilter() {}

    /**
    * @desc Called after controller action logic, but before the view is rendered.
    */
    function beforeRender() {}

    /**
    * @desc Called after every controller action, and after rendering is complete. This is the last controller method to run.
    */
    function afterFilter() {}

}

?>
