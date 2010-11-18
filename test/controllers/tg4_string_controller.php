<?php namespace nmvc;


/**
 * Responsible for testing the string API.
 */
class Tg4StringController extends TestGroupController {
    public function _run() {
        parent::_run();
        $this->autoRun();
        $this->complete();
    }

    public function lcs_distance() {
        $this->assert(
            string\lcs_distance("MZJAWXU", "XMJYAUZ"),
            4
        );
        $this->assert(
            string\lcs_distance("CHIMPANZEE", "HUMAN"),
            4
        );
        $this->assert(
            string\lcs_distance("FOOBAR", "FOObar"),
            3
        );
        $this->assert(
            string\lcs_distance("FOOBAR", "barFOO"),
            3
        );
        $this->assert(
            string\lcs_distance("qwertyuiopZXCVBNM", "zxcvbnmasdfghjkl"),
            0
        );
        // Make a 5000 byte test that should return 500 for 1 similar char per 10.
        $stra = \str_pad("", 5000, "asdfGhjklp");
        $strb = \str_pad("", 5000, "ASDFGHJKLP");
        $this->assert(
            string\lcs_distance($stra, $strb),
            500
        );
        // This test should hang if optimization doesn't work.
        $stra = \str_pad("", 50000, "abcdefghijklmnopqrstuvwxyz");
        $strb = \str_pad("", 50000, "zxabcdeihgjklonmprutvsyzwu");
        $this->assert(
            string\lcs_distance($stra, $strb, 48500),
            false
        );
    }

    public function lcs_similarity() {
        $this->assert(
            string\lcs_similarity("MZJAWXU5", "XMJYAUZ6"),
            4 / 8
        );
        $this->assert(
            string\lcs_similarity("AE", "AEAEAEAE"),
            2 / 8
        );
        $this->assert(
            string\lcs_similarity("aw9taaw9ta", "aw9taaw9ta"),
            10 / 10
        );
        $this->assert(
            string\lcs_similarity("aw9taaw9ta", "aw9taaw9ta", 1),
            10 / 10
        );
        $this->assert(
            string\lcs_similarity("qwertASDFG", "QWERTASDFG", 0.499),
            5 / 10
        );
        $this->assert(
            string\lcs_similarity("qwertASDFG", "QWERTASDFG", 0.6),
            false
        );
    }

}