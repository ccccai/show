<?php
/**
 * Created by PhpStorm.
 * User: xu
 * Date: 2017/6/21
 * Time: 下午4:44
 */
//使用次数占比
$sql = "SELECT so.*,lv.Description,FROM_UNIXTIME(so.OpTime,'%m月%d日') as datetime,COUNT(Description) as count FROM StatisticOperation so 
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
    $list['OpTime'] = strtotime(date('Y-m-d',$list['OpTime']));
    $useData[] = $list;
}
$data = [];
foreach ($useData as $key=>$item)
{
    $data[$item['OpTime']][$item['LookUpFunctionValueId']] = $item;
}
$a = [];
$LooksSql = "select UId,`Value`,Description FROM LookUpValue";
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

function doXunhuan($start,$end,$key,$data){
    $lookX = [];
    while ($start < $end){
        $lookX[] = date("m月d日",$start);
        if (array_key_exists($start,$data))
        {
            if (array_key_exists($key,$data[$start])){
                $res[] = $data[$start][$key]['count'];
            }else{
                $res[] = 0;
            }
        }else{
            $res[] = 0;
        }
        $start += 86400;
    }
    $data['y'] = $res;
    $data['x'] = $lookX;
    return $data;
}
$res = [];
$res['lookX'] = $lookX;
$res['lookY'] = $lookY;