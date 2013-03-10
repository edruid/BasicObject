<?php
class MC extends Memcache {
	private static $instance;
	private $writes=0;
	private $reads=0;
	private static $tried_to_connect=false;

	private function __construct() {
		// Memcache does not have a constructor (?)
		global $memcache_settings;
		if(!self::$tried_to_connect) {
			self::$tried_to_connect = true;
			if(@$this->connect($memcache_settings['host'], $memcache_settings['port']) === false) {
				trigger_error("Unable to connect to memcache at ".$memcache_settings['host']." on port ".$memcache_settings['port'], E_USER_WARNING);
				throw new Exception("Failed to connect");
			}
		} else {
			throw new Exception("Failed to connect");
		}
	}

	public static function get_instance() {
		if(empty(self::$instance)) {
			self::$instance = new MC();
		}
		return self::$instance;
	}
}
