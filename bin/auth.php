<?php
namespace Roombooking;

/* User authenticated? */

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header("location: " . Config::site_url . '/');
}

$auth_user = preg_replace('/@' . Config::site_emaildomain . '/', "", $_SERVER['PHP_AUTH_USER']);

if (!in_array($auth_user, Config::admin_users)) {
    if (Settings::getSetting(Settings::MAINTENANCE)) {
        die("Sorry, the Room Booking Service is under maintenance currently.  Please try again later.");
    }
    switch(basename($_SERVER['PHP_SELF'])) {
    case "settings.php":
    case "report.php":
        die('Admins only');
    default:
        break;
    }
}

// Turn on debugging if maintenance mode is on

if (Settings::getSetting(Settings::MAINTENANCE)) {
    ini_set('display_startup_errors', 1);
    ini_set('display_errors', 1);
    error_reporting(-1);
}

/* So, let's check this user should actually be here! */

/* Let's explicitly keep kids out, as staff regex may match kids! */
if (Config::is_student($auth_user) || !Config::is_staff($auth_user)) {
    header("location: " . Config::site_url . "/denied.php");
}

/* These are not the droids you have been looking for */