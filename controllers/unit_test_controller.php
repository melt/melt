<?php
/**
*@desc This controller is the official unit tester.
*      Do not use this controller as a reference, it does a lot of "illegal" stuff
*      that normal controllers never should do. Declare global functions for example.
*/

require "controllers/components/unit_testing_api.php";
require "controllers/components/unit_tests.php";

class UnitTestController extends Controller {
    public $layout = "html";

    // Executes unit testing.
    public function execute() {
        // Logic is in the view.
    }

}

?>
