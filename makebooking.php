<?php
namespace Roombooking;

require "bin/classes.php";

$school = new School();
$db = new Database();

foreach (['startTime', 'roomId', 'date'] as $g) {
    if (!isset($_GET[$g])) {
        header("location: index.php");
    }
}

$startTime = $_GET['startTime'];
$roomId = $_GET['roomId'];
$date = $_GET['date'];
$client = new GraphQLClient();

$db->lock('roomchanges');

/* What lesson and Room does the staff member have at this point? */

$queryData = $client->rawQuery('
query {
    staffCal: CalendarEntryMapping (calendar__id: ' . $school->getCurrentlyLoggedInStaff()->getCalendarId() . ' startDatetime: "' . $date . " " . $startTime . '") {
        id
        lesson: event {
            __typename
            ... on Session {
                location {
                    __typename
                    ... on Room {
                        id
                    }
                }
                displayName
                startDatetime
                endDatetime
                staff {
                    displayName
                }
            }
        }
    }
    roomCal: CalendarEntryMapping (calendar__id: ' . $school->getRoomCalendarIds()[$roomId] . ' startDatetime: "' . $date . " " . $startTime . '") {
        id
    }
}')->getData();

$staffCal = $queryData['staffCal'];

if (isset($queryData['roomCal'][0])) {
    $db->unlock();
    $_SESSION['someoneHasPippedYouToThePost'] = true;
    header("location: index.php?date=$date");
    $school->resetQuery();
    die();
}

if (!isset($staffCal[0])) {
    $db->unlock();
    $_SESSION['thereIsNoLessonAtThisTime'] = true;
    header("location: index.php?date=$date");
    die();
}

if (isset($staffCal[1])) {
    $db->unlock();
    /* There is a conflict, that's weird */
    die ("Conflicting lessons?");
}

$LessonId = $staffCal[0]['lesson']['id'];
$oldLessonRoomId= $staffCal[0]['lesson']['location']['id'];

/* Store the old room in the database */

$myCalendarId = $school->getCurrentlyLoggedInStaff()->getCalendarId();
$db->dosql("INSERT INTO roomchanges (lesson_id, oldroom_id, booking_calendar) VALUES ($LessonId, $oldLessonRoomId, $myCalendarId);");

$session = \Arbor\Model\Session::retrieve($LessonId);
$session->setLocation(\Arbor\Model\Room::retrieve($roomId));
$session->save();

/* Need to hack this into the Query data now, as timetable has changed */
/* XXX This is so evil, but it works I guess */
$staffCal[0]['lesson']['location']['id'] = $roomId;
array_push($_SESSION['School_queryData']['CalendarEntryMapping'], $staffCal[0]);

$db->unlock();

header("location: index.php?date=$date");