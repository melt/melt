<?php namespace nanomvc\nano_cms;

class ViewController extends \nanomvc\Controller {
    public function _dir($dir_id) {
        \nanomvc\request\show_xyz(403);
    }

    public function _page($page_id) {
        $this->page = PageModel::selectByID($page_id);
    }
}
