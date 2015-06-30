<?php

namespace MSDev\MSlib;

/**
 * Created:    11/09/2011
 * Project:    msLib, to be imported into where ever...
 * Created by: owenberesford aka oab1
 * 
 * Class to wrap cURL, to allow software access to linked cURL client. 
 * Requires cURL to be part of the PHP interpreter.
 *
 * Requires the Env class.
 *
 * Uses the following config settings:
 *  - 'curl agent' - The HTTP user-agent string - recommend 'Matatiro tester v0.0'
 *	- 'curl redirect' - Support HTTP header 302 - on or off for debug, on by default
 *  - 'network timelimit' -  Maximum time in seconds to wait for HTTP requets.
 *  - 'curl cookie jar' - Enable cookies, and say where to put the cookie cache.
 *  - 'curl verbose' - Enable the cURL verbose mode mode, so the commplete HTTP transaction is dumped 
 *			to STDERR.
 *  - 'fresh cURL connection' -  Whether to preserve the cURL resource.  For sequential operations 
 *			against the same host, this is a performance benefit.  
 * 
 * 
 */

if(!function_exists('curl_init')) {
	die("Internal Error: This PHP interpreter is missing a required cURL library.");
}

class cURLclient
{
	protected $res;
	protected $env;
	

	function __construct(&$env) {
		$this->env		= &$env;
		$this->res		= null;

		$this->single	= $env->get('curlSingle');
	}



	public function sendGet($url) {
		if(!is_resource($this->res)) {
			$this->connect($url);
		}

		curl_setopt($this->res, CURLOPT_HTTPGET, 1);               
		curl_setopt($this->res, CURLOPT_RETURNTRANSFER,1);	
		$this->response 		= curl_exec($this->res);
		$returnCode				= curl_getinfo($this->res, CURLINFO_HTTP_CODE);
		if($this->single) {
			curl_close($this->res);
			$this->res			= null;
		}
		return array($returnCode, $this->response);
	}

	public function sendPost($url, $data) {
		if(!is_resource($this->res)) {
			$this->connect($url);
		}

		curl_setopt($this->res, CURLOPT_POSTFIELDS, $data);
		curl_setopt($this->res, CURLOPT_POST, 1);               
		curl_setopt($this->res, CURLOPT_RETURNTRANSFER,1);	
		$this->response 		= curl_exec($this->res);
		$returnCode				= curl_getinfo($this->res, CURLINFO_HTTP_CODE);
		if($this->single) {
			curl_close($this->res);
			$this->res			= null;
		}
		return array($returnCode, $this->response);
	}

	protected function connect($url) {
		$this->res				= curl_init($url);

		if($this->env->get('curlHTTPHeader')) {
			curl_setopt($this->res, CURLOPT_HTTPHEADER, $this->env->get('curlHTTPHeader'));
		}
		
		curl_setopt($this->res, CURLOPT_HEADER, false);
		curl_setopt($this->res, CURLOPT_USERAGENT, $this->env->get('curlAgent'));
		$redir					= $this->env->get('curlRedirect');
		switch($redir) {
		case 1:
			curl_setopt($this->res, CURLOPT_FOLLOWLOCATION, true);
			break;

		case 0:
			curl_setopt($this->res, CURLOPT_FOLLOWLOCATION, false);
			break;
		
		case null:	
			curl_setopt($this->res, CURLOPT_FOLLOWLOCATION, true);
			break;

		default:

		}
		curl_setopt($this->res, CURLOPT_TIMEOUT, $this->env->get('curlTimelimit')); 
		curl_setopt($this->res, CURLOPT_SSL_VERIFYPEER, $this->env->get('curlVerifyPeer'));
		curl_setopt($this->res, CURLOPT_SSL_VERIFYHOST, $this->env->get('curlVerifyHost'));
		
		if($this->env->get('curlUsername') && $this->env->get('curlPassword')) {
			curl_setopt($this->res, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($this->res, CURLOPT_USERPWD, $this->env->get('curlUsername').':'.$this->env->get('curlPassword'));
		}
		
		$jar					= $this->env->get('curlCookieJar');
		if($jar) {
			curl_setopt($this->res, CURLOPT_COOKIEJAR, $jar);
		}
		if($this->env->get('curlVerbose') ) {
			curl_setopt($this->res, CURLOPT_VERBOSE, 1);
		}
			
		return 0;
	}


	public function getResponse() {
		return $this->response;
	}

	// reference: http://uk.php.net/manual/en/function.curl-getinfo.php
	public function interrogate($symbol ) {
		if(is_resource($this->res)) {
			return curl_getinfo($this->res, $symbol);
		} else {
			return null;
		}
	} 

	public function close( ) {
		if(is_resource($this->res)) {
			return curl_close($this->res);
		} else {
			return null;
		}
	} 
	
	
	/**
	 * Utility function to convert PHP data structures into a flat string which may be sent as a HTTP POST.
	 * 
	 * @param hash of string $args
	 * @return string, the packed data.  Chars escaped to be legal over HTTP
	 */
	public function postPack($args) {{{
		$str			='';
		foreach($args as $k=>$v) {
			if(is_array($v)) {
				$str		.= urlencode($k).'='.$this->postPack($v);	
			} else {
				$str		.= urlencode($k).'='.urlencode($v).'&';
			}
		}
		return $str;
	}}}
	
	/**
	 * Utility function to pack hashes into flat strings using [] for sub-keys
	 * 
	 * NOTE: will only correctly nest to one level deep.
	 * 
	 * @param hash $arr
	 * @param string $parent
	 * @return string
	 */
	 public function postPackFlat($arr, $parent = false) {
		$str			= '';
		foreach($arr as $k => $v) {
			if(is_array($v)) {
				$str			.= $this->postPackFlat($v, $k);
			} else {
				if($parent) {
					$str		.= urlencode("{$parent}[{$k}]").'='.urlencode($v).'&';
				} else {
					$str		.= urlencode($k).'='.urlencode($v).'&';
				}
			}
		}
		
		return $str;
	}
}