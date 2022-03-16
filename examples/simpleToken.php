<?php
require_once("../src/ZabbixApi.php");

use IntelliTrend\Zabbix\ZabbixApi;

print "Zabbix API Example\n";
print " Connect to API using a token, check certificate/hostname and get number of hosts\n";
print "=====================================================\n";

$zabUrl ='https://my.zabbixurl.com/zabbix';
$zabToken = '123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234';

$zbx = new ZabbixApi();
try {
	// default is to verify certificate and hostname
	$zbx->loginToken($zabUrl, $zabToken);
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