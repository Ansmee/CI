<?php
/**
 *
 */
class MY_Model extends CI_Model
{
    protected static $fields = [];
    protected static $index = [];
    protected static $tableName = '';
    protected static $_db = null;
    function __construct()
    {
        parent::__construct();
    }

    public function initTable()
    {
        $tableName = static::$tableName;
        $fields    = static::$fields;

        if (!$tableName) return false;
        if (!is_array($fields) || !count($fields)) return false;

        $db = $this->_initDB();
        if (!$db) throw new \Exception("Database init failed");

        foreach ($fields as $name => $fieldInfo) {
            if (!$fieldInfo) continue;
            $fieldsSQLARR[] = $this->_getCreateSQL($name, $fieldInfo);
        }

        // 表不存在，创建表，否则更新字段
        if (!$this->_tableExists($tableName)) {
            $sql = "CREATE TABLE `{$tableName}` ";
            $fieldsSQLARR[] = "`id` bigint(20) NOT NULL AUTO_INCREMENT";
            $fieldsSQLARR[] = "PRIMARY KEY (`id`)";
            $fieldsSQL = implode(", ", $fieldsSQLARR);

            $sql .= '(' . $fieldsSQL . ') ENGINE=InnoDB DEFAULT CHARSET=utf8';
        } else {
            $currentFields  = $this->_getTableFields($tableName);
            if (!$currentFields) throw new \Exception("Unexpected Error");

            $currentIndexes = $this->_getTableIndexes($tableName);
            if (!$currentIndexes) throw new \Exception("Unexpected Error");
        }

        error_log($sql);
        // $res = @$db->query($sql);
        if (!$res) throw new \Exception("Query Error: {$sql}");

        return !! $res;
    }

    private function _initDB()
    {
        if (self::$_db) return self::$_db;

        $this->load->database();
        self::$_db = $this->db;
        if (self::$_db) return self::$_db;

        return false;
    }

    private function _tableExists($tableName)
    {
        $db = $this->_initDB();
        $databaseName = $db->database;

        $sql = "SELECT COUNT(1) as count  FROM `INFORMATION_SCHEMA`.`TABLES` WHERE `TABLE_SCHEMA`='$databaseName' and `TABLE_NAME`='{$tableName}'";
        $query = $db->query($sql);

        if (!$query) return false;
        $row = $query->row();

        return !! $row->count;
    }

    private function _getTableFields($tableName)
    {
        $db = $this->_initDB();

        $sql = "DESC `{$tableName}`";
        $query = $db->query($sql);
        if (!$query) return false;
        $result = $query->result();

        return $result;
    }

    private function _getTableIndexes($tableName)
    {
        $db = $this->_initDB();

        $sql = "SHOW INDEX FROM `{$tableName}`";
        $query = $db->query($sql);
        if (!$query) return false;
        $result = $query->result();

        return $result;
    }

    private function _getCreateSQL($fieldName, $fieldInfo)
    {
        $sqlArr[] = "`{$fieldName}`";
        $fieldArrs = explode(',', $fieldInfo);

        $typeInfo = array_shift($fieldArrs);
        $typeArr = explode(':', $typeInfo);
        @list($type, $length) = $typeArr;

        $default = $comment = '';
        foreach ($fieldArrs as $fieldArr) {
            @list($name, $value) = explode(':', $fieldArr);
            switch ($name) {
                case 'default':
                    $default = $value;
                    break;
                case 'comment':
                    $comment = $value;
            }
        }

        switch ($type) {
            case 'int':
                $length = (int)$length ? (int)$length : 11;
                $sqlArr[] = "int({$length}) NOT NULL DEFAULT '0'";
                break;
            case 'bigint':
                $length = (int)$length ? (int)$length : 11;
                $sqlArr[] = "int({$length}) NOT NULL DEFAULT '0'";
                break;
            case 'double':
                $sqlArr[] = "double NOT NULL DEFAULT '0'";
            case 'string':
                $length = (int)$length ? (int)$length : 50;
                $sqlArr[] = "varchar({$length}) NOT NULL DEFAULT ''";
                break;
            case 'bool':
                $sqlArr[] = "int(1) NOT NULL DEFAULT '{$default}'";
                break;
            case 'array':
                $textType = $this->_getTextType($length);
                $sqlArr[] = "{$textType} NOT NULL";
                break;
        }

        $sql = implode(' ', $sqlArr);
        return $sql;
    }

    private function _getTextType($length = '')
    {
        switch ($length) {
            case '*':
                $type = 'mediumtext';
                break;
            case '**':
                $type = 'longtext';
                break;
            default:
                $type = 'text';
                break;
        }

        return $type;
    }
}
