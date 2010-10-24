<?php namespace nmvc;

class Test2Model extends AppModel {
    public $pointer_id = array('core\PointerType', 'Test1Model');
    public $text_f = array('core\TextType', 10);
    public $another_text_f = array('core\TextType');
}