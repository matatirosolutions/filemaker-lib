<?php

namespace MSDev\MSLib;

use Exception;

class Helpers {
	
	public function correctPath($path) {
		return str_replace('/', DIRECTORY_SEPARATOR, $path);
	}
	
	public function validateURL($env, $req) { 
		$route						= $req->route;
		if(!isset($route['id']) || $route['id'] == '' || !isset($route['hash']) || $route['hash'] == '') {
			throw new Exception('Missing validation parameters', 1003);
		}
		if(strtoupper(md5($route['id'].$env->get('SecuritySalt'))) != $route['hash']) {
			throw new Exception('Invalid security token', 1004);
		}
	}
	
	public function validateFMTimebasedURL($env, $req) {
		$route						= $req->route;
		if(!isset($route['timestamp']) || $route['timestamp'] == '' || !isset($route['hash']) || $route['hash'] == '') {
			throw new Exception('Missing validation parameters', 1003);
		}
		if(strtoupper(md5($route['timestamp'].$env->get('SecuritySalt'))) != $route['hash']) {
			throw new Exception('Invalid security token', 1004);
		}
		
		date_default_timezone_set("UTC");
		$now 						= time();
		if($route['timestamp'] + 120 < time()) {
			throw new Exception('URL has expired', 1004);
		}
	}
	
	public function splitPath($req) {
		$route						= $req->route;
		return explode("/", $route['path']);
	}
	
	
	
	
	public static function showMe($data, $title=false, $die=false, $location=true) {
		echo '<div class="debug">';
		if($location || $title) {
			echo '  <p>================================================<br/>';
		}
		if($title) {
			echo    $title;
		}
		if($title && $location) {
			echo '<br/>================================================</br/>';
		}
		if($location) {
			$caller = debug_backtrace();
			echo $caller[0]['file'].' # '.$caller[0]['line'];
		}
		if($title || $location) {
			echo '<br/>================================================</p>';
		}
	
		if(is_array($data) || is_object($data)) {
			echo '<pre>';
			print_r($data);
			echo '</pre>';
		} else {
			echo '<p>'.$data.'</p>';
		}
		echo '</div>';
		if($die == true) {
			die();
		}
	}
}