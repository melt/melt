<?php namespace nanomvc\qmi;

class QmiModule extends \nanomvc\Module {
    public static function beforeRequestProcess() {
        if (isset($_POST['_qmi']))
            ModelInterface::_interface_callback();
    }

    public static function getAuthor() {
        $year = date("Y");
        return "Hannes Landeholm, Media People Sverige HB, ©$year";
    }

    public static function getInfo() {
        return "<b>qmi - Quantum Model Interface - A flexible interface provider for nanoMVC models</b>"
        . "This module may not be used without a valid license.";
    }

    public static function getVersion() {
        return "1.0.1";
    }
}