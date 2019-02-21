<?php
/**
 * Created by PhpStorm.
 * User: xixiheng
 * Date: 2018/3/29
 * Time: 12:20
 */
chdir(dirname(__FILE__));
include_once('./config.php');
$g_poDBConfig = CSys_Conf::$DB_Config;

$g_oDBConn = @new mysqli('p:' . $g_poDBConfig['server'], $g_poDBConfig['user'], $g_poDBConfig['psw'], $g_poDBConfig['database']);
$g_oDBConn->set_charset('utf8');


const HOST_URL = "127.0.0.1";
const UPLOAD_DIR = "C:/wwwroot/HoneyRobotWeb/Ad/";

if($_SERVER['REQUEST_METHOD'] == 'GET'){
    $strMethod = $_REQUEST['method'];
    if($strMethod=="getGroupId"){
        if($g_oDBConn->connect_error){
            die("Fail to connect the database!");
        }else{
            $groups_sql = "SELECT * FROM ApplicationGroup";
            $groups = $g_oDBConn->query($groups_sql);
            $i= 0;
            while ($group = $groups -> fetch_array()){
                $res[$i]['group_id'] = $group['UId'];
                $res[$i]['Description'] = $group['Description'];
                $i++;
            }
            echo json_encode($res);
        }
    }

    if($strMethod=="selectGroupData"){
        if($g_oDBConn->connect_error){
            die("Fail to connect the database!");
        }else{
            $i = 0;
            if($_GET['groupId']==0){
                $all_ids_sql = "SELECT UId FROM ApplicationGroup";
                $ids = $g_oDBConn->query($all_ids_sql);
                while ($id = $ids->fetch_array()){
                    $groupId_sql = "SELECT * FROM rb_ad_info WHERE group_id = '".$id['UId']."'";
                    $rows = $g_oDBConn->query($groupId_sql);
                    if ($rows===FALSE){

                    }else{
                        while ($row = $rows ->fetch_array()){
                            $info[$i]['uid'] = $row['uid'];
                            $info[$i]['name'] = $row['name'];
                            $info[$i]['rb_pic_url'] = $row['rb_pic_url'];
                            $info[$i]['rb_pic_md5'] = $row['rb_pic_md5'];
                            $info[$i]['rb_audio_url'] = $row['rb_audio_url'];
                            $info[$i]['rb_audio_md5'] = $row['rb_audio_md5'];
                            $info[$i]['mb_pic_url'] = $row['mb_pic_url'];
                            $info[$i]['mb_pic_md5'] = $row['mb_pic_md5'];
                            $info[$i]['enabled_flag'] = $row['enabled_flag'];
                            $info[$i]['update_time'] = $row['update_time'];
                            $info[$i]['rb_content'] = $row['rb_content'];
                            $info[$i]['group_id'] = $row['group_id'];
                            $info[$i]['recommend_enabled_flag'] = $row['recommend_enabled_flag'];
                            $i++;
                        }
                    }
                }
            }else{
                $groupId_sql = "SELECT * FROM rb_ad_info WHERE group_id = '".$_GET['groupId']."'";
                $rows = $g_oDBConn->query($groupId_sql);
                $info = [];
                if ($rows===FALSE){

                }else{
                    while ($row = $rows ->fetch_array()){
                        $info[$i]['uid'] = $row['uid'];
                        $info[$i]['name'] = $row['name'];
                        $info[$i]['rb_pic_url'] = $row['rb_pic_url'];
                        $info[$i]['rb_pic_md5'] = $row['rb_pic_md5'];
                        $info[$i]['rb_audio_url'] = $row['rb_audio_url'];
                        $info[$i]['rb_audio_md5'] = $row['rb_audio_md5'];
                        $info[$i]['mb_pic_url'] = $row['mb_pic_url'];
                        $info[$i]['mb_pic_md5'] = $row['mb_pic_md5'];
                        $info[$i]['enabled_flag'] = $row['enabled_flag'];
                        $info[$i]['update_time'] = $row['update_time'];
                        $info[$i]['rb_content'] = $row['rb_content'];
                        $info[$i]['group_id'] = $row['group_id'];
                        $info[$i]['recommend_enabled_flag'] = $row['recommend_enabled_flag'];
                        $i++;
                    }
                }
            }
            echo json_encode($info);
        }
    }

    if($strMethod=="submitData"){
        if($g_oDBConn->connect_error){
            die("Fail to connect the database!");
        }else{
            echo "连接数据库成功";
        }
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $v_data = v_upload();
    $m_data = m_upload();
    $ad_name = $_POST['ad_name'];
    $audio_name = $v_data['videoName'];
    $rb_audio_url = "http://windowserl.honeybot.cn:8080/Ad/".$audio_name;
    $rb_audio_md5 = $v_data['rb_audio_md5'];
    $pic_name = $m_data['picName'];
    $mb_pic_url = "http://windowserl.honeybot.cn:8080/Ad/".$pic_name;
    $mb_pic_md5 = $m_data['mb_pic_md5'];
    $group_id = $_POST['group_id'];
    $enabled_flag = $_POST['hidden'];
    $re_enabled_flag = $_POST['re_hidden'];
    $update_time = date("Y-m-d H:i:s");
    $exist_sql = "SELECT * FROM rb_ad_info WHERE group_id = '".$_POST['group_id']."'";
    $exec = $g_oDBConn->query($exist_sql);
    if($exec->num_rows > 0){
        $update_sql = "UPDATE rb_ad_info SET rb_audio_url = '".$rb_audio_url."',rb_audio_md5 = '".$rb_audio_md5."',
        mb_pic_url = '".$mb_pic_url."',mb_pic_md5 = '".$mb_pic_md5."',enabled_flag = '".$enabled_flag."',update_time = '".$update_time."',
        recommend_enabled_flag = '".$re_enabled_flag."',name = '".$ad_name."' WHERE group_id = '".$group_id."'";
        if($g_oDBConn->query($update_sql)) {
            $echo['msg'] = "更新记录成功！";
        }
    }else{
            $in_sql = "INSERT INTO rb_ad_info 
                  (name, rb_audio_url, rb_audio_md5, mb_pic_url, mb_pic_md5, enabled_flag, update_time, group_id, recommend_enabled_flag) 
                  VALUES ('".$ad_name."','".$rb_audio_url."', '".$rb_audio_md5."','".$mb_pic_url."', '".$mb_pic_md5."', '".$enabled_flag."', '".$update_time."', '".$group_id."', '".$re_enabled_flag."')";
            if($g_oDBConn->query($in_sql)){
                $echo['msg'] = "新增记录成功！";
            }
        }

    echo json_encode($echo);
}


function v_upload(){
    if(isset($_FILES["robotVideo"]) && $_FILES["robotVideo"]["error"] == UPLOAD_ERR_OK){
        if(!isset($_SERVER['HTTP_X_REQUESTED_WITH'])){
            die();
        }
        $time = date('Y-m-d');
        $dir = iconv("UTF-8","GBK",UPLOAD_DIR."bak/".$time);
        if(!file_exists($dir)){
            mkdir($dir,0777,true);
            chmod($dir,0777);
        }
        $target_name = iconv("UTF-8","GBK",$dir.'/'.$_FILES['robotVideo']['name']);
        $source_name = iconv("UTF-8","GBK",UPLOAD_DIR.$_FILES['robotVideo']['name']);
        if(file_exists($source_name)&&!file_exists($target_name)){
            copy($source_name,$target_name);
        }
        if(move_uploaded_file($_FILES['robotVideo']['tmp_name'],UPLOAD_DIR.$_FILES['robotVideo']['name'])){
//            $splitName = explode(".",$_FILES['robotVideo']['name']);
//            unset($splitName[count($splitName)-1]);
//            $videoName = implode(".",$splitName);
            $data['videoName'] = $_FILES['robotVideo']['name'];
            $data['rb_audio_md5'] = md5_file(UPLOAD_DIR.$_FILES['robotVideo']['name']);
            return $data;
        }else{
            die("视频上传出错".$_FILES['robotVideo']['error']);
        }
    }else{
        if($_FILES['robotVideo']['error']){
            switch ($_FILES['robotVideo']['error']){
                case 1:
                    $str = '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值';
                    break;
                case 2:
                    $str = '上传的文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值';
                    break;
                case 3:
                    $str = '文件只有部分被上传';
                    break;
                case 4:
                    $str = '没有文件被上传';
                    break;
                case 6:
                    $str = '找不到临时文件夹';
                    break;
                case 7:
                    $str = '文件写入失败';
                    break;
                default:
                    $str = '未知异常';
                    break;
            }
            echo $str;
            exit;
        }
        die("视频上传失败");
    }
}

function m_upload(){
    if(isset($_FILES["mobilePic"]) && $_FILES["mobilePic"]["error"] == UPLOAD_ERR_OK){
        if(!isset($_SERVER['HTTP_X_REQUESTED_WITH'])){
            die();
        }
        $time = date('Y-m-d');
        $dir = iconv("UTF-8","GBK",UPLOAD_DIR."bak/".$time);
        if(!file_exists($dir)){
            mkdir($dir,0777,true);
            chmod($dir,0777);
        }
        $target_name = iconv("UTF-8","GBK",$dir.'/'.$_FILES['mobilePic']['name']);
        $source_name = iconv("UTF-8","GBK",UPLOAD_DIR.$_FILES['mobilePic']['name']);
        if(file_exists($source_name)&&!file_exists($target_name)){
            copy($source_name,$target_name);
        }
        if(move_uploaded_file($_FILES['mobilePic']['tmp_name'],UPLOAD_DIR.$_FILES['mobilePic']['name'])){
//            $splitName = explode(".",$_FILES['mobilePic']['name']);
//            unset($splitName[count($splitName)-1]);
//            $picName = implode(".",$splitName);
            $data['picName'] = $_FILES['mobilePic']['name'];
            $data['mb_pic_md5'] = md5_file(UPLOAD_DIR.$_FILES['mobilePic']['name']);
            return $data;
        }else{
            die("图片上传出错".$_FILES['mobilePic']['error']);
        }
    }else{
        if($_FILES['mobilePic']['error']){
            switch ($_FILES['mobilePic']['error']){
                case 1:
                    $str = '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值';
                    break;
                case 2:
                    $str = '上传的文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值';
                    break;
                case 3:
                    $str = '文件只有部分被上传';
                    break;
                case 4:
                    $str = '没有文件被上传';
                    break;
                case 6:
                    $str = '找不到临时文件夹';
                    break;
                case 7:
                    $str = '文件写入失败';
                    break;
                default:
                    $str = '未知异常';
                    break;
            }
            echo $str;
            exit;
        }
        die("图片上传失败");
    }
}

$g_oDBConn->close();
