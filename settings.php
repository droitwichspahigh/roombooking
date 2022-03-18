<?php
namespace Roombooking;

require "bin/classes.php";

if (Settings::getSetting(Settings::VERSION) == -1) {
    Settings::storeSetting(Settings::MAINTENANCE, "0");
    Settings::storeSetting(Settings::VERSION, "0");
}

foreach ([Settings::MAINTENANCE] as $s) {
    if (isset($_GET[$s])) {
        Settings::storeSetting($s, $_GET[$s]);
        die($s);
    }
}

?>
<!DOCTYPE html>
<html>
<head>
<?php
require "bin/head.php";
?>
<script>
function changeChkBoxSetting(name) {
	value = $('input#' + name)[0].checked ? 1 : 0;
	label = $('label[for=' + name + ']')[0];
	label.classList.remove("text-success");
	label.classList.add("text-warning");
	var xhr = new XMLHttpRequest();
    xhr.addEventListener("load", settingSaved);
    xhr.open("GET", 'settings.php?' + name + '=' + value);
	xhr.send();
}

function settingSaved() {
	label = $('label[for=' + this.response + ']')[0];
	label.classList.remove("text-warning");
	label.classList.add("text-success");	
}

</script>
</head>
<body>
    <div class="container">
    	<h1>Room Booking Settings</h1>
        <div class="row mb-3">
          <label for="maintenance" class="col-sm-2 col-form-label">Maintenance mode</label>
          <div class="col-sm-10">
            <input type="checkbox" class="form-control" id="<?= Settings::MAINTENANCE ?>" placeholder="maintenance" onchange="changeChkBoxSetting('<?= Settings::MAINTENANCE ?>')" <?= Settings::getSetting(Settings::MAINTENANCE) == "1" ? 'checked' : '' ?>>
          </div>
        </div>
        <div><a href="index.php">Back to Room Booking System</a></div>
    </div>
</body>
</html>