<?php
$dns="mysql:host=localhost;dbname=QR_cood";
$user="root";
$pass="";
$option=array(PDO::MYSQL_ATTR_INIT_COMMAND=>"SET NAMES utf8");
try{
    $connect = new PDO($dns,$user,$pass,$option);
    $connect->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
}catch(PDOException $e){
echo "failed".$e->getMessage();
}

?>