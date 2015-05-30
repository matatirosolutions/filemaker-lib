<?php

namespace MSDev\MSLib;

class Error {
	
	protected $types;
	
	
	public function __construct() {
		$this->setTypes();
	}
	
	public function setHeader($typ) {
		header("HTTP/1.1 {$typ} {$this->types[$typ]}");
	}
	
	public function defaultError($typ) {
		$this->apiExit($typ, $this->types[$typ], $typ);
	}
	
	public function customMessage($typ, $msg) {
		$this->apiExit($typ, $msg, $typ);
	}
	
	public function fatalUserError($env, $id, $e) {
		$this->setError($env, $id, $e);
		$this->htmlExit($env, false);
	}
	
	public function outputJson($env, $typ, $json) {
		header('Content-Type: application/json');
		header("HTTP/1.1 {$typ} {$this->types[$typ]}");
		print(json_encode($json));
		exit();
	}
	
	private function setError($env, $id, $e) {
		$errors						= $env->get('errors');
		$err						= isset($errors[$id]) ? $errors[$id] : $errors[1000];
		
		$cErr						= $env->get('alerts') ? $env->get('alerts') : array();
		$cErr[]						= $err;
		
		$env->set('alerts', $cErr);
		if($err['alertSysAdmin']) {
			$this->errorAlert($env, $err, $e);
		}
	}
	
	private function errorAlert($env, $err, $e) {
		$mailer						= $env->get('mailer');
		$msg						= str_replace(
			array('#errorCode#', '#errorMessage#'), 
			array($e->getCode(), $e->getMessage()), 
			$err['sysAdminMessage']
		);
		
		$mailer->alertSysAdmin($err['errorAction'], $msg);
	}
	
	
	private function htmlExit($env, $err) {
		$loader 					= new \Twig_Loader_Filesystem($env->get('CodeRoot').'/assets/templates/');
		$twig 						= new \Twig_Environment($loader);
		
		$template = $twig->loadTemplate('error.html.twig');
		echo $template->render(
			array(
				'err' => $env->get('alerts')
			)
		);
		exit();
	}
	
	
	private function apiExit($err, $msg, $typ) {
		$json						= array(
			'Error' => $err,
			'ErrorMessage' => $msg,
		);
		
		$this->outputJson(false, $typ, $json);
	}
	
	private function setTypes() {
		$this->types				= array(
			100 => 'Continue',
			101 => 'Switching Protocols',
			102 => 'Processing',
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			207 => 'Multi-Status',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => 'Switch Proxy',
			307 => 'Temporary Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			418 => 'I\'m a teapot',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			424 => 'Failed Dependency',
			425 => 'Unordered Collection',
			426 => 'Upgrade Required',
			449 => 'Retry With',
			450 => 'Blocked by Windows Parental Controls',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			506 => 'Variant Also Negotiates',
			507 => 'Insufficient Storage',
			509 => 'Bandwidth Limit Exceeded',
			510 => 'Not Extended',
		); 
	}
	
}