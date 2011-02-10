<?php namespace nmvc\core;

/**
 * This controller exposes core developer actions.
 */
class ActionController extends DeveloperController {
    public function sync() {
        // Display all SQL queries made during syncronization.
        \nmvc\db\enable_display();
        \nmvc\Model::syncronizeAllModels();
        die("\n\n\n>>> Database syncronization complete!\n\n");
    }

    public function repair() {
        // Display all SQL queries made during action.
        \nmvc\db\enable_display();
        \nmvc\Model::repairAllModels();
        die("\n\n\n>>> Model repairation complete!\n\n");
    }
    
    public function purify() {
        // Display all SQL queries made during action.
        \nmvc\db\enable_display();
        \nmvc\Model::purifyAllModels();
        die("\n\n\n>>> Model purification complete!\n\n");
    }

    public function cull() {
        // Display all SQL queries made during action.
        \nmvc\db\enable_display();
        \nmvc\Model::cullAllModels();
        die("\n\n\n>>> Model culling complete!\n\n");
    }

    public function export() {
        \nmvc\translate\TranslateModule::export();
    }

    public function info() {
        \nmvc\request\reset();
        \phpinfo();
    }

    public function set_key() {
        \nmvc\request\redirect(url("/"));
    }

    public function restart() {
        SessionDataModel::select()->unlink();
        die("Application restarted, all sessions where teared down!");
    }

    public function locale($action = null, $locale = null) {
        $this->engine = LocalizationEngine::get();
        if ($action === null)
            return "/core/locale_console";
        else if ($action === "create") {
            $locale = @$_GET["locale"];
            $this->engine->createLocale($locale);
        } else if ($action === "remove") {
            $this->engine->removeLocale($locale);
        } else if ($action === "export") {
            $po_content = $this->engine->exportLanguage($locale);
            \nmvc\request\reset();
            \header('Content-Disposition: attachment; filename=nmvc-translation-' . $locale . '.po');
            \header('Content-Type: text/plain');
            echo $po_content;
            exit;
        } else if ($action === "import") {
            $po_content = get_uploaded_file("po_file", $file_name, false);
            if (!\preg_match('#^nmvc-translation-([a-z][a-z])#', $file_name, $matches))
                \trigger_error("The file name you're uploading must begin with 'nmvc-translation-xx' where xx is the locale you are importing.");
            $locale = $matches[1];
            $this->engine->importLanguage($po_content, $locale);
        } else if ($action === "switch") {
            $this->engine->setNextLocale($locale);
        } else
            \nmvc\request\show_404();
        \nmvc\request\redirect(url("/core/action/locale"));
    }
}