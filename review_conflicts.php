<?php
namespace Roombooking;

require "bin/classes.php";

$date = date('Y-m-d');
$school = new School($date);
$db = new Database();

// Make sure that we check the next four weeks-- this could be slow!

for ($d=0; $d<28; $d++) {
    $date = date('Y-m-d', strtotime('+1 day', strtotime($date)));
    echo $date;
    $school->getTimetables($date);
}

?>
<!doctype html>
<html>
<head>
<?php
require "bin/head.php";
?>
</head>
<body>
<div class="container">
<?php 
if (!isset($_SESSION['roomBookingConflict'])) {
    ?>
    <div class="row h1">There are no conflicts in room bookings at this time.  Congratulations.</div>
    <?php
    die();
}

$bookedLessons = [];
foreach ($db->dosql("SELECT * FROM roomchanges;")->fetch_all(MYSQLI_ASSOC) as $r) {
    $bookedLessons[$r['lesson_id']] = $r['booking_calendar'];
}
?>
<div class="h3">Click the button next to a conflict to resolve by cancelling the booking.</div>
<div class="text-danger font-weight-bold">MAKE SURE YOU EMAIL THE VICTIM OR THEY WILL NOT FIND OUT!</div>
<table class="table table-hover">

<thead>
	<tr>
		<th>Date/time</th>
	
		<th>Lesson booked</th>
		
		<th>Lesson that is now scheduled</th>
		
		<th>Action</th>
	</tr>
</thead>

<?php
$conflictShown = [];
foreach ($_SESSION['roomBookingConflict'] as $conflict) {
    if ($conflict[0] instanceOf Unavailability) {
        $unavail = $conflict[0];
        $booking = $conflict[1];
    }
    if ($conflict[1] instanceOf Unavailability) {
        $booking = $conflict[0];
        $unavail = $conflict[1];
    }
    if (isset($unavail)) {
        print "<td>";
        print $booking->getInfo();
        print "</td><td>Unavailable</td></tr>";
        continue;
    }
    if (isset($bookedLessons[$conflict[0]->getId()])) {
        $booking = $conflict[0];
        $c = $conflict[1];
        $bookingId = $booking->getId();
    } elseif (isset($bookedLessons[$conflict[1]->getId()])) {
        $c = $conflict[0];
        $booking = $conflict[1];
        $bookingId = $booking->getId();
    } else {
        $c = $conflict[0];
        $booking = $conflict[1];
    }
    if (isset($c)) {
        print "<tr><td>";
        print $conflict[0]->getDay()->getDate();
        print "<br />";
        print $conflict[0]->getPeriod()->getName();
        print "</td>";
        
        /** @var Lesson $booking */
        if (in_array($c->getId(), $conflictShown)) {
            continue;
        }
        array_push($conflictShown, $c->getId());
        print "<td>";
        print $booking->getInfo();
        print "</td><td>";
        print $c->getInfo();
        print "</td>";
        if (isset($bookingId)) {
            print "<td>";
            print "<a class=\"btn btn-danger\" href=\"removebooking.php?cancelBooking=$bookingId\">I have emailed {$booking->strStaff()}- remove booking</a>";
            unset ($bookingId);
        } else {
            print "<td>This needs sorting in Arbor</td>";
        }
        print "</tr>";
    }
}
?>
</table>
</div>
</body>
</html>