<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */

/**
 * Memcache Cache Handler
 */
class memcacheCacheHandler
{
	/**
	 * The memcache server resource
	 */
	public $memcache;

	/**
	 * Unique identifier representing this copy of MyBB
	 */
	public $unique_id;

	function memcacheCacheHandler($silent=false)
	{
		global $mybb;

		 //HACK: use the newer memcached extension instead of the old memcache one
        // Check if our DB engine is loaded
        if(!extension_loaded("Memcached"))
        {
            // Throw our super awesome cache loading error
            $mybb->trigger_generic_error("memcache_load_error");
            die;
        }
	}

	/**
	 * Connect and initialize this handler.
	 *
	 * @return boolean True if successful, false on failure
	 */
	function connect()
	{
		global $mybb, $error_handler;

		$this->memcache = new Memcache;

		if($mybb->config['memcache']['host'])
		{
			$mybb->config['memcache'][0] = $mybb->config['memcache'];
			unset($mybb->config['memcache']['host']);
			unset($mybb->config['memcache']['port']);
		}

		foreach($mybb->config['memcache'] as $memcache)
		{
			if(!$memcache['host'])
			{
				$message = "Please configure the memcache settings in inc/config.php before attempting to use this cache handler";
				$error_handler->trigger($message, MYBB_CACHEHANDLER_LOAD_ERROR);
				die;
			}

			if(!isset($memcache['port']))
			{
				$memcache['port'] = "11211";
			}

			$this->memcache->addServer($memcache['host'], $memcache['port']);

			if(!$this->memcache)
			{
				$message = "Unable to connect to the memcache server on {$memcache['memcache_host']}:{$memcache['memcache_port']}. Are you sure it is running?";
				$error_handler->trigger($message, MYBB_CACHEHANDLER_LOAD_ERROR);
				die;
			}
		}

		// Set a unique identifier for all queries in case other forums are using the same memcache server
		$this->unique_id = md5(MYBB_ROOT);

		return true;
	}

	/**
	 * Retrieve an item from the cache.
	 *
	 * @param string The name of the cache
	 * @param boolean True if we should do a hard refresh
	 * @return mixed Cache data if successful, false if failure
	 */

	function fetch($name, $hard_refresh=false)
	{
		$data = $this->memcache->get($this->unique_id."_".$name);

		//HACK: memcached might return false if that's what the value was set to,
        //so check getResultCode instead
        if($this->memcache->getResultCode() != Memcached::RES_SUCCESS)
        {
            error_log("Fetching $name from memcached: got result code " . $this->memcache->getResultCode(), 3, "error_log");
            return false;
        }
        else
        {
            return $data;
        }
	}

	/**
	 * Write an item to the cache.
	 *
	 * @param string The name of the cache
	 * @param mixed The data to write to the cache item
	 * @return boolean True on success, false on failure
	 */
	function put($name, $contents)
	{
		return $this->memcache->set($this->unique_id."_".$name, $contents);
	}

	/**
	 * Delete a cache
	 *
	 * @param string The name of the cache
	 * @return boolean True on success, false on failure
	 */
	function delete($name)
	{
		return $this->memcache->delete($this->unique_id."_".$name);
	}

	/**
	 * Disconnect from the cache
	 */
	function disconnect()
	{
		@$this->memcache->close();
	}

	function size_of($name)
	{
		global $lang;

		return $lang->na;
	}
}

?>