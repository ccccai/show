<?php
header('Access-Control-Allow-Origin:*');
chdir(dirname(__FILE__));
include_once('./config.php');
$g_poDBConfig = CSys_Conf::$DB_Config;

$g_oDBConn = @new mysqli('p:' . $g_poDBConfig['server'], $g_poDBConfig['user'], $g_poDBConfig['psw'], $g_poDBConfig['database']);
$g_oDBConn->set_charset('utf8');

//所有app
$allAppSql = "select so.LookUpFunctionValueId,lv.Description
	from StatisticOperation so LEFT JOIN LookUpValue lv on so.LookUpFunctionValueId = lv.UId
	where so.OperationCode='IN'
	and so.LookUpFunctionValueId <> 5
	and so.LookUpFunctionValueId <> 4
	and lv.Subcategory IS NULL
	and lv.Description IS NOT NULL
	group by LookUpFunctionValueId";
$xResultApp = $g_oDBConn->query($allAppSql);

if ($xResultApp === FALSE)
{
    echo '{"success":0,"error":"app数据查询失败"}';
    exit();
}else{
    $res = [];
    $index = 0;
    while ($appRow = $xResultApp->fetch_array()) {
//        $res[$index]["app_id"] = $appRow["LookUpFunctionValueId"];
        $res[$appRow["LookUpFunctionValueId"]] = $appRow["Description"];
        $index++;
    }
}
echo '{"success":1,"allApps":'.json_encode($res,JSON_UNESCAPED_UNICODE).'}';