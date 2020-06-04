<?php
namespace Roombooking;

require "bin/classes.php";

$school = new School();
$db = new Database();

$lessonId = $_GET['cancelBooking'];
$date = $_GET['date'];

/* Is this actually my booking? */
$checkQuery = new \Arbor\Query\Query(\Arbor\Resource\ResourceType::CALENDAR_ENTRY_MAPPING);
$checkQuery->addPropertyFilter(\Arbor\Model\CalendarEntryMapping::CALENDAR, \Arbor\Query\Query::OPERATOR_EQUALS, '/rest-v2/calendars/' . $school->getCurrentlyLoggedInStaff()->getCalendarId());
$checkQuery->addPropertyFilter(\Arbor\Model\CalendarEntryMapping::EVENT, \Arbor\Query\Query::OPERATOR_EQUALS, '/rest-v2/sessions/' . $lessonId);
if (!isset ((\Arbor\Model\CalendarEntryMapping::query($checkQuery))[0])) {
    $_SESSION['thatIsNotYourLesson'] = true;
    header("location: index.php?date=$date");
    die();
}

$db->lock('roomchanges');

$roomDbRow = $db->dosql("SELECT * FROM roomchanges WHERE lesson_id = '$lessonId';")->fetch_array(MYSQLI_ASSOC);

$session = \Arbor\Model\Session::retrieve($lessonId);
$session->setLocation(\Arbor\Model\Room::retrieve($roomDbRow['oldroom_id']));
$session->save();

$db->dosql("DELETE FROM roomchanges WHERE id = '" . $roomDbRow['id'] . "';");

/* Need to invalidate the Query data now, as timetable is new */
$school->resetQuery();

$db->unlock();

header("location: index.php?date=$date");