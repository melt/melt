<?php namespace nmvc\core;

/**
 * This controller exposes core developer actions.
 */
class ActionController extends DeveloperController {
    public function sync() {
        // Display all SQL queries made during syncronization.
        \nmvc\db\enable_display();
        \nmvc\Model::syncronize_all_models();
        die("\n\n\n>>> Database syncronization complete!");
    }

    public function export() {
        \nmvc\translate\TranslateModule::export();
    }

    public function set_key() {
        \nmvc\request\redirect(url("/"));
    }
}