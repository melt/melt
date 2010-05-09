<?php namespace nmvc\data_tables;

/**
 * Helper interface for standardizing the way models are enlisted.
 */
interface DataTablesListable {
    /**
     * Use this function to specify the columns that should be used
     * when the model is enlisted, and also their labels.
     * @return array Like: array("column1" => "First Column", "column2" => ...)
     */
    public static function getEnlistColumns();
    /**
     * Use this function to display values in an other format when printed
     * in the table.
     * Should return an array where the keys match model fields and their
     * values match their in-table value representation.
     * The model fields not return will be string-ified the normal way.
     * @return array Like: array("is_admin" => "NO", ...)
     */
    public function getTableEnlistValues();
}