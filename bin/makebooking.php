<?php
namespace Roombooking;

/* Included from index.php */

$startTime = $_GET['period'];
$roomId = $_GET['roomId'];
$client = new GraphQLClient();

/** @var string $date Defined in index.php */
/** @var \Roombooking\School $school */

/* What lesson and Room does the staff member have at this point? */

$queryData = $client->rawQuery('
query {
    CalendarEntryMapping (calendar__id: ' . $school->getStaffCalendarId($school->getLoggedInStaffId()) . ' startDatetime: "' . $date . " " . $startTime . '") {
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
}')->getData()['CalendarEntryMapping'];

if (!isset($queryData[0])) {
    /* TODO don't die! */
    die ("No lesson");
}

if (isset($queryData[1])) {
    /* There is a conflict, that's weird */
    die ("Conflicting lessons?");
}

$LessonId = $queryData[0]['lesson']['id'];
$oldLessonRoomId= $queryData[0]['lesson']['location']['id'];

/* Store the old room in the database */

$db = new Database();
$db->dosql("INSERT INTO roomchanges (lesson_id, oldroom_id) VALUES ($LessonId, $oldLessonRoomId);");

/* TODO Write the room change to Arbor */

$session = \Arbor\Model\Session::retrieve($LessonId);
$session->setLocation(\Arbor\Model\Room::retrieve($roomId));
$session->save();

/* Need to invalidate the Query data now, as timetable is new */
unset($_SESSION['School_queryData']);

die($school->getStaffCalendarId($school->getLoggedInStaffId()));

die("<pre>" . print_r($queryData, true));