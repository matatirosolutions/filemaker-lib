<?php

namespace MSDev\MSLib;

/**
* Simple class to handle FM error codes and messages
*
* @author 		Steve Winter
* @date 		2012-07-31
* @version		0.0.1
*/
class FMException extends \Exception {

	protected $fmCode;
	protected $fmError;

	/**
	 * __construct
	 *
	 * @param numeric $code
	 * @param string $mess
	 */
	public function __construct($code, $mess) {
		parent::__construct();
		$this->fmCode				= $code;
		$this->fmError				= $mess;
	}

	/**
	 * getFMCode
	 *
	 * Returns the relevant FileMaker error code
	 *
	 * @return numeric
	 */
	public function getFMCode() {
		return $this->fmCode;
	}

	/**
	 *
	 * getFMError
	 *
	 * Returns the relevant FileMaker error message
	 * @return string
	 */
	public function getFMError() {
		return $this->fmError;
	}
}