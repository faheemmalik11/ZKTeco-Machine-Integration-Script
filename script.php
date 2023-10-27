<?php

require 'zklibrary.php';
require 'config.php';


set_time_limit(60); 
$zk = new ZKLibrary($ip, 4370);
$zk->connect();

$zk->disableDevice();

$users = $zk->getUser();
$attendances = $zk->getAttendance();


$nullValue = null;
$usersById = [];  
$attedanceWithNames = [];

foreach ($users as $user) {
    $usersById[$user[0]] = $user[1]; //associative array for names with user_id as key and name as value
}

foreach ($attendances as $index=>$attendance) {
    $userId = $attendance[1];   
    $time_stamp_string = $attendance[3];
    if (isset($usersById[$userId]) && $index!=0 ) {
        $nameOfUser = $usersById[$userId];
        $attendance[] = $nameOfUser; //pushing name

        // list($date,$time) = explode(' ',$time_stamp_string);
        $timestamp = strtotime($time_stamp_string);
        if ($timestamp) {
          $mysqlTimestamp = date("Y-m-d H:i:s", $timestamp);
          $attendance[3] = $mysqlTimestamp; //replacing timestamp from string
        } else {
            echo "Invalid date string.\n";
        }
        $attedanceWithNames[] = $attendance; //Pushing attendance in attedanceWithNames
    }
}

try {
  $conn = new PDO("mysql:host=$servername;dbname=".$db, $username, $password);    

  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  echo "Connected successfully";
  // QUERIES
  $insertQuery = $conn->prepare("INSERT INTO attendances (serial_number, user_id, check_in, check_out, timestamp, name) VALUES (?, ?, ?, ?, ?, ?)");
  $selectLastAttendanceTimeQuery =  $conn->prepare("SELECT timestamp FROM attendances ORDER BY timestamp DESC LIMIT 1");
  $selectLastAttendanceTimeQuery->execute();
  $resultLastAttendanceTime = $selectLastAttendanceTimeQuery->fetch(PDO::FETCH_ASSOC);

  $selectQuery = $conn->prepare("SELECT check_in, check_out FROM attendances WHERE DATE(timestamp) = :date AND user_id = :user_id AND check_in IS NOT NULL AND check_out IS NULL");
  $checkOutUpdateQuery = $conn->prepare("UPDATE attendances SET check_out = :checkOut , timestamp = :new_timestamp WHERE DATE(timestamp) = :date AND user_id = :user_id AND check_in IS NOT NULL AND check_out IS NULL");
  
  date_default_timezone_set('Asia/Karachi');
  $currentDateTime = date('Y-m-d h:i:s a', time());
  list($date,$time) = explode(' ',$currentDateTime);

  $defaultCheckout = $date .' 23:00:00';


  foreach($attedanceWithNames as $key=>$attendance){
      $updated = false;
      $dontCheckIn = false;
      $userId = $attendance[1];
      $status = $attendance[2];
      $attendanceTime = $attendance[3];
      list($date) = explode(' ',$attendanceTime);

      
      
      print_r("<br>".$key . "<br>");
      print_r($attendance);
      echo "<br>";

      $selectQuery->bindParam(':date', $date, PDO::PARAM_STR);
      $checkOutUpdateQuery->bindParam(':date', $date, PDO::PARAM_STR);
      $selectQuery->bindParam(':user_id', $userId, PDO::PARAM_STR);
      $checkOutUpdateQuery->bindParam(':user_id', $userId, PDO::PARAM_STR);

      $selectQuery->execute();
      $selectQueryResult = $selectQuery->fetch(PDO::FETCH_ASSOC);

        if (strtotime($resultLastAttendanceTime['timestamp']) < strtotime($attendanceTime) ) { //enters to loop only if time of the attendance is greater than last attendance time
          for($i=0; $i<count($attendance); $i++){
            if($i==2 && $status =='0'){       //if attendance is checkIn, 0 identifies checkIn

              $insertQuery->bindParam(3,$attendanceTime);     // 3 is check in
              $insertQuery->bindParam(4,$nullValue);          // 4 is checkOut

              echo "inserting the check in :". $attendanceTime;

              if($selectQueryResult){       
                $dontCheckIn = true;
                echo "check_out first to do check_in";
              }
              

            }elseif($i==2 && $status =='1'){  // if attendance is checkOut , 1 identifies checkOut

              $updated = true;

              try{
                      if ($selectQueryResult) {
                          print_r("CONDITION 1: check_in: ".$selectQueryResult['check_in'] ." check_out: ". $selectQueryResult['check_out'] );
                          $checkOutUpdateQuery->bindParam(':checkOut', $attendanceTime, PDO::PARAM_STR,);
                          $checkOutUpdateQuery->bindParam(':new_timestamp', $attendanceTime, PDO::PARAM_STR,);
                          $checkOutUpdateQuery->execute();
                      }else{
                          print_r("CONDITION 2: check_in: ".$selectQueryResult['check_in'] ." check_out: ". $selectQueryResult['check_out'] );
                          echo "Checkout cannot be inserted because checkin was not made <br>";

                      }
                  }catch(PDOException $e) {
                    echo  $e->getMessage();
                  }
            }
            elseif($i == 3 || $i == 4){     //for indexes 3=>timestamp and 4=>name
              $insertQuery->bindParam($i+2,$attendance[$i]);
            }
            else {        // for first two indexes serialNumber and userID
              $insertQuery->bindParam($i+1,$attendance[$i]);
            }
          }

          if(!$updated  && !$dontCheckIn)    
          {
            $insertQuery->execute();
            echo "<br> INSERTED";
          }

        }

    }
  echo "<br> Data Inserted Successfully";
} catch(PDOException $e) {
  echo "Connection failed: " . $e->getMessage();
}



if(strtotime($currentDateTime) >= strtotime($defaultCheckout)){
  echo "jdsjjsd";
  $updateCheckOutQuery = $conn->prepare("UPDATE attendances SET check_out = :defaultCheckout WHERE DATE(timestamp) = :date2 AND check_in IS NOT NULL AND check_out IS NULL ");
  $updateCheckOutQuery->bindParam(':defaultCheckout', $defaultCheckout, PDO::PARAM_STR);
  $updateCheckOutQuery->bindParam(':date2',$date);
  $updateCheckOutQuery->execute();

}

$zk->enableDevice();
$zk->disconnect();

?>
