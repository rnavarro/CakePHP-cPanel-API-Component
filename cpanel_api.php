<?php
class CpanelApiComponent extends Object {
	
	/**
	 * Toggles the debug flag to display verbose output
	 * 
	 * @var boolean
	 * @access public
	 */
	var $debug = FALSE;
	
	var $error = NULL;
	
	var $host = '';
	
	var $user = '';
	
	var $password = '';
	
	var $__curl;
	
	var $__port = '2086';
	
	var $__protocol = 'http://';

	//called before Controller::beforeFilter()
	function initialize(&$controller, $settings = array()) {
	}
	
	//called after Controller::beforeFilter()
	function startup(&$controller) {
		// Create curl Object
		$this->curl = curl_init();
		// Allow self-signed certs
		curl_setopt($this->__curl, CURLOPT_SSL_VERIFYPEER, 0);
		// Allow self-signed certs
		curl_setopt($this->__curl, CURLOPT_SSL_VERIFYHOST, 0);
		// Return contents of transfer on curl_exec
		curl_setopt($this->__curl, CURLOPT_RETURNTRANSFER, 1);
	}

	//called after Controller::beforeRender()
	function beforeRender(&$controller) {
	}

	//called after Controller::render()
	function shutdown(&$controller) {
		curl_close($this->__curl);
	}

	//called before Controller::redirect()
	function beforeRedirect(&$controller, $url, $status=null, $exit=true) {
	}
	
	/**
	 * Sets the protocol based on the specified port
	 * @param int $port
	 * @return void
	 */
	function set_port($port) {
		$this->__port = $port;
		if($port == '2087' || $port == '2083' || $port == '443') {
			$this->__protocol = 'https://';
		}
	}
	
	function __xmlapi_query($function, $calls = array()) {
		
	}
}
?>