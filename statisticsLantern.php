<?php
/**
 * Created by PhpStorm.
 * User: xixiheng
 * Date: 2018/3/7
 * Time: 16:50
 */
chdir(dirname(__FILE__));
include_once('./config.php');
$g_poDBConfig = CSys_Conf::$DB_Config;

$g_oDBConn = @new mysqli('p:' . $g_poDBConfig['server'], $g_poDBConfig['user'], $g_poDBConfig['psw'], $g_poDBConfig['database']);
$g_oDBConn->set_charset('utf8');
$strMethod = $_REQUEST['method'];

if($_SERVER['REQUEST_METHOD']=='GET'){
    if($strMethod=='searchAttendNum'){
        if($g_oDBConn->connect_error){
            die('连接数据库失败！');
        }else{
            $coupon_sql = "SELECT count(1) AS firstCount FROM rb_robot_coupon_mapping LEFT JOIN rb_coupons ON rb_robot_coupon_mapping.coupon_id = rb_coupons.uid WHERE rb_coupons.coupon_type=1";
            if($g_oDBConn->query($coupon_sql)){
                $coupon_num = $g_oDBConn->query($coupon_sql);
                $res['data']['coupon_num'] = $coupon_num->fetch_object()->firstCount;

            }else{
                echo '查询爱手工券失败';
                die();
            }

            $coupon68_sql = "SELECT count(1) AS secondCount FROM rb_robot_coupon_mapping LEFT JOIN rb_coupons ON rb_robot_coupon_mapping.coupon_id = rb_coupons.uid WHERE rb_coupons.coupon_type=2";
            if($g_oDBConn->query($coupon68_sql)){
                $coupon68_num = $g_oDBConn->query($coupon68_sql);
                $res['data']['coupon68_num'] = $coupon68_num->fetch_object()->secondCount;
            }else{
                echo '查询68折扣券失败';
                die();
            }

            $attendNum_sql = "SELECT count(uid) AS attendCount FROM rb_scan_fruit_statistic";
            if($g_oDBConn->query($attendNum_sql)){
                $attendNum = $g_oDBConn->query($attendNum_sql);
                $res['data']['attendNum'] = $attendNum->fetch_object()->attendCount;
            }else{
                echo '查询参与人数失败';
                die();
            }

            echo json_encode($res);
        }
    }

}

if($_SERVER['REQUEST_METHOD']=='POST'){
    if($strMethod=='selectRobotId'){
        if($g_oDBConn->connect_error){
            die('连接数据库失败！');
        }else{
            $robotId = $_POST['robotId'];
            $search_sql = "SELECT ProductId AS rbId FROM RUserBase WHERE UId = '".$robotId."'";
            if($g_oDBConn->query($search_sql)){
                $productId = $g_oDBConn->query($search_sql);
                if(mysqli_num_rows($productId)>0){
                    $data['productId'] = $productId->fetch_object()->rbId;
                    $data['msg'] = '查询序列号成功！';
                }else{
                    $data['productId'] = "";
                    $data['msg'] = '没有与此ID相关的机器人序列号！';
                }
                echo json_encode($data);
            }else{
                die('查询失败！');
            }
        }
    }

    if($strMethod=='searchPhoneNum'){
        if($g_oDBConn->connect_error){
            die('连接数据库失败！');
        }else{
            if($_POST['coupon_type']==0){
                $couponIds = array(1,2);
            }else{
                $couponIds = array($_POST['coupon_type']);
            }
            $where = "";
            if($_POST['coupon_type'] > 0){
                $where .= " AND rb_coupons.coupon_type = '".$_POST['coupon_type']." ' ";
            }
            foreach ($couponIds as $couponId){
                $robotId_sql = "SELECT
	robot_id,
	rb_coupons.coupon_type,
	LookUpValue.Description
FROM
	rb_robot_coupon_mapping AS mapping
LEFT JOIN rb_coupons ON mapping.coupon_id = rb_coupons.uid
LEFT JOIN LookUpValue ON TypeCode = \"RB_COUPONS_COUPON_TYPE\"
WHERE
	rb_coupons.coupon_type = LookUpValue.`Value`". $where;

                $res_sql = $g_oDBConn->query($robotId_sql);
                $i=0;
                //查询机器人序列号
                while ($row = $res_sql->fetch_array()) {
                    $data['obj'][$i]['robotId'] = $row['robot_id'];
                    $data['obj'][$i]['Description'] = $row['Description'];
                    $productId_sql = "SELECT ProductId FROM RUserBase WHERE RUserBase.UId = '".$row['robot_id']."' ";
                    $res_product = mysqli_query($g_oDBConn,$productId_sql);
                    $product_each = $res_product->fetch_array();
                    $data['obj'][$i]['robot'] = $product_each[0];

                    $mobile_sql = "SELECT MNum FROM MUserBase LEFT JOIN MRBind ON MUserBase.UId = MRBind.MUId WHERE MRBind.RUId = '".$data['obj'][$i]['robotId']."'";
                    $res_mobile = mysqli_query($g_oDBConn,$mobile_sql);
                    $j=0;
                    $data['obj'][$i]['mobile'] = [];
                    $data['obj'][$i]['mobile'][0] = "";
                    while ($row_m = mysqli_fetch_row($res_mobile)){
                        $data['obj'][$i]['mobile'][$j] = $row_m[0];
                        $j++;
                    }

                    $i++;
                }

            }
                echo json_encode($data);

        }
    }

}