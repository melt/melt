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
    
    public function purify() {
        // Display all SQL queries made during repair.
        \nmvc\db\enable_display();
        \nmvc\Model::purifyAllModels();
        die("\n\n\n>>> Model purification complete!");
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
            $this->engine->importLanguage($po_content);
        } else if ($action === "switch") {
            $this->engine->setNextLocale($locale);
        } else
            \nmvc\request\show_404();
        \nmvc\request\redirect("/core/action/locale");
    }
}