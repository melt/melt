<?php namespace nmvc;


/**
 * Responsible for testing types, conversions, cast-saftey etc.
 */
class Tg3TypesController extends TestGroupController {
    public function _run() {
        parent::_run();
        $this->autoRun();
        $this->complete();
    }

    public function t_binary() {
        $hash = \sha1("test", true) . \sha1("test2", true);
        $this->testType("binary_f", array(
            array($hash, $hash),
            array("x", "x"),
        ));
        $this->testType("binary2_f", array(
            array($hash, substr($hash, 0, 3)),
        ));
    }

    public function t_boolean() {
        $this->testType("boolean_f", array(
            array(0, false),
            array(1, true),
            array(true, true),
            array(false, false),
            array("tru_", true),
        ), array(
            array("checked", true),
            array(null, false),
            array("foo", false),
            array("'", false),
            array("", false),
        ));
    }

    public function t_bytes() {
        $this->testType("bytes_f", array(
            array(53, 53),
            array(1, 1),
            array(0, 0),
            array("100000", 100000),
            array(235276582, 235276582),
        ), array(
            array("35", 35),
            array("1 kb", 1000),
            array("9.5 kb", 9500),
            array("", 0),
            array("11   MB", 11000000),
            array(" 25 kib", 25600),
            array("7foo", 7),
            array("9.999 MB", 9999000),
            array(null, 0),
        ));
    }


    public function t_country() {
        $this->testType("country_f", array(
            array("AD", "AD"),
        ), array(
            array("BB", "BB"),
            array("ZM", "ZM"),
            array("ZZ", null),
        ));
    }

    public function t_date() {
        $set_gets = array(
            array("1999-05-23", "1999-05-23"),
            array("1999-05-32", "1999-06-01"),
            array("195-02-01", date("Y-m-d")),
            array("3471827", date("Y-m-d")),
            array("x", date("Y-m-d")),
            array("1950-02-01", "1950-02-01"),
            array("2000-02-29", "2000-02-29"),
            array("2030-02-24", "2030-02-24"),
            array("1595-02-01", null),
        );
        $this->testType("date_f", $set_gets, $set_gets);
    }

    public function t_enum_copy() {
        Test1Model::select()->unlink();
        $tm = array();
        for ($i = 0; $i < 10; $i++) {
            $test1_model = new Test1Model();
            $test1_model->text_f = \sha1($i);
            $test1_model->store();
            $tm[$i] = $test1_model->id;
        }
        $this->testType("enum_copy_f", array(
            array("foo", "foo"),
            array(7, "7"),
        ), array(
            array("foo bar", ""),
            array("'\"", ""),
            array($tm[4], \sha1(4)),
            array($tm[6], \sha1(6)),
            array($tm[3], \sha1(3)),
        ));
    }

    /*

    public function t_file() {
        $this->testType("date_F", array(
            array("AD", "AD"),
        ), array(
            array("BB", "Barbados"),
            array("ZM", "Zambia"),
            array("ZZ", null),
        ));
    }

    public function t_float() {
        $this->testType("date_F", array(
            array("AD", "AD"),
        ), array(
            array("BB", "Barbados"),
            array("ZM", "Zambia"),
            array("ZZ", null),
        ));
    }

    public function t_integer() {
        $this->testType("date_F", array(
            array("AD", "AD"),
        ), array(
            array("BB", "Barbados"),
            array("ZM", "Zambia"),
            array("ZZ", null),
        ));
    }

    public function t_ip_address() {
        $this->testType("date_F", array(
            array("AD", "AD"),
        ), array(
            array("BB", "Barbados"),
            array("ZM", "Zambia"),
            array("ZZ", null),
        ));
    }

    public function t_password() {
        $this->testType("date_F", array(
            array("AD", "AD"),
        ), array(
            array("BB", "Barbados"),
            array("ZM", "Zambia"),
            array("ZZ", null),
        ));
    }

    public function t_select() {
        $this->testType("date_F", array(
            array("AD", "AD"),
        ), array(
            array("BB", "Barbados"),
            array("ZM", "Zambia"),
            array("ZZ", null),
        ));
    }


    public function t_serialized() {
        $this->testType("date_F", array(
            array("AD", "AD"),
        ), array(
            array("BB", "Barbados"),
            array("ZM", "Zambia"),
            array("ZZ", null),
        ));
    }


    public function t_text_area() {
        $this->testType("date_F", array(
            array("AD", "AD"),
        ), array(
            array("BB", "Barbados"),
            array("ZM", "Zambia"),
            array("ZZ", null),
        ));
    }


    public function t_text() {
        $this->testType("date_F", array(
            array("AD", "AD"),
        ), array(
            array("BB", "Barbados"),
            array("ZM", "Zambia"),
            array("ZZ", null),
        ));
    }

    public function t_timespan() {
        $this->testType("date_F", array(
            array("AD", "AD"),
        ), array(
            array("BB", "Barbados"),
            array("ZM", "Zambia"),
            array("ZZ", null),
        ));
    }



    public function t_timestamp() {
        $this->testType("date_F", array(
            array("AD", "AD"),
        ), array(
            array("BB", "Barbados"),
            array("ZM", "Zambia"),
            array("ZZ", null),
        ));
    }


    public function t_universial_reference() {
        $this->testType("date_F", array(
            array("AD", "AD"),
        ), array(
            array("BB", "Barbados"),
            array("ZM", "Zambia"),
            array("ZZ", null),
        ));
    }

    public function t_yes_no() {
        $this->testType("date_F", array(
            array("AD", "AD"),
        ), array(
            array("BB", "Barbados"),
            array("ZM", "Zambia"),
            array("ZZ", null),
        ));
    }*/

    private function testType($field, $set_gets = array(), $post_reads = array()) {
        $model = new Test1Model();
        $default = $model->$field;
        foreach ($set_gets as $set_get) {
            list($set, $get) = $set_get;
            $model->$field = $set;
            if ($get === null)
                $model->$field;
            else
                $this->assert($model->$field, $get, $set_get);
            // Test string converting.
            (string) $model;
            // Test generating interface.
            $model->type($field)->getInterface("test");
            $model->$field = $default;
        }
        foreach ($post_reads as $post_read) {
            list($post, $read) = $post_read;
            if ($post === null)
                unset($_POST["test"]);
            else
                $_POST["test"] = $post;
            $model->type($field)->readInterface("test");
            if ($read === null)
                $model->$field;
            else
                $this->assert($model->$field, $read, $post_read);
            $model->$field = $default;
        }
        // Test reading null.
        unset($_POST["test"]);
        $model->type($field)->readInterface("test");
    }

}