<?php
namespace Roombooking;

require "../bin/classes.php";

if (!(isset($_POST['startTime']) && isset($_POST['endTime']) && isset($_POST['date']) && isset($_POST['roomId']) && isset($_POST['reason']))) {
    die("Incomplete");
}

$school = new School();

$startTime = $_POST['startTime'];
$endTime = $_POST['endTime'];
$date = $_POST['date'];
$roomId = $_POST['roomId'];
$reason = $_POST['reason'];

// Validate
if (preg_match('/\d\d:\d\d$/', $startTime) == 0) {
    die("Please complete the start time of your booking.");
}
if (preg_match('/^\d\d:\d\d$/', $endTime) == 0) {
    die("Please complete the end time of your booking.");
}
if (preg_match('/^\d\d\d\d-\d\d-\d\d$/', $date) == 0) {
    die("Something is wrong with the date ($date)-- please refresh and try again, or contact support.");
}
if (empty($reason)) {
    die("Please provide a reason.");
}

// Get properly up to date
$school->resetQuery();

$room = $school->getRooms()[$roomId];

$clash = $room->isThereClash(new Period(-3, "Custom", $startTime, $endTime), $date);

if (!is_null($clash)) {
    die("There is already a lesson or other booking in this room: <br>$clash");
}

// OK, we have a clear run!
// Get the Unavailability in, quick!

$me = $school->getCurrentlyLoggedInStaff();

$aUnavail = new \Arbor\Model\RoomUnavailability();

$room = \Arbor\Model\Room::retrieve($roomId);

$aUnavail->setProperty(\Arbor\Model\RoomUnavailability::START_DATETIME, "$date $startTime:00");
$aUnavail->setProperty(\Arbor\Model\RoomUnavailability::END_DATETIME, "$date $endTime:00");
$aUnavail->setProperty(\Arbor\Model\RoomUnavailability::ROOM, $room);
$aUnavail->setProperty(\Arbor\Model\RoomUnavailability::REASON, "$reason, {$me->getName()} ({$me->getId()})");

try {
    $aUnavail->save();
} catch (\Arbor\Exception $e) {
    die("Arbor has returned an error.  Either your request is invalid, or Arbor is down.  Please check and try later.");
}

$school->resetQuery();

print ("Success");