<?php namespace Roombooking; ?>
<!doctype html>
<html>
<head>
<?php

require "bin/classes.php";
require "bin/head.php";

$school = new School();

/**
 * TODO this validation for dates is inadequate, as calendar fortnight isn't what we
 * want- we want today and next nine working days 
 */
if (isset($_GET['date'])) {
    $date = strtotime($_GET['date']);
    if ($date < strtotime('yesterday') || $date > strtotime('next fortnight')) {
        unset ($date);
    }
}
if (!isset($date)) {
    $date = time();
}
$date = date("Y-m-d", $date);

?>
</head>
<body>
	<div class="container">
		<h3 class="mb-3">Welcome to the <?= Config::site ?></h3>
		<form method="GET">
    		<div class="form-group row">
    			<label for="date-input" class="col-2 col-form-label">Date</label>
      			<div class="col-10">
        			<input class="form-control" type="date" id="date-input" name="date" value="<?= $date; ?>" onchange="this.form.submit()">
      			</div>
    		</div>
		</form>
		<?php
		/** @var Day $day */
		$day = $school->getDay($date);
		if ($day === null || $day->isTermDay() === false) {
		    die ('<div class="alert alert-warning">' . $date . ' is not actually in term time.  Please choose a different date</div>');
		}
		
		?>
		<div class="table-responsive">
    		<table class="table table-striped table-bordered text-center">
    			<thead>
    				<tr>
    					<th>&nbsp;</th>
    					<?php
    					 /** @var \RoomBooking\Room $r */
    					 foreach ($school->getRooms() as $r) {
    				        echo "<th>" . $r->getName() . "</th>";
    				     }
    				    ?>
    				</tr>
    			</thead>
    			<?php 
    			 foreach ($school->getPeriods() as $p) {
    			     echo "<tr><th>" . $p->getName() . "</th>";
    			     foreach ($school->getRooms() as $r) {
    			         echo "<td>";
    			         $e = $r->getEntry($p, $date);
    			         if (is_null($e)) {
    			             echo "Bookable!";
    			         } else {
    			             echo $e->getName() . "<br />" . $e->strStaff();
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