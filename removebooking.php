<?php
namespace Roombooking;

require "bin/classes.php";

if (!(isset($_GET['date']) && (isset($_GET['cancelBooking']) || isset($_GET['cancelUnavailability'])))) {
    die("Issue with your GET variables-- stop trying to hack!");
}
$date = $_GET['date'];

$school = new School($date);
$db = new Database();

if (isset($_GET['cancelBooking'])) {
    $lessonId = $_GET['cancelBooking'];
    if (!in_array($auth_user, Config::admin_users)) {
        /* Is this actually my booking? */
        $checkQuery = new \Arbor\Query\Query(\Arbor\Resource\ResourceType::CALENDAR_ENTRY_MAPPING);
        $checkQuery->addPropertyFilter(\Arbor\Model\CalendarEntryMapping::CALENDAR, \Arbor\Query\Query::OPERATOR_EQUALS, '/rest-v2/calendars/' . $school->getCurrentlyLoggedInStaff()->getCalendarId());
        $checkQuery->addPropertyFilter(\Arbor\Model\CalendarEntryMapping::EVENT, \Arbor\Query\Query::OPERATOR_EQUALS, '/rest-v2/sessions/' . $lessonId);
        if (!isset ((\Arbor\Model\CalendarEntryMapping::query($checkQuery))[0])) {
            $_SESSION['thatIsNotYourLesson'] = true;
            header("location: index.php?date=$date");
            die();
        }
    }
    
    $db->lock('roomchanges');
    
    $roomDbRow = $db->dosql("SELECT * FROM roomchanges WHERE lesson_id = '$lessonId';")->fetch_array(MYSQLI_ASSOC);
    
    $session = \Arbor\Model\Session::retrieve($lessonId);
    $session->setLocation(\Arbor\Model\Room::retrieve($roomDbRow['oldroom_id']));
    $session->save();
    
    $db->dosql("DELETE FROM roomchanges WHERE id = '" . $roomDbRow['id'] . "';");
} elseif (isset($_GET['cancelUnavailability'])) {
    $unavail = \Arbor\Model\RoomUnavailability::retrieve($_GET['cancelUnavailability']);
    $api = \Arbor\Model\ModelBase::getDefaultGateway();

    $descr = $unavail->getProperty(\Arbor\Model\RoomUnavailability::REASON);
    $m = [];
    if (preg_match('/\((\d+)\)$/', $descr, $m) == 0) {
        die("This was not booked by the Room Booking Service, please contact your Arbor Administrator.");
    }
    
    if ($m[1] != $school->getCurrentlyLoggedInStaff()->getId() && !in_array($auth_user, Config::admin_users)) {
        die("This is not your booking!");
    }
    $api->delete($unavail);
}

/* Need to invalidate the Query data now, as timetable is new */
$school->resetQuery();

$db->unlock();

header("location: index.php?date=$date");