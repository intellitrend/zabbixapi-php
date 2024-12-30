# Changelog

## Version 3.3.0

* Added support for Zabbix 7.2.
* Fixed `connectTimeout` setting the regular timeout instead of the connection timeout.

## Version 3.2.0

* Added custom exception class ZabbixApiException.
* Fixed exception handling issues in function `call()` if other exceptions are thrown by custom handlers.

## Version 3.1.0

* Tested with Zabbix 6.0.
* Added missing namespace in examples.
* Added function `loginToken()` to log in via API tokens.

## Version 3.0.2

* Fixed bug in getApiVersion() with invalid credentials.
* Added example and documentation using composer. See `examples/composer`.

## Version 3.0.1

* Tested with Zabbix 5.0 - 5.4.
* Added support for Zabbix 5.4 API change in `user.login` method.
* Added namespace `IntelliTrend\Zabbix`.
* Added [composer](https://getcomposer.org/) support.
* Moved to [semver](https://semver.org/) version numbers.

## No version change

* Added more examples to filter by hostnames, hostids, hostgroupnames and hostgroupids.

## Version 2.8

* Tested with Zabbix 5.0 and 5.2.
* Ensure that params passed to API call are an array.
* Added library version to debug output.

## Version 2.7

* BREAKING CHANGE: Classfilename renamed from `Zabbixapi.php` to `ZabbixApi.php` to match classname.
* Call to `getAuthKey()` no longer simply returns the `authKey`. If there was no previous call to the Zabbix-API this funcion will call the Zabbix-API to ensure a valid key before returning the key.
* Fixed error message for invalid sslCaFile.

## Version 2.6

* Public release.
* Added check for Curl.
* Added example for filtering and additional params passed to the Zabbix-API.
* Call to `login()` no longer initially calls the Zabbix-API anylonger to verify the `authKey`.
* If debug is enabled via option, or via function before calling `login()`, `login()` issues a call to the Zabbix-API to check wether the session is re-used.

## Version 2.5

* Internal release.

## Version 2.4

* Initial public release.