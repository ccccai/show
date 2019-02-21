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

function d($string)
{
    print_r('<pre>');
    print_r($string);
    print_r('</pre>');
    die();
}
$start = $_POST['start'];
$end = $_POST['end'];
$cate_name = $_POST['cate_name'];
//筛选出使用次数每天的占比
$sql = "SELECT so.*,lv.Description,FROM_UNIXTIME(so.OpTime,'%m月%d日') as datetime,COUNT(Description) as count FROM StatisticOperation so 
LEFT JOIN LookUpValue lv 
ON so.LookUpFunctionValueId=lv.UId 
WHERE so.LookUpFunctionValueId<>5 
AND lv.Subcategory='".$cate_name."'
AND so.OperationCode='IN' 
AND so.OpTime>".$start." 
AND so.OpTime<".$end." 
GROUP BY Description,datetime";
$useList = $g_oDBConn->query($sql);
$useData = [];
while ($list = $useList->fetch_assoc())
{
    $list['OpTime'] = strtotime(date('Y-m-d',$list['OpTime']));
    $useData[] = $list;
}
$data = [];
foreach ($useData as $key=>$item)
{
    $data[$item['OpTime']][$item['LookUpFunctionValueId']] = $item;
}
$a = [];
$LooksSql = "select UId,`Value`,Description FROM LookUpValue lv WHERE lv.Uid<>5 AND lv.Uid<>44 AND lv.Subcategory='PIC_BOOK' ";
$LooksRes = $g_oDBConn->query($LooksSql);
$looks = [];
while ($Look = $LooksRes->fetch_assoc()){
    $looks[$Look['UId']] =  $Look;
}
$i = 0;
foreach ($looks as $key=>$item){
    $lookY[$i]['name'] = $item['Description'];
    $lookY[$i]['data'] = [];
    $res = doXunhuan($start,$end,$key,$data);
    $lookY[$i]['data'] = $res['y'];
    $lookX = $res['x'];
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

function doXunhuan($start,$end,$key,$data){
    $lookX = [];
    while ($start < $end){
        $lookX[] = date("m月d日",$start);
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
$res['pic_bookX'] = $lookX;
$res['pic_bookY'] = $lookY;
//一段时间内的个软件使用次数占比
$someTimeSql = "SELECT so.*,lv.Description,FROM_UNIXTIME(so.OpTime,'%m月%d日') as datetime,COUNT(Description) as count FROM StatisticOperation so 
LEFT JOIN LookUpValue lv 
ON so.LookUpFunctionValueId=lv.UId 
WHERE so.LookUpFunctionValueId<>5 
AND lv.Subcategory='".$cate_name."'
AND so.OperationCode='IN' 
AND so.OpTime>".$start." 
AND so.OpTime<".$end." 
GROUP BY Description,datetime";
$someTimes = [];
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
echo '{"success":1,"someTime":'.json_encode($someTimeRes,JSON_UNESCAPED_UNICODE).',"pic_book":'.json_encode($res,JSON_UNESCAPED_UNICODE).'}';
