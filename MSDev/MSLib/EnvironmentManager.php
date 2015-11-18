<?php

namespace MSDev\MSLib;

use Katzgrau\KLogger\Logger;
use Exception;

/**
 * EnvironmentManager
 * 
 * Simple class to load and manage configuration variables
 * 
 * @author 	Steve Winter,
 * 			Matatiro Solutions,
 * 			steve@msdev.co.uk
 * 
 *@date		2012-07-31
 *@version	0.0.1
 *
 */
 class EnvironmentManager {
	
	protected $conf;
	
	const CONF_PHP					= 2;
	
	/**
	 * get
	 * 
	 * Retrieve a config value
	 * 
	 * @param string $key		name of the value to return
	 * @returns mixed|false		returns the value request or false if it does not exist
	 */
	 public function get($key) {
	 	// does the requested item exist in the config
		if(array_key_exists($key, $this->conf)) {
			// it does, so return the value
			return $this->conf[$key];
		
		// no it doesn't
		} else {
			// return false so that we can reliably test for this at the calling point
			return false;
		}
	}
	
	/**
	 * set
	 * 
	 * Sets a configuration variable.
	 * 
	 * @param string $key	name of the config value to set
	 * @param mixed $value	value to be held by that key
	 */
	 public function set($key, $value) {
	 	// add the sent key/value pair to the config hash
		$this->conf[strval($key)]	= $value;
	}
	
	
	/**
	 * populate
	 * 
	 * Populates the configuration settings. 
	 * 
	 * At present only supports PHP files howver it could be extended to support other sources of config data
	 * 
	 * @param constant $type		class constant defining the type of data
	 * @param string $source		location of the data to be loaded
	 * @throws Exception
	 */
	 public function populate($type, $source) { 
		switch($type) {
			case EnvironmentManager::CONF_PHP:
				$this->_loadPHP($source);
				break;
				
			default:
				throw new Exception('Invalid config type specified.');
		}
	}

	public function confCheck($values) {
		foreach($values as $k => $v) {
			if(is_array($v)) {
				$val				= $this->get($k);
				if(!$val) {
					return false;
				}
				foreach($v as $i) {
					if(!isset($val[$i]) || $val[$i] == '') {
						return false;
					}
				}
			} else {
				if(!$this->get($v) || $this->get($v) == '') {
					return false;
				}
			}
		}
		return true;
	}
	
	public function BuildEnvironment($path) {
		try {
			$this->populate(EnvironmentManager::CONF_PHP, $path.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'config.php');
			$this->set('CodeRoot', $path);
		
			$opts							= $this->get('Options');
			
			if($opts['Helpers']) {
				$help						= new Helpers();
				$this->set('h', $help);
			}
			
			if($opts['Mailer']) {
				$email						= new EmailSender($this);
				$this->set('mail', $email);
			}
			
			if($opts['Errors']) {
				$err						= new Error();
				$this->populate(EnvironmentManager::CONF_PHP, $path.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'errors.php');
				$this->set('err', $err);
			}
			
			if($opts['FileMaker']) {
				$fm							= new FMConnector($this);
				$this->set('fm', $fm);
			}

			if($opts['Log']) {
				$log						= new Logger($path.DIRECTORY_SEPARATOR.'log');
				$this->set('log', $log);
			}
		} catch(Exception $e) {
			$email->alertSysAdmin('Loading classes', $e->getMessage());
			$err->customMessage(500, $e->getMessage());
		}
		
		return $this;
	}
	
	/**
	 * _loadPHP
	 * 
	 * Loads a PHP file, examines it for set variables, evaluates those
	 * then loads them by name into the configuration settings
	 * 
	 * @param string $source		path to a php config file.
	 * @throws Exception
	 */
	 private function _loadPHP($source) {
		// test that we have access to the file
		if(!file_exists($source)) {
			throw new Exception("Unable to access requested config file {$source}");
		}
	 	
	 	// load the file
		$data						= file_get_contents($source);
		// need to strip any opening/closing PHP delimitors off, or eval fails
		$data						= str_replace(array('<?php', '<?', '?>'), array('', '', ''), $data);
		
		// look for all defined variables in the file
		$matches					= array();
		$names						= '/(\$[a-zA-Z_][0-9A-Za-z_]*)[ \t]*=/';
		$ret						= preg_match_all($names, $data, $matches);

		// make them local so that we can access them
		eval($data);
		
		// loop through all the variables found
		foreach($matches[1] as $v) {
			// get the name of the variable
			$ref					= substr($v, 1);
			// make usre it's not null, and set it
			if(isset($$ref) && !is_null($$ref)) {
				$this->conf[$ref]=$$ref;
			}
		}
	}
}