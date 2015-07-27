![TYPO3](http://typo3.org/typo3conf/ext/t3org_template/i/typo3-logo.png)

TYPO3 Database Cleaner
===========

a simple PHP based Cleaner for a [TYPO3](http://typo3.org) Database.

Screenshots
-----------

are coming soon!

Instructions
-----------

Download the file `t3dbcleaner.php` and open it in your favourite editor. Change the CONSTANTS for `HOST`, `USERNAME`, `PASSWORD` and `DATABASE` and transfer the file to your remote server. The Cleaner can be opened in your web browser. Open server url `http://server-address/t3dbcleaner.php` in your web browser and follow instructions.

```php
define('HOST', 'localhost');
define('USERNAME', 'christoph');
define('PASSWORD', 'joh316');
define('DATABASE', 'db4711');
```

Don't forget to **delete** the files from your remote server afterwards!

Dependencies
-----------

* [PHP 5.1+](http://php.net/)
* [MySQL 4.1+](https://www.mysql.com/)

How to contribute
------------

Please report issues at Github:
<a href="https://github.com/halbkreativ/t3dbcleaner/issues" target="_blank">https://github.com/halbkreativ/t3dbcleaner/issues</a>