<?php
require_once("../src/ZabbixApi.php");

use IntelliTrend\Zabbix\ZabbixApi;

print "Zabbix API Example\n";
print " Connect to API and get some hostdata as list\n";
print " Ignore certificate/hostname\n";
print " Enable / Disable debug output while running\n";
print "=====================================================\n";

$zabUrl ='https://my.zabbixurl.com/zabbix';
$zabUser = 'myusername';
$zabPassword = 'mypassword';

$zbx = new ZabbixApi();
print "ZabbixApi library version:". $zbx->getVersion(). "\n";
try {
	// disable validation off certificate and host 
	$options = array('sslVerifyPeer' => false, 'sslVerifyHost' => false);
	$zbx->login($zabUrl, $zabUser, $zabPassword, $options);

	//this is similar to: $result = $zbx->call('apiinfo.version');
	$result = $zbx->getApiVersion();
	print "Remote Zabbix API Version:$result\n";
	
	print "Debugging set to on\n";
	$zbx->setDebug(true);
	
	// Get host count
	$params = array(		
		// count of hosts - no ids, no details
		"countOutput" => true  
	);
	$result = $zbx->call('host.get',$params);
	print "Number of Hosts:$result\n";
	
	print "Debugging set to off\n";
	$zbx->setDebug(false);

	$limit = 5;
	$params = array(
		 // limit host info to these fields, to get all use "extend" instead of field list
		"output" => array("hostid", "host", "name", "status"),
		// limit number of hosts returned, otherwise get all hosts you have access to
		"limit" => $limit  
	);
	$result = $zbx->call('host.get',$params);


	print "Getting hostlist - limited to:$limit\n";
	foreach ($result as $v) {
		$hostid = $v['hostid'];
		$hostname = $v['host'];
		$name = $v['name'];
		$status = $v['status'];
		print "hostid:$hostid, status:$status, hostname:$hostname, alias:$name\n";
	}

	// logout() would logout from api and also delete the session file locally
	//$zbx->logout();
} catch (Exception $e) {
	print "==== Exception ===\n";
	print 'Errorcode: '.$e->getCode()."\n";
	print 'ErrorMessage: '.$e->getMessage()."\n";
	exit;
}