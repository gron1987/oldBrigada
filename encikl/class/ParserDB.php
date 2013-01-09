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

    private static $_instance = null;
    private $_pdo = null;
    private $_DBItemFields = array(
        '`name`'  => 'itemName',
        '`img`'   => 'img',
        '`align`' => 'align',
        '`price`' => 'price'
    );

    private function __construct()
    {
        $dsn = "mysql:dbname=" . ParserDB::DB_NAME . ";host=" . ParserDB::HOST . ";charset=utf8";
        $options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);
        $this->_pdo = new PDO($dsn, ParserDB::USERNAME, ParserDB::PASSWORD, $options);
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }

    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new ParserDB();
        }
        return self::$_instance;
    }

    public function insertItem($item)
    {
        foreach ($item as $key => $value) {
            if ( !is_numeric($key) && in_array($key,$this->_DBItemFields,true)) {
                $filteredItem[$key] = $value;
            }
        }
        $dbFieldsStr = implode(",",array_keys($this->_DBItemFields));
        $itemKeyFieldsStr = ":" .implode(",:",array_values($this->_DBItemFields));

        $sql = "INSERT INTO `" . self::DB_TABLE . "` (" . $dbFieldsStr . ") VALUES (" . $itemKeyFieldsStr . ")";
        $preparedStmt = $this->_pdo->prepare( $sql );
        $preparedStmt->execute( $filteredItem );
    }
}

