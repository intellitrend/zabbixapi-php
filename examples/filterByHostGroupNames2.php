<?php
require_once("../src/ZabbixApi.php");

use IntelliTrend\Zabbix\ZabbixApi;

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
	// 2. 2 calls: Get hostGroupIds based on theirs names, then get hosts with detailed info

	// here we use method 2 - 2 calls
	// note: if a host belongs to other groups as filtered in the 1st call
	// these goups are also listed in the 2nd call.
	// get hostGroupIds by name and then execute a 2nd call to get the hosts with additional data
	$hostGroupNames = ['Zabbix servers', 'IntelliTrend/Development'];

	$params = array(
		'output' => array('groupid', 'name'),
		'filter' => array('name' => $hostGroupNames),
	);

	$result = $zbx->call('hostgroup.get',$params);
	print "==== Hosts filtered by hostGroups - 2 calls ====\n";
	print "==== 1. Call: Filtered hostGroups ====\n";
	// save the ids for next call
	$hostGroupIds = [];
	foreach($result as $hostGroup) {
		printf("GroupId:%d - Group:%s\n", $hostGroup['groupid'], $hostGroup['name']);
		$hostGroupIds[] =  $hostGroup['groupid'];
	}

	// get hosts based on the hostGroupIds
	$params = array(
		'output' => array('hostid', 'host', 'name', 'status', 'maintenance_status', 'description'),
		'groupids' => $hostGroupIds,
		'selectGroups' => array('groupid', 'name'),
		'selectInterfaces' => array('interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port'),
		'selectInventory' => array('os', 'contact', 'location'),
		'selectMacros' => array('macro', 'value')
	);

	$result = $zbx->call('host.get',$params);
	print "==== 2. Call: Filtered hostlist with groups and macros ====\n";
	foreach($result as $host) {
		printf("HostId:%d - Host:%s - Name:%s\n", $host['hostid'], $host['host'], $host['name']);
		foreach($host['groups'] as $group) {
			printf("    - GroupId:%d - Group:%s\n", $group['groupid'], $group['name']);
		}
		foreach($host['macros'] as $macro) {
			if (!isset($macro['value'])) {
				$macro['value'] = '';
			}
			printf("    - Macro:%s - Value:%s\n", $macro['macro'], $macro['value']);
		}

	}

} catch (Exception $e) {
	print "==== Exception ===\n";
	print 'Errorcode: '.$e->getCode()."\n";
	print 'ErrorMessage: '.$e->getMessage()."\n";
	exit;
}