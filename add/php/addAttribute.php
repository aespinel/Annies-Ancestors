<?php
error_reporting(E_ALL);
ini_set('display_errors', true);
date_default_timezone_set('EST5EDT');					// need to set time zone of server for date --> epoch time

$error   = 'pending';
$response = array();
$returnStr = array();

include dirname(__FILE__) . "/../../php/database.php";

$link = mysqli_connect($host,$username,$password,$db_name);

if (!$link) {
	$error = "an internal error was encountered";
	returnWithError($error, $link);
}

// text input to run script as stand-alone for debugging

$data = "gender=M&surname=johnsooon&namePrefix=&givenName=&middleName=&nameSuffix=&ownerID=6120&mainID=6121&theType=DESC&dateModifier=EXA&date1=11/07/1952&date2=&phrase=&city=Minneapolis&state=Minnesota&county=Hennipen&country=USA&details=Hackley Hospital";

//  get input to run script as forms handler, comment out to run in terminal

// $data = $_SERVER['QUERY_STRING'];

// text input to run script as stand-alone for debugging

parse_str($data, $output);

if(isset($output['ownerID'])) {$ownerID = $output['ownerID'];} else {$ownerID = '';};
if(isset($output['mainID'])) {$mainID = $output['mainID'];} else {$mainID = '';};
if(isset($output['gender'])) {$gender = $output['gender'];} else {$gender = '';};
if(isset($output['namePrefix'])) {$namePrefix = $output['namePrefix'];} else {$namePrefix = '';};
if(isset($output['givenName'])) {$givenName = $output['givenName'];} else {$givenName = '';};
if(isset($output['middleName'])) {$middleName = $output['middleName'];} else {$middleName = '';};
if(isset($output['surname'])) {$surname = $output['surname'];} else {$surname = '';};
if(isset($output['nameSuffix'])) {$nameSuffix = $output['nameSuffix'];} else {$nameSuffix = '';};
if(isset($output['theType'])) {$theType = $output['theType'];} else {$theType = '';};
if(isset($output['dateModifier'])) {$dateModifier = $output['dateModifier'];} else {$dateModifier = '';};
if(isset($output['date1'])) {$date1 = $output['date1'];} else {$date1 = '';};
if(isset($output['date2'])) {$date2 = $output['date2'];} else {$date2 = '';};
if(isset($output['phrase'])) {$phrase = $output['phrase'];} else {$phrase = '';};
if(isset($output['city'])) {$city = $output['city'];} else {$city = '';};
if(isset($output['state'])) {$state = $output['state'];} else {$state = '';};
if(isset($output['county'])) {$county = $output['county'];} else {$county = '';};
if(isset($output['country'])) {$country = $output['country'];} else {$country = '';};
if(isset($output['details'])) {$details = $output['details'];} else {$details = '';};

// test for required inputs

if ($date1=="") {
    	$error = "The event date is required";
    	returnWithError($error, $link);
}

// remove any possible SQL injections in the form data

$ownerID = test_input($link, $ownerID);
$mainID = test_input($link, $mainID);
$gender = test_input($link, $gender);
$namePrefix = test_input($link, $namePrefix);
$givenName = test_input($link, $givenName);
$middleName = test_input($link, $middleName);
$surname = test_input($link, $surname);
$nameSuffix = test_input($link, $nameSuffix);
$theType = test_input($link, $theType);
$dateModifier = test_input($link, $dateModifier);
$date1 = test_input($link, $date1);
$date2 = test_input($link, $date2);
$phrase = test_input($link, $phrase);
$city = test_input($link, $city);
$state = test_input($link, $state);
$county = test_input($link, $county);
$country = test_input($link, $country);
$details = test_input($link, $details);

if (!preg_match("/^[a-zA-Z ]*$/",$city)) {
	$error = "Only letters and white spaces are allowed in the city name";
	returnWithError($error, $link);
}

if (!preg_match("/^[a-zA-Z ]*$/",$state)) {
	$error = "Only letters and white spaces are allowed in the state name";
	returnWithError($error, $link);
}

if (!preg_match("/^[a-zA-Z ]*$/",$county)) {
  $error = "Only letters and white spaces are allowed in the county name"; 
  returnWithError($error, $link);
}

if (!preg_match("/^[a-zA-Z ]*$/",$country)) {
	$error = "Only letters and white spaces are allowed in the country name";
	returnWithError($error, $link);
}
if (!preg_match("/^[a-zA-Z ]*$/",$surname)) {
	$error = "Only letters and white spaces are allowed in the surname";
	returnWithError($error, $link);
}

if (!preg_match("/^[a-zA-Z ]*$/",$namePrefix)) {
	$error = "Only letters and white spaces are allowed in the name prefix";
	returnWithError($error, $link);
}

if (!preg_match("/^[a-zA-Z ]*$/",$givenName)) {
	$error = "Only letters and white spaces are allowed in the given name";
	returnWithError($error, $link);
}

if (!preg_match("/^[a-zA-Z ]*$/",$middleName)) {
	$error = "Only letters and white spaces are allowed in the middle name";
	returnWithError($error, $link);
}

if (!preg_match("/^[a-zA-Z ]*$/",$nameSuffix)) {
	$error = "Only letters and white spaces are allowed in the name suffix";
	returnWithError($error, $link);
}

$date1 = date('Y-m-d ', strtotime($date1));
$date2= date('Y-m-d ', strtotime($date2));

$createDate = date('Y-m-d h:i:s');

// Attempt insert query execution

$which = "ownerID,mainID,theType,dateModifier,date1,date2,phrase,city,state,county,country,details,CREATEDATE";
$value = "'$ownerID','$mainID','$theType','$dateModifier','$date1','$date2','$phrase','$city','$county','$state','$country','$details','$createDate'";

mysqli_query($link, "INSERT INTO attributes ($which) VALUES ($value)");

if ($user_id = mysqli_insert_id($link)) {							
 } else {										// INSERT failure
    $error = "Error: " . mysqli_error($link);
 	returnWithError($error, $link);
}
if($surname!='') {
	$which = "ownerID,gender,namePrefix,givenName,middleName,surname,nameSuffix,refNumber,CREATEDATE";
	$value = "'$ownerID','$gender','$namePrefix','$givenName','$middleName','$surname','$nameSuffix','$user_id','$createDate'";

	$sql = mysqli_query($link, "INSERT INTO names ($which) VALUES ($value)");

	if ($user_id = mysqli_insert_id($link)) {
	} else {										// INSERT failure
		$error = "Error: " . mysqli_error($link);
		returnWithError($error, $link);
	}
}

// return a response

	http_response_code(200);					//See https://docs.angularjs.org/api/ng/service/$http#json-vulnerability-protection
	echo ")]}',\n" . json_encode($response);	// )]}', is used to stop JSON attacks, angular strips it off for you.
	mysqli_close($link);
	return;

	function returnWithError($response, $link) {
		http_response_code(303);
		echo ")]}',\n" . json_encode($response);
		mysqli_close($link);
		exit;
	}
	
function test_input($link, $data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  $data = mysqli_real_escape_string($link, $data);
  return $data;
}
?>