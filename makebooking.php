<?php
namespace Roombooking;

require "bin/classes.php";

$school = new School();
$db = new Database();

foreach (['roomId', 'date'] as $g) {
    if (!isset($_GET[$g])) {
        header("location: index.php");
    }
}

$date = $_GET['date'];
$endTime = $_GET['endTime'];
if (isset($_GET['startTime'])) {
    /* We're going to try to book with startTime at first, then endTime */
    $startTime = $_GET['startTime'];
    $queryFilter = "startDatetime: \"$date $startTime\"";
} else {
    $queryFilter = "endDatetime: \"$date $endTime\"";
}
$roomId = $_GET['roomId'];
$client = new GraphQLClient();

$db->lock('roomchanges');

/* What lesson and Room does the staff member have at this point? */

$queryData = $client->rawQuery('
query {
    staffCal: CalendarEntryMapping (calendar__id: ' . $school->getCurrentlyLoggedInStaff()->getCalendarId() . ' ' . $queryFilter . ') {
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
    roomCal: CalendarEntryMapping (calendar__id: ' . $school->getRoomAcademicCalendarIds()[$roomId] . ' ' . $queryFilter . ') {
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
    if (isset($startTime)) {
        /* Let's try with the endTime now */
        $db->unlock();
        header("Location: makebooking.php?endTime=" . urlencode($endTime) . "&roomId=$roomId&date=$date");
        die();
    } else {
        /* We've tried endTime, we're now out of options, give up */
        $db->unlock();
        $_SESSION['thereIsNoLessonAtThisTime'] = true;
        header("location: index.php?date=$date");
        die();
    }
}

if (isset($staffCal[1])) {
    $db->unlock();
    /* There is a conflict, that's weird */
    die ("Conflicting lessons?");
}

$LessonId = $staffCal[0]['lesson']['id'];
$oldLessonRoomId= $staffCal[0]['lesson']['location']['id'];

if (empty($oldLessonRoomId)) {
    /* 
     * This looks as though we don't have a Room allocated, which could be a Duty.
     * 
     * Chances are you won't have a Lesson afterwards, but you really do need to get this allocated if you do!
     */
    if (isset($startTime)) {
        /* Let's try with the endTime now */
        $db->unlock();
        header("location: makebooking.php?endTime=" . urlencode($endTime) . "&roomId=$roomId&date=" . urlencode($date));
        die();
    } else {
        /* Looks as though you have no Lesson before and a late lunch Duty */
        $db->unlock();
        $_SESSION['thereIsNoRoomForThisLesson'] = true;
        header("location: index.php?date=$date");
        die();
    }
}

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