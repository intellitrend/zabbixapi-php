<?php
require_once("../src/Zabbixapi.php");

print "Zabbix API Example\n";
print " Connect to API, ignore certificate/hostname and get number of hosts\n";
print "=====================================================\n";

$zabUrl ='https://my.zabbixurl.com/zabbix';
$zabUser = 'myusername';
$zabPassword = 'mypassword';

$zbx = new ZabbixApi();
try {
	// disable verification of certificate and hostname
	$options = array('sslVerifyPeer' => false, 'sslVerifyHost' => false);
	$zbx->login($zabUrl, $zabUser, $zabPassword, $options);
	//this is similar to: $result = $zbx->call('apiinfo.version');
	$result = $zbx->getApiVersion();
	print "Remote Zabbix API Version:$result\n";
	// Get number of host available to this useraccount
	$result = $zbx->call('host.get',array("countOutput" => true));
	print "Number of Hosts:$result\n";
} catch (Exception $e) {
	print "==== Exception ===\n";
	print 'Errorcode: '.$e->getCode()."\n";
	print 'ErrorMessage: '.$e->getMessage()."\n";
	exit;
}