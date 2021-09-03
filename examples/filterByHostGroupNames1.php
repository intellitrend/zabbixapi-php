<?php
require_once("../src/ZabbixApi.php");

print "Zabbix API Example - Filter by HostGroupNames\n";
print " Connect to API, check certificate/hostname and get number of hosts\n";
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
	// get hostGroups and attach hosts available to this useraccount, filtered by hostGroupNames
	// note: [] is a more modern way of array()
	// depending how much details about the host is needed, 2 options are available:
	// 1. 1 call: Get hostGroups and add corresponding hosts to the result
	// 2. 2 Calls: Get hostGroupIds based on theirs names, then get hosts with detailed info

	// here we use method 1 - 1 call
	// get hostGroupIds by name and add hosts to result
	$hostGroupNames = ['Zabbix servers', 'IntelliTrend/Development'];

	$params = array(
		'output' => array('groupid', 'name'),
		'filter' => array('name' => $hostGroupNames),
		'selectHosts' => array('hostid', 'host', 'name', 'status', 'maintenance_status', 'description')
	);

	$result = $zbx->call('hostgroup.get',$params);
	print "==== Hosts filtered by hostGroups - one call ====\n";
	foreach($result as $hostGroup) {
		printf("GroupId:%d - Group:%s\n", $hostGroup['groupid'], $hostGroup['name']);
		foreach($hostGroup['hosts'] as $host) {
			printf("    - HostId:%d - Host:%s - Name:%s\n", $host['hostid'], $host['host'], $host['name']);
		}
	}

} catch (Exception $e) {
	print "==== Exception ===\n";
	print 'Errorcode: '.$e->getCode()."\n";
	print 'ErrorMessage: '.$e->getMessage()."\n";
	exit;
}