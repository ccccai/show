<?php
/**
 * Created by PhpStorm.
 * User: xixiheng
 * Date: 2018/2/23
 * Time: 17:38
 */
chdir(dirname(__FILE__));
include_once('./config.php');
$g_poDBConfig = CSys_Conf::$DB_Config;

$g_oDBConn = @new mysqli('p:' . $g_poDBConfig['server'], $g_poDBConfig['user'], $g_poDBConfig['psw'], $g_poDBConfig['database']);
$g_oDBConn->set_charset('utf8');

const HOST_URL = "192.168.0.128";
const VIDEO_UPLOAD_DIR = "D:/phpStudy/uploadResource/";

if($g_oDBConn->connect_error){
    die("连接数据库失败：".$g_oDBConn->connect_error);
}

    if ($_SERVER['REQUEST_METHOD'] == 'GET'){
//    @$file_name = $_GET['packageName'];
//    $splitName = explode(".",$file_name);
//    unset($splitName[count($splitName)-1]);
//    $package_name = implode('.',$splitName);
////    $package_name = explode(".",$file_name)[0];
//    $name_sql = "SELECT * FROM app_shop_url WHERE package_name='".$package_name."'";
//    $data = $g_oDBConn->query($name_sql);
//    if($data->num_rows>0){
//        $sta['status'] = 1;
//        $sta['msg'] = "已存在同名文件，是否覆盖？";
//    }else{
//        $sta['status'] = 0;
//    }
        if($g_oDBConn->connect_error){
            die("连接数据库失败：".$g_oDBConn->connect_error);
        }else{
            $sta['msg'] = "连接数据库成功";
            echo json_encode($sta);
            die();
        }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($g_oDBConn->connect_error) {
        die("连接数据库失败：" . $g_oDBConn->connect_error);
    } else {
        $res = upload();
        $enabled_flag = $_POST['enabled_flag'];
        $update_time = date("Y-m-d H:i:s");
        @$getUrl = $_POST['url'];
        $v_md5 = $res['MD5'];
        $package_name = $res['videoName'];
        $get_name_sql = "SELECT * FROM app_shop_url WHERE package_name = '" . $package_name . "'";
        $repeat_row = $g_oDBConn->query($get_name_sql);
        //更新记录
        if ($repeat_row->num_rows > 0) {
            $repeat['msg'] = '存在同名文件';
            if ($getUrl == null || $getUrl == "") {
                $update_sql = "UPDATE app_shop_url SET enabled_flag='" . $enabled_flag . "',v_md5='" . $v_md5 . "',update_time='" . $update_time . "'WHERE package_name='" . $package_name . "'";
            } else {
                $update_sql = "UPDATE app_shop_url SET enabled_flag='" . $enabled_flag . "',v_md5='" . $v_md5 . "',update_time='" . $update_time . "',url='" . $getUrl . "' WHERE package_name='" . $package_name . "'";
            }
            if ($g_oDBConn->query($update_sql)) {
                $update['msg'] = "更新记录成功";
            } else {
                $update['msg'] = "更新记录失败";
            }
            echo json_encode($update);
            die();
        }
        //不更新则插入
        $sql = "INSERT INTO app_shop_url (package_name, enabled_flag, url, v_md5, update_time) VALUES ('" . $package_name . "', '" . $enabled_flag . "','" . $getUrl . "','" . $v_md5 . "','" . $update_time . "')";
        $insert = $g_oDBConn->query($sql);
        if (!$insert) {
            $return['msg'] = "Error:" . $sql . "<br>" . $g_oDBConn->error;
            echo json_encode($return);
        } else {
            $return['msg'] = "新纪录插入成功";
            echo json_encode($return);
        }
        die();
    }
}

function upload(){
    if (isset($_FILES["addFile"]) && $_FILES["addFile"]["error"] == UPLOAD_ERR_OK) {
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            die();
        }
        $time = date('Y-m-d');
        $dir = iconv("UTF-8", "GBK", VIDEO_UPLOAD_DIR."bak/".$time);
        if (!file_exists($dir)){
            mkdir ($dir,0777,true);
        }
        $target_name = iconv("UTF-8", "GBK",$dir.'/'.$_FILES['addFile']['name']);
        $source_name = iconv("UTF-8", "GBK",VIDEO_UPLOAD_DIR . $_FILES['addFile']['name']);
        if(file_exists($source_name)&&!file_exists($target_name)){
            copy($source_name,$target_name);
        }

        if(move_uploaded_file($_FILES['addFile']['tmp_name'],VIDEO_UPLOAD_DIR.$_FILES['addFile']['name'])){
            //分割字符串形成数组返回
            $splitName = explode(".",$_FILES['addFile']['name']);
            unset($splitName[count($splitName)-1]);
            $videoName = implode('.',$splitName);
            $data['videoName'] = $videoName;
            $data['MD5'] = strtoupper(md5_file(VIDEO_UPLOAD_DIR .$_FILES['addFile']['name']));
            return $data;
        }else{
            echo "文件上传失败，错误信息：".$_FILES['addFile']['error']."<br>";
            die('上传出错001');
        }

    }else{
        if ($_FILES['addFile']['error']) {
            switch ($_FILES['addFile']['error']) {
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
//        echo json_encode($_FILES["addFile"]["error"]);
        die('上传出错002');
    }
}

$g_oDBConn->close();

?>