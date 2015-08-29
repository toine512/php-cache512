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

	public function last_error()
	{
		if($this->_error !== false)
		{
			return $this->_error;
		}
		return '';
	}

	public function exists($key, $issue_errors = false)
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
					if($issue_errors) {
						$this->_error = 'Cache expired. (' . $key . ') Deleting ' . $fname . '.';
					}
					unlink($fname);
				}
			}
			elseif($issue_errors) {
				$this->_error = 'File doesn\'t exist. (' . $fname .')';
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
				//Read the cache file
				$buffer = file_get_contents($this->_file_mkname($key));
				if($buffer !== false) {
					//Decode data
					$buffer = json_decode($buffer, true);
					if($buffer !== null) {
						//Only write valid data
						$this->data = $buffer;
						return true;
					}
					else {
						$this->_error = 'json_decode() has failed! (' . json_last_error_msg() . ') Corrupted file? (' . $this->_file_mkname($key) . ')';
					}
				}
				else {
					$this->_error = 'Cannot read file! (' . $this->_file_mkname($key) . ')';
				}

			/*** APC ***/
			} elseif($this->_backend == 1) {
				$success = false;
				$buffer = apc_fetch($key, $success);
				if($success) {
					$this->data = $buffer;
					return true;
				}
				$this->_error = 'apc_fetch(' . $key . ') failed!';
			}
		}
		else {
			$this->_error = 'Requested key (' . $key . ') doesn\'t exist.';
		}
		return false;
	}

	public function store($key, $ttl)
	{
		$this->_error = false;

		/*** File ***/
		if($this->_backend == 0) {
			$fname = $this->_file_mkname($key);

			//Encode data
			$buffer = json_encode($this->data, JSON_UNESCAPED_UNICODE);
			if($buffer !== false) {
				//Write data
				if(file_put_contents($fname, $buffer) !== false) {
					//Set the expiration timestamp as ctime in the future
					if(touch($fname, time() + (int) $ttl)) {
						return true;
					}
					else {
						$this->_error = 'Cannot touch ' . $fname . ', cache will not work! (check permissions?)';
					}
				}
				else {
					$this->_error = 'Cannot write file! (' . $fname . ')';
				}
			}
			else {
				$this->_error = 'json_encode() has failed! (' . json_last_error_msg() . ')';
			}

		/*** APC ***/
		} elseif($this->_backend == 1) {
			if(apc_store($key, $this->data, $ttl)) {
				return true;
			}
			else {
				$this->_error = 'apc_store(' . $key . ') failed!';
			}
		}
		return false;
	}
}