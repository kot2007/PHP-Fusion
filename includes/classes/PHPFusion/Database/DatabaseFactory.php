<?php
/*-------------------------------------------------------
| PHP-Fusion Content Management System
| Copyright (C) PHP-Fusion Inc
| https://www.php-fusion.co.uk/
 --------------------------------------------------------
| Filename: DatabaseFactory.php
| Author: Takács Ákos (Rimelek)
 --------------------------------------------------------
| This program is released as free software under the
| Affero GPL license. You can redistribute it and/or
| modify it under the terms of this license which you
| can read by viewing the included agpl.txt or online
| at www.gnu.org/licenses/agpl.html. Removal of this
| copyright header is strictly prohibited without
| written permission from the original author(s).
 --------------------------------------------------------*/

namespace PHPFusion\Database;

class DatabaseFactory {

	/**
	 * use mysql_* functions
	 */
	const DRIVER_MYSQL = 'mysql';

	/**
	 * use \PDO class
	 */
	const DRIVER_PDO_MYSQL = 'pdo_mysql';

	/**
	 * Use the default driver (pdo_mysql)
	 */
	const DRIVER_DEFAULT = self::DRIVER_PDO_MYSQL;

	/**
	 * MySQL or PDOMySQL
	 *
	 * @var string
	 */
	private static $defaultDriver = self::DRIVER_DEFAULT;

	/**
	 * @var bool|array Array of connection IDs or TRUE to debug all connections
	 */
	private static $debug = array();

	/**
	 * @var string[]
	 */
	private static $driverClasses = array(
		self::DRIVER_MYSQL => '\PHPFusion\Database\Driver\MySQL',
		self::DRIVER_PDO_MYSQL => '\PHPFusion\Database\Driver\PDOMySQL'
	);

	/**
	 * @var Configuration[]
	 */
	private static $configurations = array();

	/**
	 * @var string
	 */
	private static $defaultConnectionID = 'default';

	/**
	 * @var AbstractDatabaseDriver[]
	 */
	private static $connections = array();

	/**
	 * @return string
	 */
	public static function getDefaultConnectionID() {
		return self::$defaultConnectionID;
	}

	/**
	 * @param string $id
	 * @param string $fullClassName
	 */
	public static function registerDriverClass($id, $fullClassName) {
		if (is_subclass_of($fullClassName, __NAMESPACE__.'\AbstractDatabaseDriver')
			and !isset(self::$driverClasses[$id])) {
			self::$driverClasses[$id] = $fullClassName;
		}
	}

	/**
	 * @param int $id
	 * @param array $configuration
	 */
	public static function registerConfiguration($id, array $configuration) {
		$lowerCaseID = strtolower($id);
		if (!isset(self::$configurations[$lowerCaseID])) {
			self::$configurations[$lowerCaseID] = new Configuration($configuration);
		}
	}

	/**
	 * @param array $configurations
	 */
	public static function registerConfigurations($configurations) {
		foreach ($configurations as $id => $configuration) {
			self::registerConfiguration($id, $configuration);
		}
	}

	/**
	 * @param string $file
	 */
	public static function registerConfigurationFromFile($file) {
		if (is_file($file)) {
			$configurations = require $file;
			if (is_array($configurations)) {
				DatabaseFactory::registerConfigurations($configurations);
			}
			// TODO Exception otherwise
		}
		// TODO Exception otherwise
	}

	/**
	 * @param string $id
	 * @return null|string
	 */
	public static function getDriverClass($id = NULL) {
		if ($id === NULL) {
			$id = self::getDefaultDriver();
		}
		return isset(self::$driverClasses[$id]) ? self::$driverClasses[$id] : NULL;
	}

	/**
	 * @param string $defaultDriver
	 */
	public static function setDefaultDriver($defaultDriver) {
		self::$defaultDriver = $defaultDriver;
	}

	/**
	 * @return string
	 */
	public static function getDefaultDriver() {
		return self::$defaultDriver;
	}

	/**
	 * @param bool|array $debug
	 */
	public static function setDebug($debug = TRUE) {
		self::$debug = $debug;
	}

	/**
	 * @return bool
	 */
	public static function isDebug($connectionid = NULL) {
		return ((!$connectionid and self::$debug)
				or ($connectionid and is_array(self::$debug)
				and in_array($connectionid, self::$debug)));
	}

	/**
	 * Connect to the database using the default driver
	 *
	 * @param string $host
	 * @param string $user
	 * @param string $password
	 * @param string $db
	 * @param array $options
	 * @return AbstractDatabaseDriver
	 * @throws Exception\SelectionException
	 * @throws Exception\ConnectionException
	 */
	public static function connect($host, $user, $password, $db, array $options = array()) {
		$configuration = new Configuration();
		$options += array(
			'charset' => $configuration->getCharset(),
			'driver' => $configuration->getDriver(),
			'connectionid' => self::getDefaultConnectionID(),
			'debug' => $configuration->isDebug()
		);
		$id = strtolower($options['connectionid']);
		if (!isset(self::$connections[$id])) {
			$class = self::getDriverClass(strtolower($options['driver']));
			/**@var AbstractDatabaseDriver*/
			$connection = new $class($host, $user, $password, $db, $options);
			if ($options['debug'] and !self::isDebug($id)) {
				self::$debug[] = $id;
			}
			$connection->setDebug(self::isDebug($id));
			self::$connections[$id] = $connection;
		}
		return self::$connections[$id];
	}

	/**
	 * Get the database connection object
	 *
	 * @param string $id
	 * @return AbstractDatabaseDriver
	 */
	public static function getConnection($id = NULL) {
		$id = strtolower($id ? : self::getDefaultConnectionID());
		if (!isset(self::$configurations[$id])) {
			// TODO Exception
			return NULL;
		}
		if (!isset(self::$connections[$id])) {
			$conf = self::$configurations[$id];
			self::connect($conf->getHost(), $conf->getUser(), $conf->getPassword(), $conf->getDatabase(), array(
				'driver' => $conf->getDriver(),
				'connectionid' => $id,
				'debug' => $conf->isDebug()
			));
		}
		return self::$connections[$id];
	}

}