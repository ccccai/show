<?php
/**
 * Created by PhpStorm.
 * User: xu
 * Date: 2017/7/2
 * Time: 下午11:45
 */
chdir(dirname(__FILE__));
include_once('./config.php');

$g_poDBConfig = CSys_Conf::$DB_Config;

$g_oDBConn = @new mysqli('p:'.$g_poDBConfig['server'],$g_poDBConfig['user'],$g_poDBConfig['psw'],$g_poDBConfig['database']);
$g_oDBConn->set_charset('utf8');
$g_strQuerySql = 'SELECT lv.Subcategory from LookUpValue lv WHERE lv.Subcategory is not null GROUP BY lv.Subcategory';
$g_xResult = $g_oDBConn->query($g_strQuerySql);
$data = [];
$opt = [];
$i = 0;
while ($res = $g_xResult->fetch_assoc()){
    $data[] = $res['Subcategory'];
    $opt[$i]['name'] =  $res['Subcategory'];
    $opt[$i]['text'] =  $res['Subcategory'];
    $opt[0]['selected'] =  $res['Subcategory'];
    $i++;
}
$output['success'] = 1;
$output['list'] = $data;

echo json_encode($opt);