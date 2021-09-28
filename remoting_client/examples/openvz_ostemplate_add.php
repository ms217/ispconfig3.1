<?php

require 'soap_config.php';


$client = new SoapClient(null, array('location' => $soap_location,
		'uri'      => $soap_uri,
		'trace' => 1,
		'exceptions' => 1));


try {
	if($session_id = $client->login($username, $password)) {
		echo 'Logged successfull. Session ID:'.$session_id.'<br />';
	}

	//* Set the function parameters.
	$client_id = 1;
	$params = array(
		'template_name' => 'tone',
		'template_file' => 'custom',
		'server_id' => 1,
		'allservers' => 'y',
		'active' => 'y',
		'description' => 'howto',
	);

	$ostemplate_id = $client->openvz_ostemplate_add($session_id, $client_id, $params);

	echo "Ostemplate ID: ".$ostemplate_id."<br>";

	if($client->logout($session_id)) {
		echo 'Logged out.<br />';
	}


} catch (SoapFault $e) {
	echo $client->__getLastResponse();
	die('SOAP Error: '.$e->getMessage());
}

?>
