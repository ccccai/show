<?php
//如果post提交，则进入if
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
      $selectSql = "SELECT * FROM trade_order";
      $selectRes = $g_oDBConn->query($selectSql);
      $index = 0;
      $list['status'] = 1;
      $list['list'] = [];
      while ($oRow = $selectRes->fetch_array()) {
        // var_dump($oRow);
        $list['list'][$index]['uid'] = $oRow[0];
        $list['list'][$index]['order_id'] = $oRow[1];
        $list['list'][$index]['create_time'] = $oRow[2];
        $list['list'][$index]['product_id'] = $oRow[3];
        $list['list'][$index]['product_name'] = $oRow[4];
        $list['list'][$index]['num'] = $oRow[5];
        $list['list'][$index]['price'] = $oRow[6];
        $list['list'][$index]['total_price'] = $oRow[7];
        $index++;
      }
      echo json_encode($list);
      die();
    }
  }
}
if($_SERVER['REQUEST_METHOD'] == 'POST'){
  $file = $_FILES;
  $filename = $file['excelFile']['name'];
  $type = strstr($filename,'.');
  if($type != '.xls' && $type != '.xlsx'){//判断格式
      $list['status'] = "-6001";
      echo json_encode($list);
      die();
  }

if (is_uploaded_file($file['excelFile']['tmp_name'])) {//判断表格是否上传成功
  include_once( './PHPExcel/PHPExcel.php' );
  include_once( './PHPExcel/PHPExcel/IOFactory.php');
  include_once( './PHPExcel/PHPExcel/Reader/Excel5.php');
  //以上三步加载phpExcel的类

  if ($type == '.xls') {
      $objReader = PHPExcel_IOFactory::createReader('Excel5'); //设置以Excel5格式(Excel97-2003工作簿)
  }
  if ($type == '.xlsx') {
      $objReader = PHPExcel_IOFactory::createReader('Excel2007');//设置以Excel2007格式(Excel2007以上工作簿)
  }

  $tmpname = $_FILES['excelFile']['tmp_name'];//接收存在缓存中的excel表格
  $objPHPExcel = $objReader->load($tmpname); //$tmpname可以是上传的表格，或者是指定的表格
  $sheet = $objPHPExcel->getActiveSheet();
  $highestRow = $sheet->getHighestRow(); // 取得总行数
  $highestColumn = $sheet->getHighestColumn(); // 取得总列数
  $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);

  //循环读取excel表格,读取一条,插入一条
  //j表示从哪一行开始读取  从第二行开始读取，因为第一行是标题不保存
  //$a表示列号
  $successCount = 0;
  $failCount = 0;
  for($j=2;$j<=$highestRow;$j++)
    {
        $a = $objPHPExcel->getActiveSheet()->getCell("A".$j)->getValue();//获取A列的值
        $b = $objPHPExcel->getActiveSheet()->getCell("B".$j)->getValue();//获取B列的值
        $c = $objPHPExcel->getActiveSheet()->getCell("C".$j)->getValue();//获取C列的值
        $d = $objPHPExcel->getActiveSheet()->getCell("D".$j)->getValue();//获取D列的值
        $e = $objPHPExcel->getActiveSheet()->getCell("E".$j)->getValue();
        $f = $objPHPExcel->getActiveSheet()->getCell("F".$j)->getValue();
        $g = $objPHPExcel->getActiveSheet()->getCell("G".$j)->getValue();

        $searchSql = 'SELECT * FROM trade_order WHERE order_id = "' . $a .'"';
        $searchRes = $g_oDBConn->query($searchSql);
        if ($searchRes->num_rows == 0) {
          $insertSql = "INSERT INTO trade_order VALUES( null,'$a','$b','$c','$d','$e','$f','$g' )";
          $insertRes = $g_oDBConn->query($insertSql);
          if ($insertRes) {
              $successCount++;
          }else{
              $failCount++;
          }
        }else{
          $failCount++;
        }
    }
    $list['status'] = 0;
    $list['msg'] = "成功添加".$successCount."条数据，有".$failCount."条添加失败。";
    // $excelData = array();
    // for ($row = 2; $row <= $highestRow; $row++) {
    //     for ($col = 0; $col < $highestColumnIndex-1; $col++) {
    //         $excelData[$row][] =(string)$sheet->getCellByColumnAndRow($col, $row)->getValue();
    //     }
    // }
    //
    // var_dump($excelData);
    echo json_encode($list);
    die();
  }else{
    $list['status'] = "-6000";
    echo json_encode($list);
    die();
  }
}
?>
