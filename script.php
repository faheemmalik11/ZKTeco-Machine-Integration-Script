<?php
// echo "dds";

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
$absent = 'absent';
$present = 'present';
// File path to store the last execution date
$lastExecutionFilePath = '/home/zubair/Documents/ZKTeco-Machine-Integration-Script/last_executed.txt';

// Read the last execution date from the file
$lastExecutionDate = file_get_contents($lastExecutionFilePath);
// Get the current date
$currentDate = date('Y-m-d');

try {
  $conn = new PDO("mysql:host=$servername;dbname=".$db, $username, $password);    

  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  echo "Connected successfully";


  // QUERIES
  $insertQuery = $conn->prepare("INSERT INTO attendances (employee_id, check_in, check_out, timestamp, name, status) VALUES (?, ?, ?, ?, ?, ?)"); 
  $updateAttendance = $conn->prepare("UPDATE attendances SET check_in = :check_in, check_out = :check_out, timestamp = :timestamp, status = :status WHERE employee_id = :employee_id AND Date(timestamp) = :attendance_date");
  $companyEmployees = $conn->prepare("SELECT * FROM employees");
  $companyEmployees->execute();
  $companyEmployees = $companyEmployees->fetchAll(PDO::FETCH_ASSOC);

  if ($lastExecutionDate !== $currentDate) {
    foreach ($companyEmployees as $employee){
      $insertQuery->bindParam(1, $employee['employee_id']);
      $insertQuery->bindParam(2, $nullValue);
      $insertQuery->bindParam(3, $nullValue);
      $insertQuery->bindParam(4, $currentDate);
      $insertQuery->bindParam(5, $employee['name']);
      $insertQuery->bindParam(6, $absent);
      $insertQuery->execute();
    }
    file_put_contents($lastExecutionFilePath, $currentDate);
}
// $updateAttendance =  $conn->prepare('UPDATE attendances SET check_in')

$selectLastAttendanceTimeQuery =  $conn->prepare("SELECT timestamp FROM attendances ORDER BY timestamp DESC LIMIT 1"); //This is to know when was the last attendance inserted into db, it is getting greatest timestamp from db
$selectLastAttendanceTimeQuery->execute();
$resultLastAttendanceTime = $selectLastAttendanceTimeQuery->fetch(PDO::FETCH_ASSOC);

$selectQuery = $conn->prepare("SELECT check_in, check_out FROM attendances WHERE DATE(timestamp) = :date AND employee_id = :employee_id AND check_in IS NOT NULL AND check_out IS NULL"); //Checks wether there is any null checkout. No user should do check_in if checkout is null
$checkOutUpdateQuery = $conn->prepare("UPDATE attendances SET check_out = :checkOut , timestamp = :new_timestamp WHERE DATE(timestamp) = :date AND employee_id = :employee_id AND check_in IS NOT NULL AND check_out IS NULL"); //update where checkout is null and update the timestamp to checkout_time



foreach($attendances as $key=>$attendance){
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

              $updateAttendance->bindParam(':check_in',$attendanceTime);     
              $updateAttendance->bindParam(':check_out',$nullValue);          
              $updateAttendance->bindParam(':timestamp',$attendanceTime);   
              $updateAttendance->bindParam(':status',$present);   
              $updateAttendance->bindParam(':attendance_date',$date);   
              $updateAttendance->bindParam(':employee_id',$employeeId);   


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
            
          }

          if(!$updated  && !$dontCheckIn)    
          {
            $updateAttendance->execute();
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
