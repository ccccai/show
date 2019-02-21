<?php
/*
	数据统计
	POST['ids'] - 要统计的机器人用户id集合，json数组
	POST['start'] - 开始时间的时间戳(到日，处理时自动调整到当天开始时点)
	POST['end'] - 结束时间的时间戳(到日，处理时自动调整到当天开始时点)
	POST['timeRange'] - 时间段数组，单个区间使用【开始时间点秒数+":"+结束时间点秒数】
*/

if (isset($_POST['ids']) == false)
{
    echo '{"success":0,"error":"参数错误"}';
    return;
}
if (isset($_POST['start']) == false || isset($_POST['end']) == false)
{
    echo '{"success":0,"error":"参数错误"}';
    return;
}
if (isset($_POST['timeRange']) == false || is_array($_POST['timeRange']) == false)
{
    echo '{"success":0,"error":"参数错误"}';
    return;
}
$_POST['ids'] = @json_decode($_POST['ids']);
if ($_POST['ids'] == null || $_POST['ids'] == false)
{
    echo '{"success":0,"error":"参数错误"}';
    return;
}
$_POST['start'] = intval(intval($_POST['start']) / 24) * 24;
$_POST['end'] = intval(ceil(intval($_POST['end']) / 24)) * 24;
if ($_POST['start'] > $_POST['end'])
{
    echo '{"success":0,"error":"时间区间错误"}';
    return;
}
$aoTimeRange = [];
foreach ($_POST['timeRange'] as $strTimeGap)
{
    $astrGap = explode(':',$strTimeGap);
    if (count($astrGap) != 2)
        continue;
    $aoTimeRange[] = array(intval($astrGap[0]),intval($astrGap[1]));
}
chdir(dirname(__FILE__));
include_once('./config.php');
$g_poDBConfig = CSys_Conf::$DB_Config;

$g_oDBConn = @new mysqli('p:'.$g_poDBConfig['server'],$g_poDBConfig['user'],$g_poDBConfig['psw'],$g_poDBConfig['database']);
$g_oDBConn->set_charset('utf8');

$anIds = [];
//	$strBaseSql = 'SELECT `Name`,`OpTime`,`OpCode`,lv.Description FROM `OpReport` LEFT JOIN LookUpValue lv ON lv.`Value`=OpReport.`Name` WHERE OpReport.Name<>"SYSTEM_START_QUIT" AND (`OpCode`=1 OR `OpCode`=2) AND `OpTime`>='.$_POST['start'].' AND `OpTime`<'.$_POST['end'].' AND `BindRUId` IN (';
$strBaseSql = 'SELECT `Name`,`OpTime`,`OpCode`,lv.Description FROM `OpReport` LEFT JOIN LookUpValue lv ON lv.`Value`=OpReport.`Name` WHERE OpReport.Name IN (SELECT `Value` FROM LookUpValue lv WHERE lv.Subcategory IS NULL AND lv.Value<>\'SYSTEM_START_QUIT\') AND (`OpCode`=1 OR `OpCode`=2) AND `OpTime`>='.$_POST['start'].' AND `OpTime`<'.$_POST['end'].' AND `BindRUId` IN (';
$nTotalCount = 0;
$mapSummarize = [];//key-上报名 value-{key-日期时间戳 value-单日统计}
$funcQuery = function(&$mapSummarize,$poDBConn,$strSql,$aoTimeRange)
{
    $xResult = $poDBConn->query($strSql);
    if ($xResult === FALSE)
    {
        echo '{"success":0,"error_code":'.ERR_INNER_CODE.',"error_desc":"'.ERR_INNER_DESC.'[1]"}';
        exit();
    }
    $nNodeStartAdjust = -1;
    $nNodeEndAdjust = -1;
    $nNodeStartOrg = -1;
    $nNodeEndOrg = -1;
    $strCurName = '';
    $nTmpAnother = -1;
    while ($oRow = $xResult->fetch_array())
    {
        if ($strCurName != $oRow['Description'])
        {
            $nNodeStartAdjust = -1;
            $nNodeEndAdjust = -1;
            $nNodeStartOrg = -1;
            $nNodeEndOrg = -1;
            $strCurName = $oRow['Description'];
        }
        if ($oRow['OpCode'] == 1)
        {
            if ($nNodeEndAdjust > -1)
            {
                $nNodeEndAdjust = -1;
                $nNodeEndOrg = -1;
            }
            $nNodeStartAdjust = $oRow['OpTime'];
            $nNodeStartOrg = $nNodeStartAdjust;
        }
        else
        {
            $nNodeEndAdjust = $oRow['OpTime'];
            $nNodeEndOrg = $nNodeEndAdjust;
        }
        if ($nNodeStartAdjust == -1 || $nNodeEndAdjust == -1)
            continue;

        //调整开始区间
        $nTmpTime = ($nNodeStartAdjust % 86400 + 28800) % 86400;
        $anTimes = [];
        foreach ($aoTimeRange as $poGap)
        {
            if ($nTmpTime >= $poGap[0] && $nTmpTime < $poGap[1])
            {
                $anTimes[] = $nNodeStartAdjust;
                break;
            }
            else if ($nTmpTime < $poGap[0])
                $anTimes[] = $nNodeStartAdjust + $poGap[0] - $nTmpTime;
            else
                $anTimes[] = $nNodeStartAdjust - $nTmpTime + 86400 + $poGap[0];//天数后移1天
        }
        $nNodeStartAdjust = min($anTimes);
        //调整结束区间
        $nTmpTime = ($nNodeEndAdjust % 86400 + 28800) % 86400;
        $anTimes = [];
        foreach ($aoTimeRange as $poGap)
        {
            if ($nTmpTime >= $poGap[0] && $nTmpTime < $poGap[1])
            {
                $anTimes[] = $nNodeEndAdjust;
                break;
            }
            else if ($nTmpTime < $poGap[0])
                $anTimes[] = $nNodeEndAdjust - $nTmpTime - 86400 + $poGap[1];//天数前移1天
            else
                $anTimes[] = $nNodeEndAdjust - $nTmpTime + $poGap[1];
        }
        $nNodeEndAdjust = max($anTimes);
        //统计
        $nCurTimeArea = $nNodeStartAdjust;
        $nCurTimeOrg = $nNodeStartOrg;
        $nAddTimeBase = $nNodeStartOrg - (($nNodeStartOrg % 86400 + 28800) % 86400);
        while ($nCurTimeOrg < $nNodeEndOrg)
        {
            //区间分割
            if ($nNodeStartAdjust < $nNodeEndAdjust)
            {
                foreach ($aoTimeRange as $poGap)
                {
                    $nGapStart = $poGap[0] + $nAddTimeBase;
                    $nGapEnd = $poGap[1] + $nAddTimeBase;
                    $nInGapStartTime = $nCurTimeArea;
                    $nInGapEndTime = $nNodeEndAdjust;
                    if ($nGapStart > $nInGapStartTime)
                        $nInGapStartTime = $nGapStart;
                    if ($nGapEnd < $nInGapEndTime)
                        $nInGapEndTime = $nGapEnd;
                    if ($nInGapStartTime >= $nInGapEndTime)
                        continue;
                    if (isset($mapSummarize[$strCurName]) == false)
                        $mapSummarize[$strCurName] = [];
                    if (isset($mapSummarize[$strCurName][$nAddTimeBase]) == false)
                        $mapSummarize[$strCurName][$nAddTimeBase] = array('area'=>0,'full'=>0);
                    $mapSummarize[$strCurName][$nAddTimeBase]['area'] += $nInGapEndTime - $nInGapStartTime;
                }
            }
            //全日计算
            if ($nNodeEndOrg > $nNodeStartOrg)
            {
                if ($nNodeEndOrg <= $nAddTimeBase + 86400)
                    $nFullCount = $nNodeEndOrg;
                else
                    $nFullCount = $nAddTimeBase + 86400;
                if ($nNodeStartOrg < $nAddTimeBase)
                    $nFullCount -= $nAddTimeBase;
                else
                    $nFullCount -= $nNodeStartOrg;
                if ($nFullCount > 0)
                {
                    if (isset($mapSummarize[$strCurName]) == false)
                        $mapSummarize[$strCurName] = [];
                    if (isset($mapSummarize[$strCurName][$nAddTimeBase]) == false)
                        $mapSummarize[$strCurName][$nAddTimeBase] = array('area'=>0,'full'=>0);
                    $mapSummarize[$strCurName][$nAddTimeBase]['full'] += $nFullCount;
                }
            }

            $nAddTimeBase += 86400;
            $nCurTimeArea = $nAddTimeBase;
            $nCurTimeOrg = $nAddTimeBase;
        }
    }
};
foreach ($_POST['ids'] as $nId)
{
    ++$nTotalCount;
    $nId = intval($nId);
    $anIds[] = $nId;
    if (count($anIds) > 200 || $nTotalCount == count($_POST['ids']))
    {
        $funcQuery($mapSummarize,$g_oDBConn,$strBaseSql.implode(',',$anIds).') ORDER BY `BindRUId` ASC,`Name` ASC,`OpTime` ASC',$aoTimeRange);
        $anIds = [];
    }
}
function d($string)
{
    print_r('<pre>');
    print_r($string);
    print_r('</pre>');
    die();
}
//    查询一段时间内的开机数数量
$sql =  "SELECT count(DISTINCT t.UserId) as count,t.OpTime,datetime FROM (SELECT UserId,OpTime,FROM_UNIXTIME(OpTime, '%Y-%m-%d') as datetime FROM StatisticOperation WHERE Platform=100 AND LookUpFunctionValueId=5 AND OpTime>".$_POST['start']." AND OpTime<".$_POST['end']." ) as t GROUP BY t.datetime";
$xResult = $g_oDBConn->query($sql);
$data = [];
while($result = $xResult->fetch_assoc())
{
    $data[] = $result;
}
$resY = [];
$res = [];
foreach ($data as $value)
{
    $res[strtotime($value['datetime']) - 28800] = $value['count'];
    $resY = $res;
}
//	查询机器人总数
$countRobotSql = "SELECT count(*) as count FROM RUserBase";
$countRobot = $g_oDBConn->query($countRobotSql);
$robotCount = $countRobot->fetch_assoc()['count'];
$outX = [];
$outY = [];
$start = $_POST['start'];
$end = $_POST['end'];
while ($_POST['start'] < $_POST['end']){
    if (array_key_exists($_POST['start'],$resY))
    {
        $outY[] =  round( ($resY[$_POST['start']] / $robotCount) * 100,2);
        $countY = $resY[$_POST['start']];
    }else{
        $outY[] = 0;
        $countY = 0;
    }
    $outX[] = date("m月d日",$_POST['start']+28800)."(活跃数：".$countY.")";
    $_POST['start'] += 86400;
}

$out['x'] = $outX;
$out['y'] = $outY;

//筛选出使用次数每天的占比
$sql = "SELECT so.*,lv.Description,FROM_UNIXTIME(so.OpTime - 28800,'%m月%d日') as datetime,COUNT(Description) as count FROM StatisticOperation so 
                LEFT JOIN LookUpValue lv 
                ON so.LookUpFunctionValueId=lv.UId 
                WHERE so.LookUpFunctionValueId<>5 
                AND so.OperationCode='IN' 
                AND so.OpTime>".intval($start)." 
                AND so.OpTime<".intval($end)." 
                GROUP BY Description,datetime";
$useList = $g_oDBConn->query($sql);

while ($list = $useList->fetch_assoc())
{
    $list['OpTime'] = strtotime(date('Y-m-d',$list['OpTime'])) - 28800;
    $useData[] = $list;
}
$data = [];
foreach ($useData as $key=>$item)
{
    $data[$item['datetime']][$item['Description']] = $item['count'];
}
$a = [];
//    $LooksSql = "select UId,`Value`,Description FROM LookUpValue lv WHERE lv.Uid<>5 AND lv.Uid<>44 AND lv.TypeCode='FUNCTION'";
$LooksSql = "select UId,`Value`,Description FROM LookUpValue lv WHERE lv.Uid<>5 AND lv.Uid<>44 AND lv.TypeCode='FUNCTION' AND lv.Subcategory IS NULL";

$LooksRes = $g_oDBConn->query($LooksSql);
$looks = [];
while ($Look = $LooksRes->fetch_assoc()){
    $looks[$Look['UId']] =  $Look;
}
$i = 0;
foreach ($looks as $key=>$item){
    $lookY[$i]['name'] = $item['Description'];
    $lookY[$i]['data'] = [];
    $resLoop = doLoop($start,$end,$item,$data);
    $lookY[$i]['data'] = $resLoop['y'][$item['Description']];
    $lookX = $resLoop['x'];
    $i++;
}
function dayCount($data,$start)
{
    $count = 0;
    foreach ($data[$start] as $value){
        $count += $value['count'];
    }
    return $count;
}
function doLoop($start,$end,$app,$collections){
    while ($start < $end){
        $lookX[] = date("m月d日",$start+28800);
        if (array_key_exists(date("m月d日",$start+28800),$collections))
        {
            $count = array_sum($collections[date("m月d日",$start+28800)]);
            if (array_key_exists($app['Description'],$collections[date("m月d日",$start+28800)])){
                $useCounts[$app['Description']][] = round( intval($collections[date("m月d日",$start+28800)][$app['Description']]) * 100 / $count,2);
            }else{
                $useCounts[$app['Description']][] = 0;
            }
        }else{
            $useCounts[$app['Description']][] = 0;
        }
        $start += 86400;
    }
    $res['x'] = $lookX;
    $res['y'] = $useCounts;
    return $res;
}
function doXunhuan($start,$end,$key,$data){
    $lookX = [];
    while ($start < $end){
        $lookX[] = date("m月d日",$start+28800);
        if (array_key_exists($start,$data))
        {
            $count = dayCount($data,$start);
            if (array_key_exists($key,$data[$start])){
                $res[] = round( intval($data[$start][$key]['count']) * 100 / $count,2);
            }else{
                $res[] = 0;
            }
        }else{
            $res[] = 0;
        }
        $start += 86400;
    }
    $result['y'] = $res;
    $result['x'] = $lookX;
    return $result;
}
$res = [];
$res['lookX'] = $lookX;
$res['lookY'] = $lookY;

//一段时间内的个软件使用次数占比
$someTimeSql = "SELECT so.*,lv.Description,FROM_UNIXTIME(so.OpTime,'%m月%d日') as datetime,COUNT(LookUpFunctionValueId) as count FROM StatisticOperation so 
                    LEFT JOIN LookUpValue lv 
                    ON so.LookUpFunctionValueId=lv.UId 
                    WHERE so.LookUpFunctionValueId<>5 
                    AND lv.Subcategory IS NULL 
                    AND so.OperationCode IN('IN','ONCE') 
                    AND so.OpTime>".intval($start)." 
                    AND so.OpTime<".intval($end)." 
                    GROUP BY LookUpFunctionValueId";
$someTime = $g_oDBConn->query($someTimeSql);
while ($v = $someTime->fetch_assoc())
{
    $someTimes[$v['LookUpFunctionValueId']] = $v;
}
$allCounts = 0;
foreach ($someTimes as $v)
{
    $allCounts += $v['count'];
}
$someTimeRes = [];
$transItem = [];
foreach ($looks as $key=>$value)
{
    if (array_key_exists($key,$someTimes))
    {
        $transItem[$key][] = $value['Description'].round($someTimes[$key]['count'] * 100 / $allCounts,2)."%";
        $transItem[$key][] = round($someTimes[$key]['count'] ,2);
    }else{
        $transItem[$key][] = $value['Description']."0%";
        $transItem[$key][] = 0;
    }
    $someTimeRes[] = $transItem[$key];
}
/**
 *  手机使用率
 */
$sql = "SELECT count(*) as count,t.OpTime FROM 
                    (SELECT *,FROM_UNIXTIME(OpTime, '%m月%d日') as datetime FROM StatisticOperation 
                    LEFT JOIN LookUpValue lv ON lv.UId=StatisticOperation.Platform
                    WHERE OpTime>$start 
                    AND OpTime<$end 
                    AND lv.`Value` in ('IOS','ANDROID')
                    GROUP BY UserId,datetime) as t 
                    GROUP BY t.datetime";
$xResult = $g_oDBConn->query($sql);
$data = [];
while($result = $xResult->fetch_assoc())
{
    $result['OpTime'] = strtotime(date("Y-m-d",$result['OpTime']+28800)) -28800 ;
    $data[] = $result;
}
$mobileY = [];
$mobile = [];
foreach ($data as $value)
{
    $mobile[$value['OpTime']] = $value['count'];
    $mobileY = $mobile;
}
//	查询手机用户总数
$countRobotSql = "SELECT count(*) as count FROM MUserBase";
$countRobot = $g_oDBConn->query($countRobotSql);
$robotCount = $countRobot->fetch_assoc()['count'];
$outX = [];
$outY = [];
while ($start < $end){
    if (array_key_exists($start,$mobileY))
    {
        $outY[] =  round( ($mobileY[$start] / $robotCount) * 100,2);
        $outX[] = date("m月d日",$start+28800)."(活跃数:".$mobileY[$start].")";
    }else{
        $outY[] = 0;
        $outX[] = date("m月d日",$start+28800)."(活跃数:0)";
    }
    $start += 86400;
}
$mobile = [];
$mobile['x'] = $outX;
$mobile['y'] = $outY;
echo '{"success":1,"someTime":'.json_encode($someTimeRes,JSON_UNESCAPED_UNICODE).',"openRate":'.json_encode($out,JSON_UNESCAPED_UNICODE).',"mobile":'.json_encode($mobile,JSON_UNESCAPED_UNICODE).',"look":'.json_encode($res,JSON_UNESCAPED_UNICODE).',"statics":'.json_encode($mapSummarize,JSON_UNESCAPED_UNICODE).'}';