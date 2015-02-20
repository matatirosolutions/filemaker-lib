<?php

namespace MSDev\MSLib;

use PHPMailer;
use Exception;

/**
 * 
 * SMTP capable HTML mail sending class
 * 
 * Using parameters specified in config.php enable the sending of
 * HTML email using an SMTP server. In this version it does not support
 * requirements for authentication however can easily be extended if
 * this becomes a requirement.
 * 
 * This class extends the open source PHPMailer which is required
 * and is bundled with with this distribution.
 * 
 * @author 	Steve Winter,
 * 			Matatiro Solutions,
 * 			steve@msdev.co.uk
 * 
 * @date	2012-08-01
 * @version	0.0.1
 *
 */
 class EmailSender extends PHPMailer {
	
	protected $conf;
	protected $confReq				= array('Mailer' => array('Type', 'From', 'Email'));
	
	/**
	 * 
	 * __construct
	 * 
	 * Instantiates the PHPMailer and registers certain variables based on values
	 * from within the configuration supplied
	 * 
	 * @param hash $conf	required, the
	 */
	 public function __construct($conf, $connect = true) {
	 	// set up the various parameters required by this class
	 	$this->conf					= $conf;
	 	
		if($connect) {
			$this->defaultConnection($conf);
		}
	}
	
	
	private function defaultConnection($conf) {
		// make sure that we have all of the required configuration
		if(!$conf->confCheck($this->confReq)) {
			throw new Exception('Unable to instantiate '.__CLASS__.' because config variables are missing');
		}
		
		$config						= $conf->get('Mailer');
		$this->connect($config);
	}
	
	public function connect($config) {
		$this->Mailer 				= $config['Type'];
		if($config['Type'] == 'smtp') {
			$this->Host 			= $config['SMTP']['Host'];
			$this->Port 			= $config['SMTP']['Port'];
			$this->SMTPAuth 		= false;
		
			$this->SMTPAuth			= $config['SMTP']['Auth'];
			if($config['SMTP']['Auth']) {
				$this->Username		= $config['SMTP']['Username'];
				$this->Password		= $config['SMTP']['Password'];
			}
				
			if($config['SMTP']['SSL']) {
				$this->SMTPSecure 	= "ssl";
			}
		}
		
		$this->FromName 			= $config['From'];
		$this->From     			= $config['Email'];
		$this->Sender   			= $config['Email'];
		
		$this->isHTML(true);
	}
	
	
	/**
	* sendEmail
	* 
	* Sends an HTML format email
	* 
	* Options are supported to specify recipients, subject and body.
	* 
	* @param hash $recipients			required, expects a hash of recipients in the format
	* 					[0] => [address] => fred@spoon.com
	* 						   [name]  => Fred Spoon
	* 						   [type]  => to (or cc or bcc)
	* 					[1] => [address]...
	* @param string $subject			required, the email subject
	* @param string $body				required, html string to use as the message body.
	* @return hash 						containing a result and message
	*/
	public function sendEmail($recipients, $subject, $body) {
		// set the subject and the body for the email
		$this->Subject  			= $subject;
		$this->Body     			= $body;
		$this->AltBody				= str_replace(array("<p>, <br />"), array("\n\n", "\n"), $body);
			
		// check the recipients and add them as the correct type
		foreach($recipients as $r) {
			if($r['address'] != '') {
				switch(strtolower($r['type'])) {
					case 'to':
						$this->AddAddress($r['address'], $r['name']);
						break;
					case 'cc':
						$this->AddCC($r['address'], $r['name']);
						break;
					case 'bcc':
						$this->AddBCC($r['address'], $r['name']);
						break;
				}
			}
		}
	
		// send the email
		if(!$this->Send()) {
			$result = array('sent' => false, 'message' => 'Error: '.$this->ErrorInfo);
		} else {
			$result = array('sent' => true, 'message' => 'Message sent.');
		}
		
		//return the result
		return $result;
	}
	
	/**
	 * alertSysAdmin
	 * 
	 * A utility function which can be called to send critical emails to a system administrator
	 * Parameters for the name, address etc are all set in config.php
	 * 
	 * @param string $action		the action which was being performed at the time the error occured
	 * @param string|hash $error	the resulting error
	 */
	 public function alertSysAdmin($action, $error) {
	 	$config						= $this->conf->get('SiteAdmin');
		// load the template
		$h							= $this->conf->get('h');
		$body 						= file_get_contents($this->conf->get('CodeRoot').$h->correctPath('/assets/templates/').'alert.html'); 
		
		// convert any arrays into strings so that they appear in a readable way
		$errStr						= is_array($error) ? '<pre>'.print_r($error, true).'</pre>' : $error;
		
		// replace the variables in the template
		$replace					= array('{action}', '{error}');
		$with						= array($action, $errStr);
		$body						= str_replace($replace, $with, $body);
		
		// set the subject for the email
		$subject 					= $config['ErrorSubject'];
		
		// set who it should be sent to
		$recipients 				= array(array('address' => $config['Email'],'name' => $config['Name'], 'type' => 'to'));
		
		// finally send the email
		$res						= $this->sendEmail($recipients, $subject, $body);
	}
}