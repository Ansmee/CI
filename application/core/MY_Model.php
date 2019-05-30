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
            $fieldStructure[] = "`id` bigint(20) NOT NULL AUTO_INCREMENT";
            $fieldStructure[] = "PRIMARY KEY (`id`)";

            $fieldStructure = $this->_makeFieldStructure($fields);
            $indexStructure = $this->_makeIndexStucture($indexes);

            $fieldSQL = implode(", ", $fieldStructure);
            $indexSQL  = implode(", ", $indexStructure);
            $sql = "CREATE TABLE `{$tableName}` ( {$fieldSQL}, {$indexSQL} )  ENGINE=InnoDB DEFAULT CHARSET=utf8";
        } else {
            $tableFields  = $this->_getTableFields($tableName);
            if (!$tableFields) throw new \Exception("Unexpected Error");
            $changedFields = $this->_getChangedFields($fields, $tableFields);
            $newFields = $changedFields['new'];
            $modifiedFields = $changedFields['modified'];
            if (count($newFields)) {
                $fieldStructure = $this->_makeFieldStructure($newFields);
                $fieldSQL = implode(", ", $fieldStructure);
            }

            if (count($modifiedFields)) {
                $fieldStructure = $this->_makeFieldStructure($modifiedFields);
                $fieldSQL = implode(", ", $fieldStructure);
            }
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
        $newFields = [];
        $modifiedFields = [];
        foreach ($fields as $name => $fieldInfo) {
            if (!$fieldInfo) continue;
            $fieldAnalyzes = $this->_analyzeField($fieldInfo);
            if (!isset($tableFields[$name])) {
                $newFields[$name] = $fieldInfo;
                continue;
            }

            if ($this->_hasChanged($fieldAnalyzes, $tableFields[$name])) {
                $modifiedFields[$name] = $fieldInfo;
                continue;
            }
        }

        return [
            'new'      => $newFields,
            'modified' => $modifiedFields
        ];
    }

    private function _hasChanged($fieldInfo, $tableFieldInfo)
    {
        if ($fieldInfo['type'] != $tableFieldInfo['type']) {
            return true;
        }

        if ($fieldInfo['length'] != $tableFieldInfo['length']) {
            return true;
        }

        if ($fieldInfo['default'] != $tableFieldInfo['default']) {
            return true;
        }

        return false;
    }

    private function _typeFormate($type)
    {
        $enableTypes = [
            'int'     => 'int',
            'bigint'  => 'bigint',
            'varchar' => 'string',
            'double'  => 'double',
            'text'    => 'array',
            'mediumtext' => 'array',
            'longtext'   => 'array'
        ];

        return isset($enableTypes[$type]) ? $enableTypes[$type] : false;
    }

    private function _lengthFormate($type, $length = '')
    {
        $length = rtrim($length, ')');

        if ($type == 'text') {
            $length = '*';
        }

        if ($type == 'mediumtext') {
            $length = '**';
        }

        if ($type == 'longtext') {
            $length = '***';
        }

        switch ($type) {
            case 'text':
                $length = '*';
                break;
            case 'mediumtext':
                $length = '**';
                break;
            case 'longtext':
                $length = '***';
                break;
        }

        return $length;
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
            $typeAndLength = explode('(', $field->Type);
            $type    = $this->_typeFormate($typeAndLength[0]) ?: $typeAndLength[0];
            $length  = $this->_lengthFormate($typeAndLength[0], isset($typeAndLength[1]) ? $typeAndLength[1] : '');
            $default = $field->Default;
            $results[$name] = [
                'type'    => $type,
                'length'  => $length,
                'default' => $default
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

        $sqlArr[] = "`{$fieldName}`";
        switch ($type) {
            case 'int':
                $length = (int)$length ? (int)$length : 11;
                $sqlArr[] = "int({$length}) NOT NULL DEFAULT '{$default}'";
                break;
            case 'bigint':
                $length = (int)$length ? (int)$length : 11;
                $sqlArr[] = "int({$length}) NOT NULL DEFAULT '{$default}'";
                break;
            case 'double':
                $sqlArr[] = "double NOT NULL DEFAULT '{$default}'";
            case 'string':
                $length = (int)$length ? (int)$length : 50;
                $sqlArr[] = "varchar({$length}) NOT NULL DEFAULT '{$default}'";
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

    // TODO default comment 需要调整
    private function _analyzeField($fieldInfo)
    {
        $fieldArrs = explode(',', $fieldInfo);

        $typeInfo = array_shift($fieldArrs);
        $typeArr = explode(':', $typeInfo);
        @list($type, $length) = $typeArr;

        switch ($type) {
            case 'int':
                $length = 11;
                $default = 0;
            case 'bigint':
                $length = $length ?: 20;
                $default = 0;
            case 'double':
                $default = 0;
                break;
            case 'string':
                $length = $length ?: 20;
                $default = '';
            default:
                $default = '';
                break;
        }

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

        return $data;
    }

    private function _getTextType($length = '')
    {
        switch ($length) {
            case '*':
                $type = 'text';
                break;
            case '**':
                $type = 'mediumtext';
                break;
            case '***':
                $type = 'longtext';
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
