<?php
require_once("../src/ZabbixApi.php");

use IntelliTrend\Zabbix\ZabbixApi;
use IntelliTrend\Zabbix\ZabbixApiException;

print "Zabbix API Example - Create a trigger\n";
print " Connect to API, create a trigger'\n";
print "=====================================================\n";

$zabUrl ='https://my.zabbixurl.com/zabbix';
$zabUser = 'myusername';
$zabPassword = 'mypassword';

$zbx = new ZabbixApi();
try {
	// default is to verify certificate and hostname
	$zbx->login($zabUrl, $zabUser, $zabPassword);

	// this is similar to: $result = $zbx->call('apiinfo.version');
	$result = $zbx->getApiVersion();
	print "Remote Zabbix API Version:$result\n";

	print "====================================================================\n";

	// create a trigger on host 'Zabbix Server' that fires if the item 'system.cpu.util' is greater than 75
	$result = $zbx->call('trigger.create', array(
		"priority" => "3", // average
		"description" => "High CPU usage on {HOST.NAME}",
		"expression" => "last(/Zabbix server/system.cpu.util)>75", // Zabbix 5.4 and after
		// "expression" => "{Zabbix server:system.cpu.util.last()}>75", // before Zabbix 5.4
	));

	// print ID of the trigger that was created
	print_r($result);
} catch (ZabbixApiException $e) {
	print "==== Zabbix API Exception ===\n";
	print 'Errorcode: '.$e->getCode()."\n";
	print 'ErrorMessage: '.$e->getMessage()."\n";
	exit;
} catch (Exception $e) {
	print "==== Exception ===\n";
	print 'Errorcode: '.$e->getCode()."\n";
	print 'ErrorMessage: '.$e->getMessage()."\n";
	exit;
}
?>
