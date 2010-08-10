<?php namespace nmvc\core;

/**
 * This controller exposes core developer actions.
 */
class ActionController extends DeveloperController {
    public function sync() {
        // Display all SQL queries made during syncronization.
        \nmvc\db\enable_display();
        \nmvc\Model::syncronizeAllModels();
        die("\n\n\n>>> Database syncronization complete!");
    }

    public function repair() {
        // Display all SQL queries made during repair.
        \nmvc\db\enable_display();
        \nmvc\Model::repairAllModels();
        die("\n\n\n>>> Model repairation complete!");
    }

    public function export() {
        \nmvc\translate\TranslateModule::export();
    }

    public function set_key() {
        \nmvc\request\redirect(url("/"));
    }
}