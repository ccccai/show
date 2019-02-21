<?php
/**
 * Created by PhpStorm.
 * User: cc
 * Date: 2018/5/10
 * Time: 10:02
 */
chdir(dirname(__FILE__));
include_once('./config.php');
$g_poDBConfig = CSys_Conf::$DB_Config;
$g_oDBConn = @new mysqli('p:' . $g_poDBConfig['server'], $g_poDBConfig['user'], $g_poDBConfig['psw'], $g_poDBConfig['database']);
$g_oDBConn->set_charset('utf8');
$list = [];

if($_SERVER['REQUEST_METHOD'] == 'GET'){
    $method = $_REQUEST['method'];
    if($method == "get_data"){
        if ($g_oDBConn->connect_error) {
            $list['status'] = -1;
            $list['msg'] = "连接数据库失败：" . $g_oDBConn->connect_error;
            echo json_encode($list);
            die();
        } else {
            $selectSql = "SELECT * FROM trade_order_list";
            $selectRes = $g_oDBConn->query($selectSql);
            $index = 0;
            $list['status'] = 1;
            $list['list'] = [];
            while ($oRow = $selectRes->fetch_array()) {
                // var_dump($oRow);
                $list['list'][$index]['id'] = $oRow[0];
                $list['list'][$index]['sn'] = $oRow[1];
                $list['list'][$index]['time'] = $oRow[2];
                $list['list'][$index]['amount'] = $oRow[3];
                $index++;
            }
            echo json_encode($list);
            die();
        }
    }
}