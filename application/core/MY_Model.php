<?php
/**
 *
 */
class MY_Model extends CI_Model
{
    protected static $fields = [];
    protected static $indexes = [];
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
        $indexes   = static::$indexes;

        if (!$tableName) return false;
        if (!is_array($fields) || !count($fields)) return false;

        $db = $this->_initDB();
        if (!$db) throw new \Exception("Database init failed");

        // 表不存在，创建表，否则更新字段
        $sql = '';
        if (!$this->_tableExists($tableName)) {
            $fieldStructure = $this->_makeFieldStructure($fields);
            $indexStructure = $this->_makeIndexStucture($indexes);

            $fieldSQL = implode(", ", $fieldStructure);
            $indexSQL  = implode(", ", $indexStructure);
            $sql = "CREATE TABLE `{$tableName}` ( {$fieldSQL}, {$indexSQL} )  ENGINE=InnoDB DEFAULT CHARSET=utf8";
        } else {
            $tableFields  = $this->_getTableFields($tableName);
            if (!$tableFields) throw new \Exception("Unexpected Error");
            $changedFields = $this->_getChangedFields($fields, $tableFields);
            error_log(json_encode($changedFields));
        }

        if ($sql) {
            $res = @$db->query($sql);
            if (!$res) throw new \Exception("Query Error: {$sql}");
        }

        return true;
    }

    private function _makeFieldStructure($fields)
    {
        foreach ($fields as $name => $fieldInfo) {
            if (!$fieldInfo) continue;
            $fieldStructure[] = $this->_getFieldStruct($name, $fieldInfo);
        }

        $fieldStructure[] = "`id` bigint(20) NOT NULL AUTO_INCREMENT";
        $fieldStructure[] = "PRIMARY KEY (`id`)";

        return $fieldStructure;
    }

    private function _makeIndexStucture($indexes)
    {
        $indexStructure = [];
        if (!count($indexes)) return $indexStructure;

        foreach ($indexes as $indexeInfo) {
            $tmpIndexStructrues[] = explode(':', $indexeInfo);
        }

        $num = 0;
        $indexPrefix = '_MIDX_';
        foreach ($tmpIndexStructrues as $tmpIndexStructrue) {
            $isUnique = in_array('unique', $tmpIndexStructrue) ? true : false;
            $indexArrs = explode(',', end($tmpIndexStructrue));
            $indexArrs = array_map(function($value){
                return "`{$value}`";
            }, $indexArrs);
            $indexFields = implode(',', $indexArrs);
            $indexName = $indexPrefix . $num;
            $indexStructure[] = $isUnique ? "UNIQUE KEY `{$indexName}` ({$indexFields})" : "KEY `{$indexName}` ({$indexFields})";
            $num ++;
        }

        return $indexStructure;
    }

    private function _getChangedFields($fields, $tableFields)
    {
        error_log(json_encode($tableFields, JSON_UNESCAPED_UNICODE));
        foreach ($fields as $name => $fieldInfo) {
            if (!$fieldInfo) continue;
            $analyzeArr = $this->_analyzeField($fieldInfo);
            error_log(json_encode($analyzeArr, JSON_UNESCAPED_UNICODE));
        }
    }

    private function _getTableFields($tableName)
    {
        $db = $this->_initDB();

        $sql = "DESC `{$tableName}`";
        $query = $db->query($sql);
        if (!$query) return false;
        $fields = $query->result();

        $results = [];
        foreach ($fields as $field) {
            $name = $field->Field;
            list($type, $length) = explode('(', $field->Type);
            $results[$name] = [
                'type'    => $type,
                'length'  => rtrim($length, ')'),
                'default' => $field->Default
            ];
        }

        return $results;
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

    private function _getFieldStruct($fieldName, $fieldInfo)
    {
        $analyzeArr = $this->_analyzeField($fieldInfo);
        $type    = $analyzeArr['type'];
        $length  = $analyzeArr['length'];
        $default = $analyzeArr['default'];
        $comment = $analyzeArr['comment'];

        $sqlArr[] = "`{$fieldName}`";
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

    private function _analyzeField($fieldInfo)
    {
        $fieldArrs = explode(',', $fieldInfo);

        $typeInfo = array_shift($fieldArrs);
        $typeArr = explode(':', $typeInfo);
        @list($type, $length) = $typeArr;

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

        $data['type'] = $type;
        $data['length'] = $length;
        $data['default'] = $default;
        $data['comment'] = $comment;

        return $data;
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
}
