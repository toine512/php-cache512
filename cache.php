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
				//TTL is not reached
				if(filemtime($fname) > time())
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
				//Unserialize data
				$this->data = unserialize(file_get_contents($this->_file_mkname($key)));
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
			$fname = $this->_file_mkname($key);

			//Write data
			file_put_contents($fname, serialize($this->data));
			//Set the expiration timestamp as a future ctime
			touch($fname, time() + (int) $ttl);
			return true;

		/*** APC ***/
		} elseif($this->_backend == 1) {
			return apc_store($key, $this->data, $ttl);
		}
	}
}