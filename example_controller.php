<?php
class ExamplesController extends AppController {
	var $name = 'Examples';
	var $uses = array();
	var $components = array('CpanelApi');
	
	function api() {
		$this->CpanelApi->host = '';
		
		// sets the port to communicate on (defaults to 2086)
		// If just a user (not a reseller or admin)
		// you probably want to use ports 2082 or 2083
		// otherwise use the WHM ports, 2086 or 2087
		$this->CpanelApi->set_port(2082);
		
		// Use an API Hash
		$this->CpanelApi->hash = '';
		// or Credentials
		$this->CpanelApi->username = '';
		$this->CpanelApi->password = '';
		
		// Toggles debug flag
		$this->CpanelApi->debug = TRUE;
		// Toggles raw xml output flag, only valid if debug is TRUE
		$this->CpanelApi->rawXML = TRUE;
		
		// Example 1 - pull bandwidth and disk usage statistics for cpanel user
		$result = $this->CpanelApi->api2_query(
			'cpanel_user', // Change me
			'StatsBar',
			'stat',
			array(
				'display' => 'diskusage|bandwidthusage',
				'infinitylang' => '1',
				'rawcounter' => 'mainstats'
			)
		);
		
		if($result) {
			debug($result);
		} else {			
			debug($this->CpanelApi->error);
		}
		
		// Example 2 - get list of available api commands
		$result = $this->CpanelApi->applist();
		
		if(!$this->CpanelApi->error) {
			debug($result);
		} else {			
			debug($this->CpanelApi->error);
		}
	}
}
?>