<?php
/**
 * Created by JetBrains PhpStorm.
 * User: gron
 * Date: 1/9/13
 * Time: 9:34 PM
 */
class ParserDB
{
    const HOST = "localhost";
    const USERNAME = "root";
    const PASSWORD = "";
    const DB_NAME = "oldbrigada";
    const DB_TABLE = "encyclopedia";

    /**
     * @var null|ParserDB Object itself
     */
    private static $_instance = null;

    /**
     * @var null|PDO PDO object itself
     */
    private $_pdo = null;

    /**
     * @var array which fields from regexp must be inserted into DB
     */
    private $_fieldsToUse = array(
        'itemName','img','align','price','additionalInfo','level'
    );

    /**
     * @var array INI data
     */
    private $_iniData = array();

    /**
     * Create PDO object
     */
    private function __construct()
    {
        $dsn = "mysql:dbname=" . ParserDB::DB_NAME . ";host=" . ParserDB::HOST . ";charset=utf8";
        $options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);
        $this->_pdo = new PDO($dsn, ParserDB::USERNAME, ParserDB::PASSWORD, $options);
    }

    /**
     * Singletone
     */
    private function __clone()
    {
    }

    /**
     * Singletone
     */
    private function __wakeup()
    {
    }

    /**
     * Singletone
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new ParserDB();
        }
        return self::$_instance;
    }

    /**
     * Insert item into DB using fields from fieldsToUse array
     * @param $item
     */
    public function insertItem($item)
    {
        $filteredItem = array();
        $this->_addToFilteredItem( "", $item, $filteredItem );
        $dbFieldsStr = implode(",",array_values($this->_fieldsToUse));
        $itemKeyFieldsStr = ":" .implode(",:",array_values($this->_fieldsToUse));

        $sql = "INSERT INTO `" . self::DB_TABLE . "` (" . $dbFieldsStr . ") VALUES (" . $itemKeyFieldsStr . ")";
        $preparedStmt = $this->_pdo->prepare( $sql );
        $preparedStmt->execute( $filteredItem );
    }

    /**
     * Add fields to filtered item
     * @param string $addon addon prefix for key
     * @param array $subData array of data
     * @param $filteredItem item to change
     */
    private function _addToFilteredItem( $addon, array $subData, &$filteredItem ){
        foreach ($subData as $key => $value) {
            if( is_array($value) ){
                $fieldPrefix = ($this->_iniData[$key]['db_table_prefix']) ? $this->_iniData[$key]['db_table_prefix'] : "";
                $this->_addToFilteredItem( $fieldPrefix, $value, $filteredItem );
            }
            if ( !is_numeric($key) && in_array($key,$this->_fieldsToUse,true)) {
                $filteredItem[$addon . $key] = $value;
            }
        }
    }

    /**
     * Set ini data field
     * @param array $iniData
     */
    public function setIniData(array $iniData){
        $this->_iniData = $iniData;
    }
}