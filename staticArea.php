<?php
//地区-id关系聚合
chdir(dirname(__FILE__));
include_once('./config.php');
include_once('./qqwry.php');

$g_astrProvince = ['北京市','天津市','河北省','山西省','内蒙古',
    '辽宁省','吉林省','黑龙江省',
    '山东省','江苏省','安徽省','浙江省','福建省','上海市',
    '广东省','广西','海南省',
    '湖北省','湖南省','河南省','江西省',
    '宁夏','新疆','青海省','陕西省','甘肃省',
    '四川省','云南省','贵州省','西藏','重庆市',
    '辽宁省','吉林省','黑龙江',
    '台湾省','香港','澳门'];

$g_poDBConfig = CSys_Conf::$DB_Config;

$g_oDBConn = @new mysqli('p:'.$g_poDBConfig['server'],$g_poDBConfig['user'],$g_poDBConfig['psw'],$g_poDBConfig['database']);
$g_oDBConn->set_charset('utf8');
$g_strQuerySql = 'SELECT `LastIP`,`UId` FROM `RUserBase`';
$g_xResult = $g_oDBConn->query($g_strQuerySql);
if ($g_xResult === FALSE)
{
    echo '{"success":0,"error_code":'.ERR_INNER_CODE.',"error_desc":"'.ERR_INNER_DESC.'[2]"}';
    exit();
}
$g_mapProvince = [];//key-省名 value-映射的市名数组
$g_mapPC = [];//key-省名-市名 value-id数组
$g_mapIPtoAddr = [];//key-ip value-地址

$g_oIPQuery = new qqwry(dirname(__FILE__).'/qqwry.dat');


while ($oRow = $g_xResult->fetch_array())
{
    if ($oRow['LastIP'] == '')
        continue;
    $strCity = '';
    $strProvince = '';
    try
    {

        if (isset($g_mapIPtoAddr[$oRow['LastIP']]))
        {
            $strProvince = $g_mapIPtoAddr[$oRow['LastIP']]['p'];
            $strCity = $g_mapIPtoAddr[$oRow['LastIP']]['c'];
        }
        else
        {
            $strFullAddr = $g_oIPQuery->query($oRow['LastIP'])[0];

            foreach ($g_astrProvince as $strProvince)
            {
                $nPos = strpos($strFullAddr,$strProvince);
                if ($nPos === 0)
                {
                    $strProvince = $strProvince;
                    $strFullAddr = substr($strFullAddr,strlen($strProvince));
                    break;
                }
            }
            $nPos = strpos($strFullAddr,'市');
            if ($nPos !== false)
                $strCity = substr($strFullAddr,0,$nPos);
            $g_mapIPtoAddr[$oRow['LastIP']] = array('p'=>$strProvince,'c'=>$strCity);
        }
    }
    catch (Exception $oErr)
    {
        continue;
    }
    if ($strProvince == '' && $strCity == '') //不考虑国外地区
        continue;
    $g_mapProvince[$strProvince] = [];
    if ($strCity != '')
    {
        $g_mapProvince[$strProvince][] = $strCity;
        if (isset($g_mapPC[$strProvince.'-'.$strCity]))
            $g_mapPC[$strProvince.'-'.$strCity][] = $oRow['UId'];
        else
            $g_mapPC[$strProvince.'-'.$strCity] = [$oRow['UId']];
    }
    else //只有省的情况
    {
        if (isset($g_mapPC[$strProvince.'- ']))
            $g_mapPC[$strProvince.'- '][] = $oRow['UId'];
        else
            $g_mapPC[$strProvince.'- '] = [$oRow['UId']];
    }
}
//组拼输出数据
$nAllocId = 1;
$strReturn = '{"success":1,"area":[';
$aoIds = [];
$nProvinceCount = 0;
foreach ($g_mapProvince as $strProvince=>$astrCitys)
{
    $oPCombine = array('text'=>$strProvince);
    if (isset($g_mapPC[$strProvince.'- ']) == true)
        $aoIds[$nAllocId] = $g_mapPC[$strProvince.'- '];
    $oPCombine['id'] = $nAllocId;
    ++$nAllocId;
    if (count($astrCitys) > 0)
    {
        $oPCombine['children'] = [];
        for ($nScan = 0;$nScan < count($astrCitys);++$nScan)
        {
            $oPCombine['children'][] = array('text'=>$astrCitys[$nScan],'id'=>$nAllocId);
            $aoIds[$nAllocId] = $g_mapPC[$strProvince.'-'.$astrCitys[$nScan]];
            ++$nAllocId;
        }
    }
    if ($nProvinceCount == 0)
        $strReturn .= json_encode($oPCombine,JSON_UNESCAPED_UNICODE);
    else
        $strReturn .= ','.json_encode($oPCombine,JSON_UNESCAPED_UNICODE);
    ++$nProvinceCount;
}
$strReturn .= '],"ids":'.json_encode($aoIds).'}';
echo $strReturn;