<?php namespace Roombooking; ?>
<!doctype html>
<html>
<head>
<?php

require "bin/classes.php";
require "bin/head.php";



$school = new School();



/* TODO make a date setting thingy */
$date = "2020-05-20"; 

?>
</head>
<body>
	<div class="container">
		<h3 class="mb-3">Welcome to the <?= Config::site ?></h3>
		<div class="form-group row">
			<label for="date-input" class="col-2 col-form-label">Date</label>
  			<div class="col-10">
    			<input class="form-control" type="date" id="date-input" value="2020-05-20">
  			</div>
		</div>
		<table class="table table-striped table-bordered">
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
			     echo "<tr><th>" . $p . "</th>";
			     foreach ($school->getRooms() as $r) {
			         echo "<td>";
			         $e = $r->getEntry($p, $date);
			         if (is_null($e)) {
			             echo "Bookable!";
			         } else {
			             echo $e[Room::LESSON]->getName() . "<br />" . $e[Room::LESSON]->strStaff();
			         }
			         
			     }
			     echo "</tr>";
			 }
			?>
		</table>
	</div>
</body>
</html>