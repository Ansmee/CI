<?php
/**
 *
 */
class User extends MY_Model
{
    // 设置对应的表明
    protected static $tableName = 'user';

    //初始化数据表字段
    protected static $fields = [
        'name' => 'string:100,comment:用户名',
        'sex'  => 'int,default:0,comment:性别',
        'age'  => 'int,comment:年龄',
        'idnumber'=> 'int,comment:身份证号',
        'summary' => 'array:*,comment:简介',
        'info'    => 'array:**,comment:简介',
        'address' => 'string:100,comment:地址',
        'phone'   => 'bigint:20,comment:联系电话'
    ];

    protected static $indexes = [
        'unique:idnumber',
        'name,age'
    ];

    public function getInfo()
    {
        return ['Ansme', '25'];
    }
}
