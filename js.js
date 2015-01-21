/**
* Javascript for 3CX HTTP API Call Maker
*
* This file contains 2 functions and the javascript needed to search a page for correctly formatted phone numbers [(333) 333-3333]
* then add a phone icon and link that allows users to click to call using a 3CX v12 PBX
*
* @author     Taylor Nolen <taylor@abptech.com>
* @author     ABP Technologies <itadmin@abptech.com>
* @copyright  2015 ABP Technologies
* @license    The MIT License (MIT)
* @link       http://abptech.com
*/

/**
* 
* On document ready, we are going to traverse the DOM for all table cell (<td>) contents.
* Then test the contents against a regular expression that requires this format: (999) 999-9999
* 
* If found, it will call the linkify() function
*
* The $.cookie() function is usable by including the jQuery cookie plugin (https://github.com/carhartl/jquery-cookie)
* 
*/
$(document).ready(function(){
	
	var user_name	= $.cookie('CookieWithUserID');	
	var	regex 		= /\(?([0-9]{3})\)?[- ]([0-9]{3})[- ]([0-9]{4})/;
	
	$("td").each(function() {
	
		var cell_contents = $(this).text();
		
		if(regex.test(cell_contents)) {
		$(this).html(linkify(cell_contents,user_name));
		}
		
	});
	
});


/**
* 
* linkify()
* 
* For our specific purposes, we require the following inputs:
*
* input_text	A string holding the table cell's contents (basically, a phone number)
* user_name		The user's name that is currently logged in. Within our app, this was set on login and added to $.cookie('CookieWithUserID')
* 
*/
function linkify(input_text,user_name) {

    var replaced_text, replace_pattern;	

    replace_pattern = /\(?([0-9]{3})\)?[- ]([0-9]{3})[- ]([0-9]{4})/; // regex for (999) 999-9999
    
	/**
	*
	* replaced_text uses the replace() function to add the phone number to the output '($1) $2-$3', 
	* create an <A> link tag. add the user name to the URL as 'user=user_name' as well as the number: 'num=$1$2$3'
	* then a little phone icon is added as the linked object on the page. 
	*
	*/
	replaced_text = input_text.replace(replace_pattern, '($1) $2-$3 <a href="http://yourdomain.com/make_call.php?user='+user_name+'&num=$1$2$3" onclick="return makeCall(\'http://yourdomain.com/make_call.php?user='+user_name+'&num=$1$2$3\')"><img src="img/phone_icon_grey_15_15.png" border="0" /></a>');
	
	return replaced_text;
	
}

/**
* 
* makeCall()
* 
* Pops up a new window that shows the user what is happening when they click on the phone icon. Ours just has a loading icon and says "Placing call to (999) 999-9999"
* 
*/
function makeCall(url) {

	var random_number	= Math.random(); //adding random_number to the window name as not to interfere with other phonecall popups that are already there

	newwindow=window.open(url,'makeNewCall_'+random_number,'height=250,width=450,top=300,left=250');
	if (window.focus) {newwindow.focus()}
	return false;
	
}

