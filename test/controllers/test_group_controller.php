<?php namespace nmvc;

abstract class TestGroupController extends AppController {
    public $name;
    private $test_outcomes = array();

    public function _run() {
        \set_time_limit(0);
        $this->name = substr(get_class($this), 5, -10);
    }

    protected function autoRun() {
        foreach ($this->getActions() as $action) {
            if ($action[0] == "_")
                continue;
            $this->test($this->getPath($action), "");
        }
    }

    protected function complete() {
        View::render("/test_group", array("name" => $this->name, "test_outcomes" => $this->test_outcomes), false, true);
    }

    protected function test($local_path, $expected_response = false) {
        $outcome = new TestOutcome();
        $outcome->name = "#" . \count($this->test_outcomes) . ", " . $local_path;
        $this->test_outcomes[] = $outcome;
        $response = http\request(url($local_path), http\HTTP_METHOD_GET, array(), null, true);
        $headers = $response[1];
        $response = $response[0];
        if (isset($headers["X-Ntest-Assert-Failure"])) {
            $outcome->success = false;
            $outcome->data = $response;
            $outcome->reason = "Assertation failure!";
            return;
        }
        $expecting_controlled_fail = $expected_response === false;
        $controlled_fail = string\starts_with($response, "<h1>nanoMVC - Exception Caught</h1>") &&
        false !== \strpos($response, "__Messsage: E_USER_");
        if ($controlled_fail && $expecting_controlled_fail) {
            $outcome->success = true;
        } else if ($controlled_fail) {
            $outcome->success = false;
            $outcome->data = $response;
            $outcome->reason = "Unexpected failure/exception.";
        } else {
            if ($expected_response != $response) {
                $outcome->success = false;
                $outcome->data = $response;
                $outcome->reason = "Unexpected data.";
            } else {
                $outcome->success = true;
            }
        }
    }
    
    protected function assert($a, $b = true, $additional_vars = null) {
        if ($a === $b)
            return;
        \header("X-Ntest-Assert-Failure: 1");
        debug("Assertation failed.", $a, $b, $additional_vars);
    }
}