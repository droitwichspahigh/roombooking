<?php
namespace Roombooking;

require "bin/classes.php";

$school = new School();
$db = new Database();

$lessonId = $_GET['cancelBooking'];
$date = $_GET['date'];

$roomId = $db->dosql("SELECT oldroom_id FROM roomchanges WHERE lesson_id = '$lessonId';")->fetch_array(MYSQLI_NUM)[0];

$session = \Arbor\Model\Session::retrieve($lessonId);
$session->setLocation(\Arbor\Model\Room::retrieve($roomId));
$session->save();

/* Need to invalidate the Query data now, as timetable is new */
unset($_SESSION['School_queryData']);

header("location: index.php?date=$date");