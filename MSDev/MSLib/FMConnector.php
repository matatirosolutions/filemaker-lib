<?php

namespace MSDev\MSLib;

use FileMaker;
use Exception;
use MSDev\MSLib\cURLclient;

/**
 * Wrapper class to simplify access to FileMaker
 * 
 * @author 	Steve Winter
 * 			Matatiro Solutoins
 * 			steve@msdev.co.uk
 * 
 * @date	2012-07-31
 * @version 0.0.1
 *
 */
 class FMConnector {
	
 	const FM_RECORD_ID				= '__RECORD_ID__';
 	const FM_MOD_ID					= '__MODIFICATION_ID__';
 	const FM_RECORD					= '__RECORD__';
 	const FM_METADATA				= '__METADATA__';
 	
	protected $conf;
	protected $fm;
	protected $confReq				= array(
		'FMServer' => array('host', 'database', 'username', 'password', ) //'logLayout'
	);
	
	/**
	 * 
	 * __construct
	 * 
	 * Construct the FMConnector object and create a connection to FileMaker
	 * 
	 * The connection is cached within the class to prevent the need to re-establish
	 * it for each call which is required.
	 * 
	 * @param has $conf		Standard configuration class reference
	 */
	 public function __construct($conf) {
		$this->conf					= $conf;
		
		// make sure that we have all of the required configuration
		if(!$conf->confCheck($this->confReq)) {
			throw new Exception('Unable to instantiate '.__CLASS__.' because config variables are missing');
		}
		
		$config						= $conf->get('FMServer');
		$this->fm					= new \FileMaker(
			$config['database'], 
			$config['host'], 
			$config['username'], 
			$config['password']
		);
	}
	
	
	/**
	 * get
	 * 
	 * Provides the connection object which is created on instantiation
	 * 
	 * Usually access to FileMaker will be via other methods in this class,
	 * however there may be occasions where it's preferable to let another class
	 * manually manipulate a request. 
	 * 
	 * This method simply returns the connection object to allow that to occur.
	 */
	 public function get() {
		return $this->fm;
	 }
	
	 
	 /**
	  * select
	  * 
	  * Selects records from FileMaker based on the supplied query
	  * 
	  * At present this performs a simple AND query, using the provided parameters.
	  * 
	  * It could easily be extended to support more complex OR searches, or to add additional
	  * paramters to the FM query such as sorting, script running etc.
	  * 
	  * @param string $layout		The layout to use when accessing FileMaker  
	  * @param array $query			An array of field - value hashes which represents the query to be performed
	  * @param hash $options		Options which are used when records are being extracted. See _extractRecords 
	  * 							for details
	  * 
	  * @throws FMException
	  */
	  public function select($layout, $query, $options = array('ids' => true)) {
		// initiate the find command
		$cmd						= $this->fm->newFindCommand($layout);
		
		// loop through the query adding the requests
		foreach($query as $field => $value) {
			$cmd->addFindCriterion($field, $value);
		}
	
		// see if we have any limits
	  	if(array_key_exists('maxRecords', $options)) {
			$start				= array_key_exists('start', $options) ? $options['start'] : 0;
			$cmd->setRange($start, $options['maxRecords']);
		}
		
		// execute the call to the database
		$res						= $cmd->execute();
	
		// check and see if FM returned an error, if so throw an exception
		if(FileMaker::isError($res)) {
			throw new FMException($res->code, $res->message);
		}
		
		// extract the records from the data set and return them to the requester
		return $this->_extractRecords($res, $options);
	}
	
	
	/**
	 * 
	 * selectAny
	 * 
	 * Selects any record from FileMaker for the specified layout
	 * 
	 * @param string $layout		The layout in FileMaker from which to retrieve a single record
	 * @param string|const $return	The data to be returned, see _processReturn for options. 
	 * 								Defaults to returning the full record 
	 * 
	 * @throws FMException
	 */
	 public function selectAny($layout, $return = FMConnector::FM_RECORD) {
		// create the command and perform the call
		$cmd						= $this->fm->newFindAnyCommand($layout);
		$res						= $cmd->execute();
		
		// check for any errors
		if(FileMaker::isError($res)) {
			throw new FMException($res->code, $res->message);
		}
		
		// process the requested return data
		return $this->_processReturn($res, $return);
	}
	
	/**
	 * 
	 * selectAll
	 * 
	 * Selects all records from FileMaker for the specified layout
	 * 
	 * @param string $layout		The layout in FileMaker from which to retrieve a single record
	 * @param integer $start		Record in the set to begin from, defaults to 0
	 * @param integer $setRize		No records to return, defaults to null meaning all
	 * @param string|const $return	The data to be returned, see _processReturn for options. 
	 * 								Defaults to returning the full record 
	 * @param hash $options			Options which are used when records are being extracted. See _extractRecords
	 * 								for details
	 * @throws FMException
	 */
	 public function selectAll($layout, $start = 0, $setSize = null, $options = array('ids' => true), $return = FMConnector::FM_RECORD) {
		// create the command and perform the call
		$cmd						= $this->fm->newFindAllCommand($layout);
		$cmd->setRange($start, $setSize);
		$res						= $cmd->execute();
		
		// check for any errors
		if(FileMaker::isError($res)) {
			throw new FMException($res->code, $res->message);
		}
		
		// process the requested return data
		return $this->_processReturn($res, $return, $options);
	}
	
	
	/**
	 * 
	 * insert
	 * 
	 * Adds a record to FileMaker
	 * 
	 * @param string $layout		The FileMaker layout to insert data via
	 * @param hash $data			A hash of data to be inserted into the layout
	 * @param string $return		What data should be returned as result of the insert. 
	 * 								See _processReturn for options. 
	 * 								Defaults to null
	 * 
	 * @throws FMException
	 */
	 public function insert($layout, $data, $return = null, $script = array()) {
		// create the command, set the data, and execute
		$cmd						= $this->fm->newAddCommand($layout, $data);
		foreach($script as $sc => $param) {
			$cmd->setScript($sc, $param);
		}	
		$res						= $cmd->execute();

		// see if an error was returned
		if(FileMaker::isError($res)) {
			throw new FMException($res->code, $res->message);
		}
		
		// gather whatever data was requested (defaults to nothing)
		$response					= $this->_processReturn($res, $return);
		
		// if we were asked for the record, collapse the array
		if($return == FMConnector::FM_RECORD) {
			return $response[0];
		} else {
			return $response;
		}
	}
	
	
	/**
	 * 
	 * update
	 * 
	 * Update a record in FileMaker
	 * 
	 * @param string $layout		The FileMaker layout to insert data via
	 * @param numeric $recid		The FileMaker internal record ID (as viewed with Get(RecordID) in FM)
	 * @param hash $data			A hash of data to be updated via the layout
	 * @param string $return		What data should be returned as result of the insert. 
	 * 								See _processReturn for options. 
	 * 								Defaults to null
	 * 
	 * @throws FMException
	 */
	 public function update($layout, $recid, $data, $return = null, $script = array()) {
		// create the command, set the data, and execute
		$cmd						= $this->fm->newEditCommand($layout, $recid, $data);
		foreach($script as $sc => $param) {
			$cmd->setScript($sc, $param);
		}
		$res						= $cmd->execute();
	
		// see if an error was returned
		if(FileMaker::isError($res)) {
			throw new FMException($res->code, $res->message);
		}
		
		// send back whatever data was requested (defaults to nothing)
		return $this->_processReturn($res, $return);
	}
	
	
	/**
	 * 
	 * log
	 * 
	 * Adds a log entry to the FileMaker log table
	 * 
	 * Requires a layout to be specified within config.php to submit records via
	 * 
	 * @param hash $log		The data to be logged. Possible hash keys are:
	 * 							Action: 	what action was performed which caused the need to create a log entry
	 * 							Result:		the outcome of the above action
	 * 							Request:	what data was sent to the action
	 * 							Response:	any data returned by performing the action
	 * 							RequestID:	an internal ID which can be used to locate a record being acted upon
	 * 						All are optional, but without at least an action and a result there is little point
	 * 
	 * 						It's fine to pass in arrays, hashes or objects for any variable (where it makes
	 * 						sense to do so. These will be converted to strings prior to being submitted to FM.						
	 * 
	 * 						NOTE: hash keys are case sensitive.
	 */
	 public function log($log) {
		// convert any arrays or objects to more sensible string representations to store
		foreach($log as $k => $v) {
			if(is_array($v) || is_object($v)) {
				$log[$k]			= print_r($v, true);
			}
		}
	
		// try and add the record to the log layout
		try {
			$conf					= $this->conf->get('FMServer');
			return $this->insert($conf['logLayout'], $log, FMConnector::FM_RECORD);

		// if the log entry fails, catch that
		} catch(FMException $e) {
			// this is pretty serious, since without a log we've got no idea what's going on (or wrong) so email the sysAdmin
			$mailer					= $this->conf->get('mailer');
			$mailer->alertSysAdmin(
				'Attempting to write log entry',
				'FileMaker returned error code: '.$e->getFMCode().' message: '.$e->getFMError());
		}
	 }
	 
	 /**
	 * The command to extract externally stored document of conatainer field
	 *
	 * This function only works for external containers
	 *
	 * @param string $reqURL	- required, to pass the container URL
	 * @return $containerData	- return the conatainer data
	 * @throws FMPaccessException
	 *
	 */
	 public function getExternalContainerContent($path) {
	 	$config						= $this->conf->get('FMServer');
		$protocol					= $config['fmpContainerProtocol'];
		$prot						= $protocol ? $protocol : 'https'; 

		$this->conf->set('curlUsername', $config['username']);
		$this->conf->set('curlPassword', $config['password']);
		if(isset($config['cURLAuthType'])) {
			$this->conf->set('curlAuthMethod', $config['cURLAuthType']);
		}

		$url						= "{$prot}://{$config['host']}{$path}";

		$c							= new cURLclient($this->conf);	
		$res						= $c->sendGet($url);

		if($res[0] != 200) {
			$log = $this->conf->get('log');
			$log->debug("Failed to run the extract container $path, data ".print_r($res, true));
			throw new FMException($res[0], 'Unable to load container content.');
		}
		
		return $res[1];
	}
	
	
	/**
	 * 
	 * _processReturn
	 * 
	 * Processes the response of any FileMaker query, providing the required data
	 * 
	 * @param object $res		The FileMaker result object from the query performed
	 * @param string $return	What data is required. Options are
	 * 								FMConnector::FM_RECORD_ID:	this will return only the FileMaker internal record ID
	 * 								FMConnector::FM_RECORD:		this will return the record(s) contained within the provided
	 * 															result object as an array of hashes
	 * 								null:						nothing (at all) is returned
	 * 								**string**:					any string which represents the (full) name of a field which
	 * 															exists on the layout. If the requested field does not exist
	 * 															an exception is thrown. 					
	 * @param hash $opts		The options to use when processing records. See _extractRecords for details
	 * 
	 * @throws FMException
	 */
	 private function _processReturn($res, $return, $opts = array('ids' => true)) {
		// look at what's been requested and work out what data should be returned
		switch($return) {
			// just want the record ID
			case FMConnector::FM_RECORD_ID:
				$rec				= $res->getFirstRecord();
				return $rec->getRecordID();
				break;
		
			// want the record(s)
			case FMConnector::FM_RECORD:
				return $this->_extractRecords($res, $opts);
				break;
				
			// don't want anything back
			case null:
				return;
				break;
				
			// want a specific field
			default:
				// extract the records
				$rec				= $this->_extractRecords($res, $opts);
				
				// check and see if the requested field exists in the record set
				if(array_key_exists($return, $rec[0])) {
					// return it since it does
					return $rec[0][$return];
				} else {
					// throw an exception because it's not there
					throw new FMException(-1, "Field $return does not exist in that record");
				}
		}
	}
	
	
	/**
	 * _extractRecords
	 * 
	 * Extracts record data from a FileMaker result set
	 * 
	 * @param object $res		The FileMaker result set
	 * @param hash $options		A hash of options. Current options are
	 * 								ids	boolean	should the record and mod IDs be in the returned set
	 * 											defaults to true
	 * @return hash				A hash of the records extracted from the data set 
	 */
	 private function _extractRecords($res, $options = array('ids' => true)) {
		// an array to hold the records
	 	$data						= array();
		
		// retrieve the recrods from the found set
		$rec						= $res->getRecords();
		$fields						= $res->getFields();
		
		// check and see if there are any portals on the layout
		$rSets						= $res->getRelatedSets();
		$portals					= !FileMaker::isError($rSets);
		
		// see if metadata has been requested
		if(array_key_exists('metadata', $options) && $options['metadata']) {
			$md						= array(
				'found'				=> $res->getFoundSetCount(),
				'fetch'				=> $res->getFetchCount(),
				'total'				=> $res->getTableRecordCount(),
			);
		}
		
		// loop through each record
		foreach($rec as $r) {
			// an array to hold the record data
			$d						= array();
			
			// loop through each field and set the data
			foreach($fields as $f) {
				if(array_key_exists('timestamps', $options) && in_array($f, $options['timestamps']) && $r->getField($f) != '') {
					$d[$f]			= $r->getFieldAsTimestamp($f);
				} elseif(array_key_exists('decode', $options) && $options['decode'] ) {
					$d[$f]			= $r->getFieldUnencoded($f);
				} else {
					$d[$f]			= $r->getField($f);
				}
			}
			
			// add the IDs if requested
			if(array_key_exists('ids', $options) && $options['ids']) {
				$d[FMConnector::FM_RECORD_ID] 	= $r->getRecordId();
				$d[FMConnector::FM_MOD_ID]		= $r->getModificationId();
			}
			
			if(array_key_exists('metadata', $options) && $options['metadata']) {
				$d[FMConnector::FM_METADATA] 	= $md;
			}
			
			// were there any portals on this layout
			if($portals) {
				// loop through each portal
				foreach($rSets as $p) {
					// array to hold the portal data
					$pOut			= array();
					
					// get the related record set
					$pData			= $r->getRelatedSet($p);
					
					// make sure there were related records
					if(!FileMaker::isError($pData)) {
						
						// find out which fields are in the portal
						$pFields	= $pData[0]->getFields();
					
						// loop through each row in the portal
						foreach($pData as $pRow) {
							$prOut		= array();
							
							// and each field
							foreach($pFields as $pf) {
								
							    // remove the name of the relationship from the front of the related field name if trim is set
							    if(array_key_exists('trim', $options) && $options['trim'] && strpos($pf, '::')) {
							        $f = substr($pf, strpos($pf, '::') + strlen('::') );
							    } else {
							        $f = $pf;
							    }
							    
							    // and extract the data
							    $prOut[$f]	= $pRow->getField($pf);
							}
							
							// add in the ids if we've been asked to
							if(array_key_exists('ids', $options) && $options['ids']) {
								$prOut[FMConnector::FM_RECORD_ID] 	= $pRow->getRecordId();
								$prOut[FMConnector::FM_MOD_ID]		= $pRow->getModificationId();
							}
						
							// add the row to the portal array
							$pOut[]		= $prOut;
						}
					}
					
					// add the portal to the record
					$d[$p]			= $pOut;
				}
			}
			
			// add the record to the record set
			$data[]					= $d;
		}
		
		// send the data back
		return $data;
	}
	
}
