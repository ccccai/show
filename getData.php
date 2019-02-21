<?php
header('Access-Control-Allow-Origin:*');
chdir(dirname(__FILE__));
include_once('.//config.php');
include_once('./qqwry.php');

$g_poDBConfig = CSys_Conf::$DB_Config;

$g_oDBConn = @new mysqli('p:'.$g_poDBConfig['server'],$g_poDBConfig['user'],$g_poDBConfig['psw'],$g_poDBConfig['database']);
$g_oDBConn->set_charset('utf8');
$type = isset($_GET['type']) ? $_GET['type'] : 1;
$where = "WHERE `IsOnline` = " . $type ;
if ($type == 0)
    $where = '';
$g_strQuerySql = 'SELECT `LastIP`,COUNT(`LastIP`) CountIP FROM `RUserBase` '.$where.' GROUP BY `LastIP`';
$g_xResult = $g_oDBConn->query($g_strQuerySql);
if ($g_xResult === FALSE)
{
    echo '{"success":0,"error_code":'.ERR_INNER_CODE.',"error_desc":"'.ERR_INNER_DESC.'[2]"}';
    exit();
}
$g_mapAddrCount = [];//key-地名 value-统计值

$g_astrProvinceDrop = ['河北省','山西省','内蒙古',
    '辽宁省','吉林省','黑龙江省',
    '山东省','江苏省','安徽省','浙江省','福建省',
    '广东省','广西','海南省',
    '湖北省','湖南省','河南省','江西省',
    '宁夏','新疆','青海省','陕西省','甘肃省',
    '四川省','云南省','贵州省','西藏',
    '辽宁省','吉林省','黑龙江',
    '台湾省'];
$g_astrCitySp = ['北京','天津','上海','重庆','香港','澳门'];
$g_oIPQuery = new qqwry(dirname(__FILE__).'/qqwry.dat');
while ($oRow = $g_xResult->fetch_array())
{
    if ($oRow['LastIP'] == '')
        continue;
    try
    {
        $strFullAddr = $g_oIPQuery->query($oRow['LastIP'])[0];
    }
    catch (Exception $oErr)
    {
        continue;
    }
    //去除省份
    foreach ($g_astrProvinceDrop as $strDropProvince)
    {
        $nPos = strpos($strFullAddr,$strDropProvince);
        if ($nPos === 0)
        {
            $strFullAddr = substr($strFullAddr,strlen($strDropProvince));
            break;
        }
    }
    //挖出地区
    $strArea = '';
    foreach ($g_astrCitySp as $strSpCity)
    {
        $nPos = strpos($strFullAddr,$strSpCity);
        if ($nPos === 0)
        {
            $strArea = substr($strFullAddr,0,strlen($strSpCity));
            break;
        }
    }
    if ($strArea == '')
    {
        $nPos = strpos($strFullAddr,'市');
        if ($nPos !== false)
            $strArea = substr($strFullAddr,0,$nPos);
        else
            $strArea = $strFullAddr;
    }
    if ($strArea == '')
        continue;//只有省的情况直接忽略
    if (isset($g_mapAddrCount[$strArea]) == false)
        $g_mapAddrCount[$strArea] = 0;
    $g_mapAddrCount[$strArea] += $oRow['CountIP'];
}
echo '{"success":1,"data":'.json_encode($g_mapAddrCount,JSON_UNESCAPED_UNICODE).'}';