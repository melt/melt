<?php

class UnitTestingTest {

    function testAssertEqualities() {
        assert_equality(0, 0);
        assert_relation("foo", "foo", "==");
        try {
            assert_equality(1, 0);
        } catch (FailedTestException $e) {
            return;
        }
        assert_not_reach();
    }

    function testAssertComplexObjects() {
        $a = new UnitTestingTest();
        assert_type($a, "object");
        $array = array("foo", $a, 643);
        assert_array($array);
        assert_array($array, 1, ">");
        $array = array_merge($array, array("bar", 56));
        assert_array($array, 5);
        assert_type($array, "array");
        assert_string("foo");
        try {
        assert_string(array("foo", "bar"));
        assert_not_reach();
        } catch (FailedTestException $e) {}
        assert_string("bar_huzzah", 10);
        assert_string("foo bars bar foo", 10, ">");
    }

    function testAssertControlFlow() {
        try {
            throw new Exception("Testing exception assertation.");
            assert_not_reach();
        } catch (Exception $ex) {}
    }
}

class APITest {

    function testCachingBasic() {
        api_cache::set_cache("test", "foo", "bar");
        api_cache::set_cache("test", "foo2", "2bar");
        assert_equality(api_cache::get_cache("test", "foo2"), "2bar");
        assert_equality(api_cache::get_cache("test", "foo"), "bar");
        api_cache::delete_cache("test", "foo");
        api_cache::delete_cache("test", "non existing key");
        assert_equality(api_cache::get_cache("test", "foo"), null);
        assert_relation(api_cache::cache_last_modified("test", "foo2"), time(), "<=");
        assert_(api_cache::cache_exists("test", "foo2"));
        assert_(!api_cache::cache_exists("test", "foo"));
        assert_(!api_cache::cache_exists("test", "2bar"));
        assert_type(api_cache::get_cache_path("test", "foo"), "string");
        api_cache::set_cache("test", "more", "xyz");
        $file_path = tempnam(sys_get_temp_dir(), "tmp");
        file_put_contents($file_path, "xyz_123");
        api_cache::set_cache_file("test", "foo", $file_path);
        unlink($file_path);
        assert_equality(api_cache::get_cache("test", "foo"), "xyz_123");
        api_cache::clear_cache("test");
        assert_(!api_cache::cache_exists("test", "foo"));
        assert_(!api_cache::cache_exists("test", "more"));
        assert_(!api_cache::cache_exists("test", "xyz"));
        assert_equality(api_cache::get_cache("test", "foo"), null);
    }

    function testFilesystemAPI() {
        assert_equality(api_filesystem::file_ext("test.foo"), "foo");
        assert_equality(api_filesystem::file_ext("bar."), "");
        assert_equality(api_filesystem::file_ext("filewithoutextention"), "");
        assert_array(api_filesystem::file_stat(__FILE__), 6, ">");
        assert_array(api_filesystem::get_dir_tree(".", true, true));
        assert_relation(api_filesystem::dir_modified(dirname(dirname(__FILE__))), filemtime(__FILE__), ">=");
        assert_equality(api_filesystem::resolve_mime("picture.jpg"), "image/pjpeg");
        assert_equality(api_filesystem::resolve_mime("foo.bar.txt"), "text/plain");
        assert_equality(api_filesystem::resolve_mime("foo.bar.unknown"), "application/octet-stream");
        assert_equality(api_filesystem::resolve_mime("noextention"), "application/octet-stream");
    }

    function testHtmlAPI() {
        // Doing some tough UTF-8 tests.
        assert_equality(api_html::decode("nforce&gt;__&lt;rx"), "nforce>__<rx");
        assert_equality(api_html::decode("hop hop  hop"), "hop hop  hop");
        assert_equality(api_html::decode("&amp;copy; &#x10e8;&#x10d4;&#x10db;&#x10d5;&#x10d4;&#x10d3;&#x10e0;&#x10d4;, &#x10dc;&#x10e3;&#x10d7;&#x10e3; &#x10d9;&#x10d5;&#x10da;&#x10d0;&amp;&amp;&amp;"), "&copy; შემვედრე, ნუთუ კვლა&&&");
        assert_equality(api_html::decode("&amp;#x10dc;&amp;#x10e3;&amp;#x10d7;&amp;#x10e3; &amp;#x10d9;"), "&#x10dc;&#x10e3;&#x10d7;&#x10e3; &#x10d9;");
        assert_equality(api_html::escape("_f <o>o_"), "_f &lt;o&gt;o_");
        assert_equality(api_html::escape("this text requires no escaping!"), "this text requires no escaping!");
    }

    function testMiscAPI() {
        // Test recursive array soft comparing.
        $equal1 = array("a" => 1, "b" => "foo", "c" => array(1 => "one", 2 => 2, 3 => "tre"));
        $equal2 = array("a" => true, "b" => "foo", "c" => array(1 => "one", 2 => 2, 3 => "tre"));
        $unequal = array("a" => 1, "b" => "foo", "c" => array(1 => "one", 2 => array(2), 3 => "tre"));
        assert_(api_misc::compare_arrays($equal1, $equal2));
        assert_(api_misc::compare_arrays($equal2, $equal1));
        assert_(!api_misc::compare_arrays($equal2, $unequal));
        assert_equality(api_misc::byte_unit(1000000, true), "1 MB");
        assert_equality(api_misc::byte_unit(1048576, false), "1 MiB");
    }

    function testStringAPI() {
        assert_equality(api_string::cased_to_underline("fooBarLol"), "foo_bar_lol");
        assert_equality(api_string::cased_to_underline("sdFsdf_foo_Bar"), "sd_fsdf_foo_bar");
        assert_equality(api_string::cased_to_underline("_a__"), "_a__");
        assert_(api_string::email_validate("foo@bar.com"));
        assert_(api_string::email_validate("fo_f.ee.o@bar.xyz.com"));
        assert_(!api_string::email_validate("@obar.xyz.com"));
        assert_(!api_string::email_validate("@xyz.com"));
        assert_(!api_string::email_validate("fåoo@bar.com"));
        assert_(!api_string::email_validate("foo huz@bar.com"));
        assert_(!api_string::email_validate("foo@@bar.com"));
        assert_(!api_string::email_validate("foobar.com@"));
        assert_(!api_string::email_validate(""));
        assert_string($foo = api_string::random_hex_str(), 16);
        assert_string(api_string::random_hex_str(32), 32);
        assert_relation($foo, api_string::random_hex_str(), "!=");
        assert_string(api_string::gen_key(), 16);
        assert_(api_string::starts_with("foo bar", "foo"));
        assert_(api_string::starts_with("\x32foofoo_BarBar", "\x32foofoo_BarBar"));
        assert_(!api_string::starts_with("BarFoo", "bar"));
        assert_(api_string::starts_with("foo", ""));
        assert_(!api_string::starts_with("", "bar foo"));
        $test_str = "\xf2\x01AFoემვo_barba \x03r_\x9f";
        $str = api_string::str_represent($test_str);
        assert_(preg_match('#^[a-zA-Z0-9]*$#', $str));
        assert_equality(api_string::str_derepresent($str), $test_str);
    }
}

?>