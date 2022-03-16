<?php
/*
 * ZabbixApi example using composer
 */

require('vendor/autoload.php');

use IntelliTrend\Zabbix\ZabbixApi;

print "Zabbix API Example using composer\n";
print " Connect to API, check certificate/hostname and get number of hosts\n";
print "=====================================================\n";

$zabUrl ='https://my.zabbixurl.com/zabbix';
$zabUser = 'myusername';
$zabPassword = 'mypassword';
// $zabToken = '123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234';

$zbx = new ZabbixApi();
try {
	// $zbx->setDebug(true);
	// default is to verify certificate and hostname
	$zbx->login($zabUrl, $zabUser, $zabPassword);
	// loginToken() accepts API tokens as an alternative to login()
	// $zbx->loginToken($zabUrl, $zabToken);
	// this is similar to: $result = $zbx->call('apiinfo.version');
	$result = $zbx->getApiVersion();
	print "Remote Zabbix API Version:$result\n";
	// get number of host available to this useraccount
	$result = $zbx->call('host.get',array("countOutput" => true));
	print "Number of Hosts:$result\n";
	// calling logout will logout from zabbix (deleteing the session) and also delete the local session cache
	// if your application is called periodically, the do not logout to benefit from session cache
	// $zbx->logout();
} catch (Exception $e) {
	print "==== Exception ===\n";
	print 'Errorcode: '.$e->getCode()."\n";
	print 'ErrorMessage: '.$e->getMessage()."\n";
	exit;
}