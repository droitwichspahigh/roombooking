<?php namespace Roombooking;

require "bin/classes.php";

$school = new School();
$db = new Database();

if (isset($_GET['date'])) {
    $date = strtotime($_GET['date']);
    if ($date < strtotime('today') || $date > strtotime($school->getTenWorkingDaysFromNow()->getDate())) {
        unset ($date);
        $modalmsg = "You may only make bookings from today up to ten working days from today.";
    }
}
if (!isset($date)) {
    $date = time();
}
$date = date("Y-m-d", $date);

/* OK, now let's deal with some popups */
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

?>
<!doctype html>
<html>
<head>
<?php
require "bin/head.php"; 
?>
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
	<div class="container">
		<nav class="navbar navbar-expand">
            <!-- Brand -->
            <a class="navbar-brand">Actions</a>
            
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
        	<div class="collapse navbar-collapse" id="collapsibleNavbar">
            	<ul class="navbar-nav">
            		<li class="nav-item">
                		<a class="nav-link" href="clear_cache.php" disabled>Clear the cache and rescan Arbor</a>
                	</li>
            	</ul>
        	</div>
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
    					 foreach ($school->getIctRooms() as $r) {
    				        echo "<th>" . $r->getName() . "</th>";
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
    			 foreach ($school->getPeriods() as $p) {
    			     echo "<tr><th>" . $p->getName() . "</th>";
    			     foreach ($school->getIctRooms() as $rId => $r) {
    			         echo "<td>";
    			         $e = $r->getEntry($p, $date);
    			         if (is_null($e)) {
    			             echo "<a href=\"makebooking.php?startTime=" . urlencode($p->getStartTime()) . "&roomId=" . $rId . "&date=" . $date . "\" class=\"btn btn-secondary stretched-link\">Book</a>";
    			         } else {
    			             $info = $e->getInfo();
    			             /* Is this my booking? */
    			             if (isset ($bookedLessons[$e->getId()]) && $bookedLessons[$e->getId()] == $school->getCurrentlyLoggedInStaff()->getCalendarId()) {
    			                 $info = "<a href=\"removebooking.php?cancelBooking=" . $e->getId() . "&date=" . $date . "\" class=\"btn btn-primary stretched-link\">" . $info . "</a>";
    			             }
    			             echo $info;
    			         }
    			     }
    			     echo "</tr>";
    			 }
    			?>
    		</table>
		</div>
	</div>
</body>
</html>