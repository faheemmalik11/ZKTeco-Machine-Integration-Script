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
    $usersById[$user[0]] = $user[1]; //associative array for names with employee_id as key and name as value
}

foreach ($attendances as $index=>$attendance) {
    $employeeId = $attendance[1];   
    $attendanceTime = $attendance[3];
    if (isset($usersById[$employeeId]) && $index!=0 ) {   //if associative array created above has employeeId coming from attendance array. index 0 has garbaage data
        $nameOfUser = $usersById[$employeeId];
        $attendance[] = $nameOfUser; //pushing name

        $timestamp = strtotime($attendanceTime);
          try { //We did this because we needed mysql timestamp to store in db
              $mysqlTimestamp = date("Y-m-d H:i:s", $timestamp);
              $attendance[3] = $mysqlTimestamp; //replacing timestamp from string
          } catch (\Throwable $th) {
              echo "Invalid date string.\n ". $th->getMessage();
          }

        $attedanceWithNames[] = $attendance; //Pushing attendance in attedanceWithNames
    }
}

try {
  $conn = new PDO("mysql:host=$servername;dbname=".$db, $username, $password);    

  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  echo "Connected successfully";


  // QUERIES
  $companyEmployees = $conn->prepare("SELECT * FROM employees");
  $companyEmployees->execute();
  $companyEmployees = $companyEmployees->fetchAll(PDO::FETCH_ASSOC);

  $insertQuery = $conn->prepare("INSERT INTO attendances (serial_number, employee_id, check_in, check_out, timestamp, name) VALUES (?, ?, ?, ?, ?, ?)"); 
  $selectLastAttendanceTimeQuery =  $conn->prepare("SELECT timestamp FROM attendances ORDER BY timestamp DESC LIMIT 1"); //This is to know when was the last attendance inserted into db, it is getting greatest timestamp from db
  $selectLastAttendanceTimeQuery->execute();
  $resultLastAttendanceTime = $selectLastAttendanceTimeQuery->fetch(PDO::FETCH_ASSOC);

  $selectQuery = $conn->prepare("SELECT check_in, check_out FROM attendances WHERE DATE(timestamp) = :date AND employee_id = :employee_id AND check_in IS NOT NULL AND check_out IS NULL"); //Checks wether there is any null checkout. No user should do check_in if checkout is null
  $checkOutUpdateQuery = $conn->prepare("UPDATE attendances SET check_out = :checkOut , timestamp = :new_timestamp WHERE DATE(timestamp) = :date AND employee_id = :employee_id AND check_in IS NOT NULL AND check_out IS NULL"); //update where checkout is null and update the timestamp to checkout_time
  


  foreach($attedanceWithNames as $key=>$attendance){
      $updated = false;
      $dontCheckIn = false;
      $employeeId = $attendance[1];
      $status = $attendance[2]; //status means check_in or checkout , 0 means check in and 1 means check out
      $attendanceTime = $attendance[3];
      list($date) = explode(' ',$attendanceTime);

      
      
      print_r("<br>".$key . "<br>");
      print_r($attendance);
      echo "<br>";

      $selectQuery->bindParam(':date', $date, PDO::PARAM_STR);
      $checkOutUpdateQuery->bindParam(':date', $date, PDO::PARAM_STR);
      $selectQuery->bindParam(':employee_id', $employeeId, PDO::PARAM_STR);
      $checkOutUpdateQuery->bindParam(':employee_id', $employeeId, PDO::PARAM_STR);

      $selectQuery->execute();
      $selectQueryResult = $selectQuery->fetch(PDO::FETCH_ASSOC);

        if (strtotime($resultLastAttendanceTime['timestamp']) < strtotime($attendanceTime) ) { //enters to loop only if time of the attendance is greater than last attendance time
          for($i=0; $i<count($attendance); $i++){
            if($i==2 && $status =='0'){       //if attendance is checkIn, 0 identifies checkIn

              $insertQuery->bindParam(3,$attendanceTime);     // 3 is check in
              $insertQuery->bindParam(4,$nullValue);          // 4 is checkOut

              echo "inserting the check in :". $attendanceTime;

              if($selectQueryResult){         //if there is a user who hasn't checkout and wants to check_in again 
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
            else {        // for first two indexes serialNumber and employeeId
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

//this is to checkout by default after 11 pm
date_default_timezone_set('Asia/Karachi');    
$currentDateTime = date('Y-m-d h:i:s a', time());
list($currentDate,$currentTime) = explode(' ',$currentDateTime);
$defaultCheckout = $currentDate .' 23:00:00';

if(strtotime($currentDateTime) >= strtotime($defaultCheckout)){

  $updateCheckOutQuery = $conn->prepare("UPDATE attendances SET check_out = :defaultCheckout WHERE DATE(timestamp) = :date2 AND check_in IS NOT NULL AND check_out IS NULL "); //updates all the null checkouts of that date
  $updateCheckOutQuery->bindParam(':defaultCheckout', $defaultCheckout, PDO::PARAM_STR);
  $updateCheckOutQuery->bindParam(':date2',$currentDate);
  $updateCheckOutQuery->execute();

}

$zk->enableDevice();
$zk->disconnect();

?>
