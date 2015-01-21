<?php
/**
 * UI Frontend for 3CX HTTP API Call Maker
 *
 * @author     Taylor Nolen <taylor@abptech.com>
 * @author     ABP Technologies <itadmin@abptech.com>
 * @copyright  2015 ABP Technologies
 * @license    The MIT License (MIT)
 * @link       http://abptech.com
 */

//cache-control and expires headers must be set to ensure browser doesn't cache old and expired dynamically generated link
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate( 'D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache'); 

/**
 * Include the functions file
 */
require_once("functions.php");

/**
 * Get Quesrystring Values
 */
$user			= $_GET['user'];
$num			= $_GET['num'];
$user_info		= getUserInfo($user); //populate $user_info with array(user_name, telephone_extension, telephone_pin)

if (isset($_GET['num'])) { //ensure that we were given a phone number to call

	$validate_phone_number 	= filterPBXPhoneNumber($num);	//filter bad phone numbers, will return either the phone number itself, or an error message

	if (isValidPhoneNumber($validate_phone_number)) { //ensure that the phone number is valid and not an error message
		
		/**
		 * Show Webpage
		 */
		?>
		
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml">
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<head>
		<title>Making Call to <?= $num; ?></title>	
		</head>
		<body>
			<div class="making_call">
				Placing call to: <?= formatPBXPhoneNumber($num); ?>			
			</div>		
		</body>
		</html>
		
		<?php
		/**
		 * Send HTML mark-up to the browser. We want the user to see the message indicating 
		 * that the call is being placed, then actually call the function below so if they
		 * get any lag from the time the HTML is finished rendering and the PBX actually gets
		 * the request and processes it and sends it to the user's phone, they aren't stuck
		 * thinking the browser is locked or something.
		 */
		 
		ob_flush();
		flush();
		
		make3CXPBXCall($user_info[1],$user_info[2],$validate_phone_number); //function that actually makes the call

	} else {
	echo getPhoneValidationError($validate_phone_number); //output error message
	}

} else {
echo "No phone number given, please close this window and try again.";	
exit;
}




