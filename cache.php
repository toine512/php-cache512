<?php
class Cache512
{
	protected $file_ext = '512cache';

	public $data;
	protected $_error;

	protected $_backend;
	private $_supported_backend = array('file' => 0, 'apc' => 1);

	function __construct($backend)
	{
		$this->data = null;
		$this->_error = false;

		$backend = strtolower(trim($backend));
		$this->_backend = (array_key_exists($backend, $this->_supported_backend) ? $this->_supported_backend[$backend] : 0);
	}

	private function _file_mkname($key)
	{
		return str_replace(array('\\', '/', ':', '*', '?', '"', '<', '>', '|'), '_', $key) . '.' . $this->file_ext;
	}

	/*
	public function is_error()
	{
		if($this->$_error === false)
		{
			return false;
		}
		return true;
	}
	*/
	public function last_error()
	{
		if($this->_error !== false)
		{
			return $this->_error;
		}
		return '';
	}

	public function exists($key)
	{
		$this->_error = false;

		/*** File ***/
		if($this->_backend == 0) {
			$fname = $this->_file_mkname($key);

			clearstatcache();
			if(is_file($fname) && is_readable($fname))
			{
				//Get expiration timestamp inside the file (1st line)
				$f = fopen($fname, 'rb');
				$ttl_timestamp = (int) fgets($f);
				fclose($f);

				//TTL is not reached
				if($ttl_timestamp > time())
				{
					return true;
				}
				
				//TTL reached, cleaning the file
				else
				{
					unlink($fname);
				}
			}

			return false;

		/*** APC ***/
		} elseif($this->_backend == 1) {
			return apc_exists($key);
		}
	}

	public function fetch($key)
	{
		$this->_error = false;

		if($this->exists($key))
		{
			/*** File ***/
			if($this->_backend == 0) {
				$f = fopen($this->_file_mkname($key), 'rb');
				//Jump over the timestamp line
				fgets($f);
				//Then read real data
				$buffer = null;
				while(!feof($f)) {
					$buffer .= fread($f, 8192);
				}
				fclose($f);
				
				//Unserialize data
				$this->data = unserialize($buffer);
				return true;

			/*** APC ***/
			} elseif($this->_backend == 1) {
				$this->data = apc_fetch($key);
				return true;
			}
		}
		return false;
	}

	public function store($key, $ttl)
	{
		$this->_error = false;

		/*** File ***/
		if($this->_backend == 0) {
			$f = fopen($this->_file_mkname($key), 'wb');
			//Write the expiration timestamp as the 1st line
			fwrite($f, ((string) (time() + (int) $ttl)) . "\n");
			//Then write real data
			fwrite($f, serialize($this->data));
			fclose($f);
			
			return true;

		/*** APC ***/
		} elseif($this->_backend == 1) {
			return apc_store($key, $this->data, $ttl);
		}
	}
}