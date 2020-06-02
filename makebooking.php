<?php
namespace Roombooking;

require "bin/classes.php";

$school = new School();
$db = new Database();

$startTime = $_GET['period'];
$roomId = $_GET['roomId'];
$date = $_GET['date'];
$client = new GraphQLClient();

/** @var string $date Defined in index.php */
/** @var \Roombooking\School $school */

/* What lesson and Room does the staff member have at this point? */

$queryData = $client->rawQuery('
query {
    staffCal: CalendarEntryMapping (calendar__id: ' . $school->getStaffCalendarId($school->getLoggedInStaffId()) . ' startDatetime: "' . $date . " " . $startTime . '") {
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
                startDatetime
            }
        }
    }
    roomCal: CalendarEntryMapping (calendar__id: ' . $school->getCalendarIds()[$roomId] . ' startDatetime: "' . $date . " " . $startTime . '") {
        id
    }
}')->getData();

$staffCal = $queryData['staffCal'];

if (isset($queryData['roomCal'][0])) {
    die ("Sorry, appears someone has pipped you to the post...");
}

if (!isset($staffCal[0])) {
    /* TODO don't die! */
    $_SESSION['thereIsNoLessonAtThisTime'] = true;
    header("location: index.php?date=$date");
    die();
}

if (isset($staffCal[1])) {
    /* There is a conflict, that's weird */
    die ("Conflicting lessons?");
}

$LessonId = $staffCal[0]['lesson']['id'];
$oldLessonRoomId= $staffCal[0]['lesson']['location']['id'];

/* Store the old room in the database */

$db = new Database();
$db->dosql("INSERT INTO roomchanges (lesson_id, oldroom_id) VALUES ($LessonId, $oldLessonRoomId);");

$session = \Arbor\Model\Session::retrieve($LessonId);
$session->setLocation(\Arbor\Model\Room::retrieve($roomId));
$session->save();

/* Need to invalidate the Query data now, as timetable is new */
unset($_SESSION['School_queryData']);

header("location: index.php?date=$date");