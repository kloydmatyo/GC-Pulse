<?php

require("vendor/autoload.php");

$goo = new Google\Client();
$goo->setClientId("921411307007-5o6gd8bn4vgkl7h07u8feo9lj6bdgci1.apps.googleusercontent.com");
$goo->setClientSecret("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");
$goo->setHostedDomain('gordoncollege.edu.ph');
$goo->setRedirectUri("http://localhost/gcpulseeee/login.php");
$goo->addScope("email");
$goo->addScope("profile");
?>