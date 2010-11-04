<?php namespace nmvc;

class TestFooModel extends AppModel {
    public $bar_id = array('core\PointerType', 'TestBarModel');
    public $bar2_id = array('core\PointerType', 'TestBarModel', 'CASCADE');
    public $name = array('core\TextType', 16);
}