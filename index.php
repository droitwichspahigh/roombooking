<?php namespace Roombooking;

if(!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="CSE2K"');
    header('HTTP/1.0 401 Unauthorized');
    die();
}

require "bin/classes.php";
/** @var $auth_user // from auth.php */

function nonLessonRow($school, $rooms, $name, $date, $startTime, $endTime) {
    global $auth_user;
    $startHhmm = substr($startTime, 0, 5);
    $endHhmm = substr($endTime, 0, 5);
    echo "<tr><th title=\"$startHhmm-$endHhmm\">$name</th>";
    foreach ($rooms as $r) {
        $cellTextArr = [];
        $entries = $r->getEntriesForPeriod(new Period(-1, $name, $startTime, $endTime), $date);
        usort($entries, function (Event $a, Event $b) { 
            $a = ($a instanceOf Unavailability) ? $a : $a->getPeriod;
            $b = ($b instanceOf Unavailability) ? $b : $b->getPeriod;
            return strtotime($a->getStartTime()) <=> strtotime($b->getStartTime()); });
        //$entries = array_merge($unavail, $other);
        foreach ($entries as $e) {
                $descr = str_replace(': ', '<br />', str_replace('Room Unavailable: ', '', $e->getInfo($date)));
                $m = [];
                if (preg_match('/^(.*) \((\d+)\)$/', $descr, $m) == 1) {
                    if ($m[2] == $school->getCurrentlyLoggedInStaff()->getId()) {
                        $descr = "<a href=\"removebooking.php?cancelUnavailability={$e->getPositiveId()}&date=$date\" class=\"btn btn-primary stretched-link\">{$m[1]}</a>";
                    } elseif (in_array($auth_user, Config::admin_users)) {
                        $descr = "<a href=\"removebooking.php?cancelUnavailability={$e->getPositiveId()}&date=$date\" class=\"btn btn-danger stretched-link\">{$m[1]}</a>";
                    }
                }
                array_push($cellTextArr, "$descr");
            }
            $cellText = implode('<br />', $cellTextArr);
            echo "<td>$cellText</td>";
    }
    echo "</tr>\n";
}

$school = new School();
$db = new Database();

$bookableRooms = [];
foreach (Config::roomFeatureName as $feature) {
    foreach ($school->getBookableRooms($feature) as $r) {
        array_push($bookableRooms, $r);
    }
}

if (isset($_GET['date'])) {
    $date = strtotime($_GET['date']);
    if ($date < strtotime('today')) {
        unset ($date);
    }
}
if (!isset($date)) {
    $date = time();
}
$date = date("Y-m-d", $date);

/* OK, now let's deal with some popups */
if (isset($_SESSION['dateTooFarInAdvance'])) {
    unset($_SESSION['dateTooFarInAdvance']);
    $modalmsg = "You may only make lesson bookings from today up to ten working days from today.";
}
if (isset($_SESSION['roomBookingConflict'])) {
    $modalmsg = "WARNING: There are some conflicts in scheduling.  If you would like to help, please <a href=\"review_conflicts.php\">review them</a> and email whoever has lost a booking to let them know.";
}
if (isset ($_SESSION['thereIsNoLessonAtThisTime'])) {
    unset ($_SESSION['thereIsNoLessonAtThisTime']);
    /* Do a popup here about there being no lesson for the teacher! */
    $modalmsg = "You don't appear to have a lesson scheduled during this period.";
}
if (isset ($_SESSION['someoneHasPippedYouToThePost'])) {
    unset ($_SESSION['someoneHasPippedYouToThePost']);
    $modalmsg = "Someone has just booked that slot before you did!  Sorry, please try another.";
}
if (isset ($_SESSION['thatIsNotYourLesson'])) {
    unset ($_SESSION['thatIsNotYourLesson']);
    /* Do a popup here about there not being allowed to unbook someone else's booking! */
    $modalmsg = "You can't unbook someone else's lesson!";
}
/* This happens if for example you try to book when on duty */
if (isset($_SESSION['thereIsNoRoomForThisLesson'])) {
    unset ($_SESSION['thereIsNoRoomForThisLesson']);
    $modalmsg = "Hm, there is no Room scheduled for this lesson- are you actually teaching?";
}

?>
<!doctype html>
<html>
<head>
<?php
require "bin/head.php"; 
?>
<script>
adhocRoomId = 0;
function showAdhocBooking(id, name) {
	$('#adhocBook').modal('show');
    date = $('input#date-input')[0].value;
	$('span#adhocBookRoomName')[0].innerHTML = name + " on " + date;
	adhocRoomId = id;
}
function makeAdhocBooking() {
	var xhr = new XMLHttpRequest();
    xhr.open("POST", 'async/newUnavailability.php', true);
    
    //Send the proper header information along with the request
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          adhocBookingHandle(this.responseText);
        }
    };
	$('div#adhocError')[0].innerHTML = "Working...";
	$('div#adhocError').removeClass('hidden');
    startTime = encodeURIComponent($('input#adhocBookStartTime')[0].value);
    endTime = encodeURIComponent($('input#adhocBookEndTime')[0].value);
    date = encodeURIComponent($('input#date-input')[0].value);
    reason = encodeURIComponent($('input#adhocBookDetails')[0].value);
    xhr.send("startTime=" + startTime + "&endTime=" + endTime + "&date=" + date + "&roomId=" + adhocRoomId + "&reason=" + reason);
}
function adhocBookingHandle(response) {
	if (response == "Success") {
		$('div#adhocError')[0].innerHTML = "Done!  Please wait...";
		location.reload(true);
	} else {
		// Indicate error
		$('div#adhocError')[0].innerHTML = response;
	}	
}
</script>
</head>
<body>
<?php
if (isset($modalmsg)) {
    echo <<< EOF
    <div class="modal fade" id="msgBox" tabindex="-1" role="dialog" aria-labelledby="msgBoxLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="msgBoxLabel">There is a problem</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            $modalmsg
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
    <script>$('#msgBox').modal('show')</script>
EOF;
}
?>
<!-- Ad-hoc booking modal -->
<div class="modal fade" id="adhocBook" tabindex="-1" role="dialog" aria-labelledby="adhocBooking" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="adhocBookLabel">Non-lesson booking</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div><strong>Book room <span id="adhocBookRoomName"></span>, for something other than a lesson.</strong></div>
        <div class="form-group">
        	<label for="adhocBookDetails">Booking details</label>
    		<input type="text" class="form-control" id="adhocBookDetails" aria-describedby="adhocBookDetails" placeholder="Describe why you are booking the room">
        </div>
        <div class="form-group">
			<label for="adhocBookStartTime">Start time</label>
    		<input type="time" class="form-control" id="adhocBookStartTime" aria-describedby="adhocBookStartTime">
        </div>
        <div class="form-group">
			<label for="adhocBookEndTime">End time</label>
    		<input type="time" class="form-control" id="adhocBookEndTime" aria-describedby="adhocBookEndTime">
        </div>
        <div id="adhocError" class="text-danger hidden"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="makeAdhocBooking()">Make booking</button>
      </div>
    </div>
  </div>
</div>
	<div class="container">
		<nav class="navbar navbar-expand">
            <!-- Brand -->
            <a class="navbar-brand"><?php print ($auth_user);
            if (in_array($auth_user, Config::admin_users)) {
                print ("<span class=\"text-warning\"> (administrator)</span>");
                if (Settings::getSetting(Settings::MAINTENANCE)) {
                    print "<span class=\"text-danger\"> (maintenance mode)</span>";
                }
            }?></a>
            
            <!-- Toggler/collapsibe Button -->
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
            	<span class="navbar-toggler-icon">collapse</span>
            </button>
            
            <!-- Navbar links -->
            <div class="collapse navbar-collapse" id="collapsibleNavbar">
            	<ul class="navbar-nav">
            		<li class="nav-item">
                		<a class="nav-link" href="?date=<?= $date; ?>&session_destroy=<?= $_SESSION['SESSION_CREATIONTIME']; ?>">Rescan Arbor for changes</a>
                	</li>
            	</ul>
        	</div>
        	<?php if (in_array($auth_user, Config::admin_users)) { ?>
        	<div class="collapse navbar-collapse" id="collapsibleNavbar">
            	<ul class="navbar-nav">
            		<li class="nav-item">
                		<a class="nav-link" href="clear_cache.php">Clear the cache and rescan Arbor</a>
                	</li>
            	</ul>
            	<ul class="navbar-nav">
            		<li class="nav-item">
                		<a class="nav-link" href="settings.php">Change settings</a>
                	</li>
            	</ul>
        	</div>
        	
        	<div class="collapse navbar-collapse" id="collapsibleNavbar">
            	<ul class="navbar-nav">
            		<li class="nav-item">
                		<a class="nav-link" href="review_conflicts.php">Check for and review scheduling conflicts</a>
                	</li>
            	</ul>
        	</div>
        	<?php } /* Admin bit */ ?>
        </nav>
		<h3 class="mb-4"><?= Config::site ?></h3>
		<form method="GET">
    		<div class="form-group row">
    			<label for="date-input" class="col-2 col-form-label">Date</label>
      			<div class="col-10">
        			<input class="form-control" type="date" id="date-input" name="date" value="<?= $date; ?>" onchange="this.form.submit()">
      			</div>
    		</div>
		</form>
		<?php
		$day = $school->getDay($date);
		if ($day === null || $day->isTermDay() === false) {
		    die ('<div class="alert alert-warning">' . $date . ' is not actually a working day.  Please choose a different date</div>');
		}
		?>
		<div class="table-responsive">
    		<table class="table table-striped table-bordered text-center table-sm">
    			<thead>
    				<tr>
    					<th>&nbsp;</th>
    					<?php
    					 foreach ($bookableRooms as $r) {
    					     $roomHeader = $r->getName();
    					     if ($r->getCapacity() !== null) {
    					         $roomHeader .= " ({$r->getCapacity()} students)";
    					     }
    					     echo "<th onclick=\"showAdhocBooking({$r->getId()}, '{$r->getName()}')\">" . $roomHeader . "</th>";
    					 }
    				    ?>
    				</tr>
    			</thead>
    			<?php
    			 /* Fetch the lessons from the Database */
                 $bookedLessons = [];
    	       	 foreach ($db->dosql("SELECT * FROM roomchanges;")->fetch_all(MYSQLI_ASSOC) as $r) {
    			     $bookedLessons[$r['lesson_id']] = $r['booking_calendar'];
    			 }
    			 // Before school, check for Unavailability
    			 nonLessonRow($school, $bookableRooms, "Early", $date, "00:00", array_values($school->getPeriods())[0]->getStartTime());
    			 $previousPeriod = null;
    			 foreach ($school->getPeriods() as $p) {
    			     if (!is_null($previousPeriod)) {
    			         if ($previousPeriod->getEndTime() != $p->getStartTime()) {
    			             // Must be a break
    			             nonLessonRow($school, $bookableRooms, "B", $date, $previousPeriod->getEndTime(), $p->getStartTime());
    			         }
    			     }
    			     $previousPeriod = $p;
    			     echo "<tr><th title=\"{$p->getStartTime(true)}-{$p->getEndTime(true)}\">{$p->getName()}</th>";
    			     foreach ($bookableRooms as $r) {
    			         echo "<td>";
    			         $e = $r->getEntry($p, $date);
    			         if (is_null($e)) {
    			             echo "<a href=\"makebooking.php?startTime=" . urlencode($p->getStartTime()) . "&endTime=" . urlencode($p->getEndTime()) . "&roomId=" . $r->getId() . "&date=" . $date . "\" class=\"btn btn-secondary stretched-link\">Book</a>";
    			         } else {
			                 $info = $e->getInfo($date);
			                 if ($e instanceOf Unavailability) {
			                     $info = str_replace(': ', '<br />', str_replace('Room Unavailable: ', '', $info));
			                     $m = [];
			                     if (preg_match('/^(.*) \((\d+)\)$/', $info, $m) == 1) {
			                         if ($m[2] == $school->getCurrentlyLoggedInStaff()->getId()) {
			                             $info = "<a href=\"removebooking.php?cancelUnavailability={$e->getPositiveId()}&date=$date\" class=\"btn btn-primary stretched-link\">{$m[1]}</a>";
			                         } elseif (in_array($auth_user, Config::admin_users)) {
			                             $info = "<a href=\"removebooking.php?cancelUnavailability={$e->getPositiveId()}&date=$date\" class=\"btn btn-danger stretched-link\">{$m[1]}</a>";
			                         }
			                     }
			                 } else {
        			             /* Is this my booking? */
        			             if (isset ($bookedLessons[$e->getId()])) {
        			                 if ($bookedLessons[$e->getId()] == $school->getCurrentlyLoggedInStaff()->getCalendarId()) {
        			                     $info = "<a href=\"removebooking.php?cancelBooking=" . $e->getId() . "&date=" . $date . "\" class=\"btn btn-primary stretched-link\">" . $info . "</a>";
        			                 } elseif (in_array($auth_user, Config::admin_users)) {
        			                     $info = "<a href=\"removebooking.php?cancelBooking=" . $e->getId() . "&date=" . $date . "\" class=\"btn btn-danger stretched-link\">" . $info . "</a>";
        			                 }
        			             }
			                 }
        			         echo $info;
    			         }
    			     }
    			     echo "</tr>\n";
    			 }
    			 // After school, check for Unavailability
    			 foreach ($school->getPeriods() as $p) {
    			     $lastPeriod = $p;
    			 }
    			 nonLessonRow($school, $bookableRooms, "Late", $date, $lastPeriod->getEndTime(), "23:59");
    			 ?>
    		</table>
		</div>
	</div>
</body>
</html>