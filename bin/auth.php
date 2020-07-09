<?php
namespace Roombooking;

/* User authenticated? */

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header("location: " . Config::site_url . '/');
}

$auth_user = preg_replace('/@' . Config::site_emaildomain . '/', "", $_SERVER['PHP_AUTH_USER']);

/* So, let's check this user should actually be here! */

/* Let's explicitly keep kids out, as staff regex may match kids! */
if (Config::is_student($auth_user) || !Config::is_staff($auth_user)) {
    header("location: " . Config::$site_url . "/denied.php");
}

/* These are not the droids you have been looking for */