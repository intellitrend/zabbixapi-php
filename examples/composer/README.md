# Example using composer

The package `intellitrend/zabbixapi` requires composer 2+.

The following examples assumes `composer.phar` to be in the root of the project directory. Your call to composer might be different.

You can download the latest version of composer [here](https://getcomposer.org/download/).

## Pepare the project that should use the package

Create a `composer.json` for the project:

```json
{
    "name": "zabbix-api/demo",
    "description": "",
    "require": {
        "intellitrend/zabbixapi": "v3.3.0"
    },
    "autoload": {
        "psr-4": {
            "IntelliTrend\\Zabbix\\": "/src"
        }
    }
}
```

## Run composer

Run `php .\composer.phar create-project` (requires composer.phar)

After a successful run, the directory structure looks like this. Composer added the vendor directory and also its own created autoload files:

```text
├───projectfolder
│   └───vendor
│       ├───composer
│       └───intellitrend
│           └───zabbixapi
│               ├───docs
│               ├───examples
│               └───src
```

Note: You can remove the `vendor` directory any time. However then you must run `php .\composer.phar create-project` again. For more composer options see the [documentation](https://getcomposer.org/doc/).

## Add the following lines to your application

```php
<?php
/*
 * Example using composer
 */

require('vendor/autoload.php');

use IntelliTrend\Zabbix\ZabbixApi;
```
