<?php namespace nmvc\nano_cms;

class ViewController extends \nmvc\AppController {
    public function _dir($dir_id) {
        \nmvc\request\show_xyz(403);
    }

    public function _page($page_id) {
        $this->page = PageModel::selectByID($page_id);
    }
}
