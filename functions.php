<<<<<<< HEAD
<?php
/**
 * Functions listing for 3CX HTTP API call making
 *
 * @author     Taylor Nolen <taylor@abptech.com>
 * @author     ABP Technologies <itadmin@abptech.com>
 * @copyright  2015 ABP Technologies
 * @license    The MIT License (MIT)
 * @link       http://abptech.com
 */

 
/**
* make3CXPBXCall()
*
* Puts together URL and POSTs to it using CURL which basically signals the PBX that a call should be initialized
* 
* When a call is placed using just the normal URL in a browser, it works. But when initialized using CURL,the 3CX web server returns
* a HTTP/1.1 301 error with a link to a different, dynamically generated URL that includes some type of 24 character hash.
*
* This is presumably a security feature that prevents the spamming of a URL to make calls in case someone was able to access the server
* from within the network. We get around this by calling the normal URL, then parsing the response and getting the newly generated
* URL and CURLing it again. The process is basically instant and there is no lag seen by the end user.
*
*/
function make3CXPBXCall ($extension, $pin, $external_number) {

	$your_pbx_url		= "http://your.pbx.com"; //your PBX's web URL. The same one you go to to get into the 3CX Phone System Management Console
	$external_number 	= filterPBXPhoneNumber($external_number);	//ensure this phone number is valid
		
	if ($external_number == 0 || $external_number == 1) { //number couldn't be validated correctly. basically a bad phone number was given. exit the script
	echo "Error Validating Phone Number. (" . $external_number . ")";	
	exit;
	} else {
		
		try {
			
			/**
			* format the initial URL. For v12.x for the 3CX PBX, the URL should follow this format:
			*  http://your.pbx.com/ivr/PbxAPI.aspx?func=make_call&from=XXXX&to=XXXXXXXXXX&pin=PHONEPASSWORD
			*
			* func=make_call  		This is the function you are telling the PBX to initiate. All available HTTP functions available here: 
			*    					http://www.3cx.com/blog/docs/3cx-http-api/
			*
			* from=XXXX  			This is the user's extension that is initiating the outbound call
			* 
			* to=XXXXXXXXXX  		This is either the internal extension OR external phone number the user is wanting to call
			*
			* pin=PHONEPASSWORD  	This is the PIN from the PHONES page in the 3CX Phone System Management Console. 
			* 						Typically a 5-10 digit password with alpha-numeric and punctuation characters
			*
			*/
			
			$url = $your_pbx_url . "/ivr/PbxAPI.aspx?func=make_call&from=" . $extension . "&to=" . $external_number . "&pin=" . urlencode($pin);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);			// the URL we want to CURL
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 	// output to string
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 	// make sure we don't verify ssl certificate if found
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);	// don't wait longer than 30 seconds to connect
			curl_setopt($ch, CURLOPT_TIMEOUT, 120);			// don't execute any longer than 2 minutes

			//execute and close the connection
			$result = curl_exec($ch);					
			curl_close($ch);
			
			/**
			*
			* Load the response into a DOMDocument object in order to parse out just the HREF value from the <A> tag. 
			* The response we received was similar to this:
			*
			* 	<html><head><title>Object moved</title></head><body>
			* 	<h2>Object moved to <a href="/ivr/(S(r0jyhxxdam0ndk5wnhfzay3a))/PbxAPI.aspx?func=make_call&amp;from=XXX&amp;to=XXXXXXXXXX&amp;pin=XXXXXXXXXX">here</a>.</h2>
			* 	</body></html>
			*
			*/
			
			$dom = new DOMDocument; 		// create the DOMDocument object 
			$dom->loadHTML($result);		// load the response as HTML
			$new_url	= $your_pbx_url;	// initial our new pbx url with the http://yourdomain.com string
			
			//cycle through the A elements. There will only be 1 found so we have no need for validating it
			foreach ($dom->getElementsByTagName('a') as $tag) {
			$new_url	.= $tag->getAttribute('href'); // append the href value to the $new_url variable
			}		
			
			//CURL the same as above to the newly aquired URL
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $new_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($ch, CURLOPT_TIMEOUT, 120);

			$result = curl_exec($ch);					
			curl_close($ch);		
		   
			//insertPBXCallLog ($_SERVER['PHP_AUTH_USER'],$extension,$external_number); // simple logging script, not needed for call functionality
			
		} catch (Exception $e) {
			
			//dump exception if found
			echo "Error making call: ";
			print_r($e);
			exit;
			
		}
	
	}

}


/**
* getUserInfo()
*
* Gets some user information used to formulate the URL for the pbx call
* Returns array(user_name, telephone_extension, telephone_pin)
*
*/
function getUserInfo ($user_email) {
	
	global $db_connection;
	
	$search_sql 	= "SELECT name, phone_ext, 3cx_phone_pin FROM `user_auth` WHERE `user_name` = '" . $user_email . "' ORDER BY last_modified DESC LIMIT 0,1";
		
	foreach($db_connection->query($search_sql) as $row) {
			
		$phone_ext		= $row{'phone_ext'};
		$phone_pin		= $row{'3cx_phone_pin'};
		$name			= $row{'name'};
	
	}
	
	return array($name,$phone_ext,$phone_pin);
	
}


/**
* filterPBXPhoneNumber()
*
* 	Filter out bad phone numbers
* 
* 	Error 10 - Number less than 10 digits
* 
* 	Error 20 - $unallowed_numbers are info and emergency numbers
* 	
* 	Error 30 - $unallowed_area_codes are toll area codes and informative ones, no need to allow them to call from here
* 	 Canada special services	600, 622, 633, 644, 655, 677, 688
*	 Inbound international	456
*	 Interexchange carrier-specific services	700
*	 Premium call services	900	
*	 US government	710	
* 	
*	Error 40 - $bad_area_codes are known scam area codes: Bahamas, Barbados, Anguilla, British Virgin Islands, Dominica, Trinidad and Tobago, Etc	
*/
function filterPBXPhoneNumber ($number) {

	$number					= trim($number);
	$reg_exp 				= '/[^\++0-9,.]/'; //match only numeric
	$clean_number 			= preg_replace($reg_exp,'', $number); //delete non-numeric from string
	$number_split			= str_split($clean_number); //$number_split becomes array of single digits of the number
	$starting_digit			= 0;
	$unallowed_numbers		= array(211,411,911);
	$unallowed_area_codes	= array(600,622,633,644,655,677,688,456,700,900,710);
	$bad_area_codes			= array(242,246,264,268,284,345,441,473,649,664,758,767,784,809,829,849,868,869,876); 	
	
	$output					= $clean_number; //set the output to the cleaned phone number. this will be the output IF there is no error found below
	
	//get the correct START of the phone number, disregard leading 1s and +s
	foreach($number_split as $number) {	
	
		if ($number == "+" || $number == "1") {
		$starting_digit++;
		} else {
		break;
		}	
	
	}
	
	$area_code	= substr($clean_number,$starting_digit,3);	//first 3 digits are the area code
	
	if (strlen($clean_number) < 10) {
	$output = 10; //has to be at least 10 digits
	}
		
	if (in_array($area_code,$unallowed_numbers)) {
	$output = 20; //emergency services
	}
	
	if (in_array($area_code,$unallowed_area_codes)) {
	$output = 30; //unallowed area codes
	}
	
	if (in_array($area_code,$bad_area_codes)) {
	$output = 40; //potentially fraudulent area codes
	}	
	
	return $output;

}


/**
* getPhoneValidationError()
*
* outputs the error received
* returns string
*
*/
function getPhoneValidationError($error_no) {
	
	$length	= strlen($error_no);
	
	if ($length > 11) {
	return "Error 50 - Phone Number is too long."; 
	} else {
		
		switch ($error_no) {
		
			case 10: return "Error 10 - Phone Number did not have enough digits. (Less than 10)"; break;
			case 20: return "Error 20 - Phone Number is of an emergency nature."; break;
			case 30: return "Error 30 - Phone Number is of an informative or toll-based nature."; break;
			case 40: return "Error 40 - Phone Number Area Code is known for possibly fraudulent behaviour."; break;
			
			default: return "Unknown Error"; break;
		
		}
	
	}
	
}

/**
* isValidPhoneNumber()
*
* tests $number input for valid 10 or 11 digit formatting
* returns bool (true|false)
*
*/
function isValidPhoneNumber ($number) {
	
	$lengths = array(10, 11);  
	$number = preg_replace('/D+/', '', $number);

	return in_array(strlen($number), $lengths);

}


/**
* formatPBXPhoneNumber()
*
* formats a number string into a proper telephone format
* returns string
*
*/
function formatPBXPhoneNumber($phoneNumber) {
	
    $phoneNumber = preg_replace('/[^0-9]/','',$phoneNumber); //get only numbers

    if(strlen($phoneNumber) > 10) {
		
		//separate the number into pieces to properly format. this is for an international NANPA number - + 1 (999) 444-4444
        $countryCode = substr($phoneNumber, 0, strlen($phoneNumber)-10);
        $areaCode = substr($phoneNumber, -10, 3);
        $nextThree = substr($phoneNumber, -7, 3);
        $lastFour = substr($phoneNumber, -4, 4);

        $phoneNumber = '+'.$countryCode.' ('.$areaCode.') '.$nextThree.'-'.$lastFour;
		
    } elseif (strlen($phoneNumber) == 10) {
		
		//formats into (999) 345-6789
        $areaCode = substr($phoneNumber, 0, 3);
        $nextThree = substr($phoneNumber, 3, 3);
        $lastFour = substr($phoneNumber, 6, 4);

        $phoneNumber = '('.$areaCode.') '.$nextThree.'-'.$lastFour;
		
    } elseif(strlen($phoneNumber) == 7) {
		
		//formats into 345-6789
        $nextThree = substr($phoneNumber, 0, 3);
        $lastFour = substr($phoneNumber, 3, 4);

        $phoneNumber = $nextThree.'-'.$lastFour;
		
    }

    return $phoneNumber;
	
}



=======
<?php
/**
 * Functions listing for 3CX HTTP API call making
 *
 * @author     Taylor Nolen <taylor@abptech.com>
 * @author     ABP Technologies <itadmin@abptech.com>
 * @copyright  2015 ABP Technologies
 * @license    The MIT License (MIT)
 * @link       http://abptech.com
 */

 
/**
* make3CXPBXCall()
*
* Puts together URL and POSTs to it using CURL which basically signals the PBX that a call should be initialized
* 
* When a call is placed using just the normal URL in a browser, it works. But when initialized using CURL,the 3CX web server returns
* a HTTP/1.1 301 error with a link to a different, dynamically generated URL that includes some type of 24 character hash.
*
* This is presumably a security feature that prevents the spamming of a URL to make calls in case someone was able to access the server
* from within the network. We get around this by calling the normal URL, then parsing the response and getting the newly generated
* URL and CURLing it again. The process is basically instant and there is no lag seen by the end user.
*
*/
function make3CXPBXCall ($extension, $pin, $external_number) {

	$your_pbx_url		= "http://your.pbx.com"; //your PBX's web URL. The same one you go to to get into the 3CX Phone System Management Console
	$external_number 	= filterPBXPhoneNumber($external_number);	//ensure this phone number is valid
		
	if ($external_number == 0 || $external_number == 1) { //number couldn't be validated correctly. basically a bad phone number was given. exit the script
	echo "Error Validating Phone Number. (" . $external_number . ")";	
	exit;
	} else {
		
		try {
			
			/**
			* format the initial URL. For v12.x for the 3CX PBX, the URL should follow this format:
			*  http://your.pbx.com/ivr/PbxAPI.aspx?func=make_call&from=XXXX&to=XXXXXXXXXX&pin=PHONEPASSWORD
			*
			* func=make_call  		This is the function you are telling the PBX to initiate. All available HTTP functions available here: 
			*    					http://www.3cx.com/blog/docs/3cx-http-api/
			*
			* from=XXXX  			This is the user's extension that is initiating the outbound call
			* 
			* to=XXXXXXXXXX  		This is either the internal extension OR external phone number the user is wanting to call
			*
			* pin=PHONEPASSWORD  	This is the PIN from the PHONES page in the 3CX Phone System Management Console. 
			* 						Typically a 5-10 digit password with alpha-numeric and punctuation characters
			*
			*/
			
			$url = $your_pbx_url . "/ivr/PbxAPI.aspx?func=make_call&from=" . $extension . "&to=" . $external_number . "&pin=" . urlencode($pin);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);			// the URL we want to CURL
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 	// output to string
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 	// make sure we don't verify ssl certificate if found
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);	// don't wait longer than 30 seconds to connect
			curl_setopt($ch, CURLOPT_TIMEOUT, 120);			// don't execute any longer than 2 minutes

			//execute and close the connection
			$result = curl_exec($ch);					
			curl_close($ch);
			
			/**
			*
			* Load the response into a DOMDocument object in order to parse out just the HREF value from the <A> tag. 
			* The response we received was similar to this:
			*
			* 	<html><head><title>Object moved</title></head><body>
			* 	<h2>Object moved to <a href="/ivr/(S(r0jyhxxdam0ndk5wnhfzay3a))/PbxAPI.aspx?func=make_call&amp;from=XXX&amp;to=XXXXXXXXXX&amp;pin=XXXXXXXXXX">here</a>.</h2>
			* 	</body></html>
			*
			*/
			
			$dom = new DOMDocument; 		// create the DOMDocument object 
			$dom->loadHTML($result);		// load the response as HTML
			$new_url	= $your_pbx_url;	// initial our new pbx url with the http://yourdomain.com string
			
			//cycle through the A elements. There will only be 1 found so we have no need for validating it
			foreach ($dom->getElementsByTagName('a') as $tag) {
			$new_url	.= $tag->getAttribute('href'); // append the href value to the $new_url variable
			}		
			
			//CURL the same as above to the newly aquired URL
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $new_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($ch, CURLOPT_TIMEOUT, 120);

			$result = curl_exec($ch);					
			curl_close($ch);		
		   
			//insertPBXCallLog ($_SERVER['PHP_AUTH_USER'],$extension,$external_number); // simple logging script, not needed for call functionality
			
		} catch (Exception $e) {
			
			//dump exception if found
			echo "Error making call: ";
			print_r($e);
			exit;
			
		}
	
	}

}


/**
* getUserInfo()
*
* Gets some user information used to formulate the URL for the pbx call
* Returns array(user_name, telephone_extension, telephone_pin)
*
*/
function getUserInfo ($user_email) {
	
	global $db_connection;
	
	$search_sql 	= "SELECT name, phone_ext, 3cx_phone_pin FROM `user_auth` WHERE `user_name` = '" . $user_email . "' ORDER BY last_modified DESC LIMIT 0,1";
		
	foreach($db_connection->query($search_sql) as $row) {
			
		$phone_ext		= $row{'phone_ext'};
		$phone_pin		= $row{'3cx_phone_pin'};
		$name			= $row{'name'};
	
	}
	
	return array($name,$phone_ext,$phone_pin);
	
}


/**
* filterPBXPhoneNumber()
*
* 	Filter out bad phone numbers
* 
* 	Error 10 - Number less than 10 digits
* 
* 	Error 20 - $unallowed_numbers are info and emergency numbers
* 	
* 	Error 30 - $unallowed_area_codes are toll area codes and informative ones, no need to allow them to call from here
* 	 Canada special services	600, 622, 633, 644, 655, 677, 688
*	 Inbound international	456
*	 Interexchange carrier-specific services	700
*	 Premium call services	900	
*	 US government	710	
* 	
*	Error 40 - $bad_area_codes are known scam area codes: Bahamas, Barbados, Anguilla, British Virgin Islands, Dominica, Trinidad and Tobago, Etc	
*/
function filterPBXPhoneNumber ($number) {

	$number					= trim($number);
	$reg_exp 				= '/[^\++0-9,.]/'; //match only numeric
	$clean_number 			= preg_replace($reg_exp,'', $number); //delete non-numeric from string
	$number_split			= str_split($clean_number); //$number_split becomes array of single digits of the number
	$starting_digit			= 0;
	$unallowed_numbers		= array(211,411,911);
	$unallowed_area_codes	= array(600,622,633,644,655,677,688,456,700,900,710);
	$bad_area_codes			= array(242,246,264,268,284,345,441,473,649,664,758,767,784,809,829,849,868,869,876); 	
	
	$output					= $clean_number; //set the output to the cleaned phone number. this will be the output IF there is no error found below
	
	//get the correct START of the phone number, disregard leading 1s and +s
	foreach($number_split as $number) {	
	
		if ($number == "+" || $number == "1") {
		$starting_digit++;
		} else {
		break;
		}	
	
	}
	
	$area_code	= substr($clean_number,$starting_digit,3);	//first 3 digits are the area code
	
	if (strlen($clean_number) < 10) {
	$output = 10; //has to be at least 10 digits
	}
		
	if (in_array($area_code,$unallowed_numbers)) {
	$output = 20; //emergency services
	}
	
	if (in_array($area_code,$unallowed_area_codes)) {
	$output = 30; //unallowed area codes
	}
	
	if (in_array($area_code,$bad_area_codes)) {
	$output = 40; //potentially fraudulent area codes
	}	
	
	return $output;

}


/**
* getPhoneValidationError()
*
* outputs the error received
* returns string
*
*/
function getPhoneValidationError($error_no) {
	
	$length	= strlen($error_no);
	
	if ($length > 11) {
	return "Error 50 - Phone Number is too long."; 
	} else {
		
		switch ($error_no) {
		
			case 10: return "Error 10 - Phone Number did not have enough digits. (Less than 10)"; break;
			case 20: return "Error 20 - Phone Number is of an emergency nature."; break;
			case 30: return "Error 30 - Phone Number is of an informative or toll-based nature."; break;
			case 40: return "Error 40 - Phone Number Area Code is known for possibly fraudulent behaviour."; break;
			
			default: return "Unknown Error"; break;
		
		}
	
	}
	
}

/**
* isValidPhoneNumber()
*
* tests $number input for valid 10 or 11 digit formatting
* returns bool (true|false)
*
*/
function isValidPhoneNumber ($number) {
	
	$lengths = array(10, 11);  
	$number = preg_replace('/D+/', '', $number);

	return in_array(strlen($number), $lengths);

}


/**
* formatPBXPhoneNumber()
*
* formats a number string into a proper telephone format
* returns string
*
*/
function formatPBXPhoneNumber($phoneNumber) {
	
    $phoneNumber = preg_replace('/[^0-9]/','',$phoneNumber); //get only numbers

    if(strlen($phoneNumber) > 10) {
		
		//separate the number into pieces to properly format. this is for an international NANPA number - + 1 (999) 444-4444
        $countryCode = substr($phoneNumber, 0, strlen($phoneNumber)-10);
        $areaCode = substr($phoneNumber, -10, 3);
        $nextThree = substr($phoneNumber, -7, 3);
        $lastFour = substr($phoneNumber, -4, 4);

        $phoneNumber = '+'.$countryCode.' ('.$areaCode.') '.$nextThree.'-'.$lastFour;
		
    } elseif (strlen($phoneNumber) == 10) {
		
		//formats into (999) 345-6789
        $areaCode = substr($phoneNumber, 0, 3);
        $nextThree = substr($phoneNumber, 3, 3);
        $lastFour = substr($phoneNumber, 6, 4);

        $phoneNumber = '('.$areaCode.') '.$nextThree.'-'.$lastFour;
		
    } elseif(strlen($phoneNumber) == 7) {
		
		//formats into 345-6789
        $nextThree = substr($phoneNumber, 0, 3);
        $lastFour = substr($phoneNumber, 3, 4);

        $phoneNumber = $nextThree.'-'.$lastFour;
		
    }

    return $phoneNumber;
	
}



>>>>>>> 1a1046692585c81d9f14e6db3df5cfea149fe4ba
