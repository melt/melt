<?php namespace nmvc\core;

/**
 * NanoMVC wrapper for reCAPTCHA.
 * reCAPTCHA is a service provided by Google Inc. which is not related
 * to, nor endorse this software.
 * @see http://www.google.com/recaptcha/terms
 */
class RecaptchaType extends \nmvc\AppType {
    const RECAPTCHA_API_SERVER = "http://www.google.com/recaptcha/api";
    const RECAPTCHA_API_SECURE_SERVER = "https://www.google.com/recaptcha/api";
    const RECAPTCHA_VERIFY_SERVER = "http://www.google.com/recaptcha/api/verify";

    public function __construct() {
        parent::__construct();
        $this->value = false;
    }
    public function get() {
        return $this->value == true;
    }

    public function set($value) {
        $this->value = $this->value == true;
    }

    public function getSQLValue() {
        return $this->value == true? 1: 0;
    }

    public function getSQLType() {
        return "int";
    }

    public function setSQLValue($value) {
        $this->value = $value > 0? 1: 0;
    }

    public function getInterface($name) {
	if (config\RECAPTCHA_PUBLIC_KEY == "")
            trigger_error("To use reCAPTCHA you must get an API key from <a href='https://www.google.com/recaptcha/admin/create'>https://www.google.com/recaptcha/admin/create</a>", \E_USER_ERROR);
	$server = APP_ROOT_PROTOCOL == "https"? self::RECAPTCHA_API_SECURE_SERVER: self::RECAPTCHA_API_SERVER;
        return '<script type="text/javascript" src="'. $server . '/challenge?k=' . config\RECAPTCHA_PUBLIC_KEY . '"></script>
        <noscript>
                <iframe src="'. $server . '/noscript?k=' . config\RECAPTCHA_PUBLIC_KEY . '" height="300" width="500" frameborder="0"></iframe><br/>
                <textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea>
                <input type="hidden" id="' . $name . '" name="recaptcha_response_field" value="manual_challenge"/>
        </noscript>';
    }

    public function readInterface($name) {
	if (config\RECAPTCHA_PUBLIC_KEY == "")
            trigger_error("To use reCAPTCHA you must get an API key from <a href='https://www.google.com/recaptcha/admin/create'>https://www.google.com/recaptcha/admin/create</a>", \E_USER_ERROR);
        $remote_ip = @$_SERVER['REMOTE_ADDR'];
        $challenge = @$_POST['recaptcha_challenge_field'];
        $response = @$_POST['recaptcha_response_field'];
        // Discard spam submissions.
        if ($challenge == null || strlen($challenge) == 0 || $response == null || strlen($response) == 0) {
            $this->value = false;
            return;
        }
        $contents = \nmvc\http\make_urlencoded_formdata(array(
             'privatekey' => config\RECAPTCHA_PRIVATE_KEY,
             'remoteip' => $remote_ip,
             'challenge' => $challenge,
             'response' => $response
        ));
        $response = \nmvc\http\request(self::RECAPTCHA_VERIFY_SERVER, \nmvc\http\HTTP_METHOD_POST, array(), null, false, $contents, 10);
        if (is_integer($response[1]))
            trigger_error("Recaptcha verification failed! Response: " . $response[1], \E_USER_ERROR);
        $answers = explode("\n", @$response[0]);
        if (trim(@$answers[0]) == "true")
            $this->value = true;
        else
            $this->value = false;
        return;
    }
}