<?php
namespace Roombooking;

require "bin/classes.php";

if (!isset($_GET['date'])) {
    die("Don't call this directly.");
}

$date = $_GET['date'];

$school = new School($date);
$db = new Database();

if (strtotime($date) > strtotime($school->getTenWorkingDaysFromNow()->getDate())) {
    $_SESSION['dateTooFarInAdvance'] = true;
    header("Location: index.php?date=$date");
    die();
}

foreach (['roomId', 'date'] as $g) {
    if (!isset($_GET[$g])) {
        header("location: index.php");
    }
}

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
    if (!isset($_GET['lessonId'])) {
        $db->unlock();
        /* There is a conflict, that's weird */
        echo "<h1>Arbor thinks that you have two lessons.  Please click the lesson that you wish to book</h1>";
        echo "<ul>";
        foreach ($staffCal as $cal) {
            echo "<li><a href=\"{$_SERVER['REQUEST_URI']}&lessonId={$cal['lesson']['id']}\">";
            echo "{$cal['lesson']['displayName']}</a></li>";
        }
        die ("</ul>");
    } else {
        foreach ($staffCal as $cal) {
            if ($cal['lesson']['id'] == $_GET['lessonId']) {
                $selectedCal = $cal;
                break;
            }
        }
    }
} else {
    $selectedCal = $staffCal[0];
}

$LessonId = $selectedCal['lesson']['id'];
$oldLessonRoomId= $selectedCal['lesson']['location']['id'];

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
$roomName = $school->getRooms()[$roomId]->getName();
$humanreadable = $db->escape("$startTime {$school->getCurrentlyLoggedInStaff()->getName()} {$selectedCal['lesson']['displayName']}");
$db->dosql("INSERT INTO roomchanges (lesson_id, oldroom_id, booking_calendar, humanreadable, roomname, lessondate) VALUES ($LessonId, $oldLessonRoomId, $myCalendarId, '$humanreadable', '$roomName', '$date');");

$session = \Arbor\Model\Session::retrieve($LessonId);
$session->setLocation(\Arbor\Model\Room::retrieve($roomId));
$session->save();

/* Need to hack this into the Query data now, as timetable has changed */
/* XXX This is so evil, but it works I guess */

$selectedCal['lesson']['location']['id'] = $roomId;
array_push($_SESSION['School_queryData'][$date]['CalendarEntryMapping'], $selectedCal);


$db->unlock();

header("location: index.php?date=$date");