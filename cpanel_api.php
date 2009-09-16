<?php
class CpanelApiComponent extends Object {
	
	/**
	 * Toggles the debug flag to display verbose output
	 * 
	 * @var boolean
	 * @access public
	 */
	var $debug = FALSE;
	
	/**
	 * Toggles the raw xml flag to display raw xml debug output, only valid if debug set to TRUE
	 * 
	 * @var boolean
	 */
	var $rawXML = FALSE;
	
	/**
	 * NULL if no error, error message otherwise
	 * 
	 * @var mixed
	 */
	var $error = NULL;
	
	/**
	 * cPanel host
	 * 
	 * @var string
	 */
	var $host = '';
	
	/**
	 * WHM api hash
	 * 
	 * @var string
	 */
	var $hash = '';
	
	/**
	 * cPanel username
	 * 
	 * @var string
	 */
	var $username = '';
	
	/**
	 * cPanel password
	 * 
	 * @var string
	 */
	var $password = '';
	
	/**
	 * curl Instance object
	 * 
	 * @var object
	 */
	var $__curl;
	
	/**
	 * cPanel Port
	 * 
	 * @var int
	 */
	var $__port = 2086;
	
	/**
	 * cPanel protocol
	 * 
	 * @var string
	 */
	var $__protocol = 'http://';
	
	function startup(&$controller) {
		// Create curl Object
		$this->__curl = curl_init();
		// Allow self-signed certs
		curl_setopt($this->__curl, CURLOPT_SSL_VERIFYPEER, 0);
		// Allow self-signed certs
		curl_setopt($this->__curl, CURLOPT_SSL_VERIFYHOST, 0);
		// Return contents of transfer on curl_exec
		curl_setopt($this->__curl, CURLOPT_RETURNTRANSFER, 1);
	}

	function shutdown(&$controller) {
		curl_close($this->__curl);
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
	
	/**
	 * Prepares our query and sends it
	 * 
	 * @param string $function
	 * @param array $calls [optional]
	 * @return mixed
	 */
	function xmlapi_query($function, $calls = array()) {
		App::import('Xml');
		
		if(!$function) {
			$this->error = 'xmlapi_query requires a function to be passed to it';
			return FALSE;
		}
		
		// Build our api query
		$args = http_build_query($calls, '', '&');
		$query = $this->__protocol . $this->host . ':' . $this->__port . '/xml-api/' . $function . '?' . $args;
		
		if($this->debug) {
			debug('Query: ' . $query);
		}
		
		// Check for credentials and set them
		if($this->hash) {
			curl_setopt($this->curl, CURLOPT_USERPWD, $username . ':' . $password);
		} elseif($this->username) {
			curl_setopt($this->__curl, CURLOPT_USERPWD, $this->username . ':' . $this->password);
		} else {
			$this->error = 'Missing authentication information (api hash or user/pass)';
			return FALSE;
		}
		
		curl_setopt($this->__curl, CURLOPT_URL, $query);
		$result = curl_exec($this->__curl);
		
		$xml_array = new XML($result);
		$xml_array = Set::reverse($xml_array); // Super cake magic...
		
		if($this->debug) {
			debug($xml_array);
			if($this->rawXML) {
				debug($result);
			}
		}
		
		if(stristr($result, '<body>') == TRUE) {		
			if(stristr($result, 'Login Attempt Failed') == TRUE) {
				$this->error = 'Login Attempt Failed';
				return FALSE;
			}
			
			if(stristr($result, 'action="/login/"') == TRUE) {
				$this->error = 'Authentication Error';
				return FALSE;
			}
			
			if(stristr($result, '404 Not Found') == TRUE) {
				$this->error = 'cPanel API 404 Error';
				return FALSE;
			}
			
			$this->error = 'Generic Error';
			return FALSE;
		}
		
		if ($result == FALSE) {
			$this->error = 'curl_error threw error "' . curl_error($this->__curl) . '"';
			return FALSE;
		}
		
		return $xml_array;
	}
	
	/**
	 * Uses the fast-mode option to call API1-functions
	 * 
	 * @param string $username
	 * @param string $module
	 * @param string $function
	 * @param array $args [optional]
	 * @return array
	 */
	function api1_query($username, $module, $function, $args = null) {
		$call = array(
			'user' => $username,
			'cpanel_xmlapi_module' => $module,
			'cpanel_xmlapi_func' => $function,
			'cpanel_xmlapi_apiversion' => '1'
		);
		
		if (is_array($args)) {
			foreach($args as $key => $data) {
				$call['arg-' . $key] = $data;
			}
		}
		
		return $this->xmlapi_query('cpanel', $call);
	}
	
	/**
	 * Uses the fast-mode option to call API2-functions
	 * 
	 * @param string $username
	 * @param string $module
	 * @param string $function
	 * @param array $args [optional]
	 * @return array
	 */
	function api2_query($username, $module, $function, $args = null) {
		$call = array(
			'user' => $username,
			'cpanel_xmlapi_module' => $module,
			'cpanel_xmlapi_func' => $function,
			'cpanel_xmlapi_apiversion' => '2'
		);
		
		if (is_array($args)) {
			foreach($args as $tag => $data) {
				$call[$tag] = $data;
			}
		}
		
		return $this->xmlapi_query('cpanel', $call);
	}
	
	/**
	 * Just a wrapper
	 * 
	 * @param string $error
	 * @return void
	 */
	function error_log($error) {
		$this->error = $error;
		return FALSE;
	}
	
	####
	#  XML API Functions
	####

	// This function lists all XML/JSON API functions available to you.
	function applist() {
		return $this->xmlapi_query('applist');
	}

	####
	# Account functions
	####

	// This API function allows you to create new cPanel accounts.
	// $acctconf = array('username' => string, 'password' => string, 'domain' => string)
	// Optional variables (f.e. plan, contactemail, cpmod, language, etc.) can be found at:
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/CreateAccount
	function createacct($acctconf) {
		if (!isset($acctconf['username']) || !isset($acctconf['password']) || !isset($acctconf['domain'])) {
			error_log("createacct requires that username, password & domain elements are in the array passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('createacct', $acctconf);
	}

	// Using this API function, you can change a cPanel account's password.
	// $username = string, $pass = string
	function passwd($username, $pass){
		if (!isset($username) || !isset($pass)) {
			error_log("passwd requires that an username and password are passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('passwd', array('user' => $username, 'pass' => $pass));
	}

	// This API function allows you to change bandwidth limits for cPanel accounts.
	// $username = string, $bwlimit = integer (in Megabytes)
	function limitbw($username, $bwlimit) {
		if (!isset($username) || !isset($bwlimit)) {
			error_log("limitbw requires that an username and bwlimit are passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('limitbw', array('user' => $username, 'bwlimit' => $bwlimit));
	}

	// This API function will generate a list of accounts associated with a server.
	// $searchtype = string (Allowed values: domain, owner, user, ip, package)
	// $search = string (regular expression)
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/ListAccounts
	function listaccts($searchtype = null, $search = null) {
		if ($search) {
			return $this->xmlapi_query('listaccts', array('searchtype' => $searchtype, 'search' => $search ));
		}
		return $this->xmlapi_query('listaccts');
	}

	// Using this API function, you are able to change specific attributes of cPanel accounts, such as the theme or domain.
	// $opts = array('user' => string). Optional variables can be found at:
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/ModifyAccount
	function modifyacct($opts) {
		if (!isset($opts['user'])) {
			error_log("modifyacct requires that user is defined in the array passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('modifyacct', $opts);
	}

	// This API function allows you to edit a user's disk space quota.
	// $username = string, $quota = integer (in Megabytes)
	function editquota($username, $quota) {
		if (!isset($username) || !isset($quota)) {
			error_log("editquota requires that an username and quota are passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('editquota', array('user' => $username, 'quota' => $quota));
	}

	// This API function will generate a list of an account's attributes, such as it's IP address and partition.
	// $username = string
	function accountsummary($username) {
		if (!isset($username)) {
			error_log("accountsummary requires that an username is passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('accountsummary', array('user' => $username));
	}

	// Using this API function, you can prevent a user from accessing his or her cPanel account.
	// $username = string
	// Optional: $reason = string
	function suspendacct($username, $reason = null) {
		if (!isset($username)) {
			error_log("suspendacct requires that an username is passed to it");
			return FALSE;
		}
		if ($reason) {
			return $this->xmlapi_query('suspendacct', array('user' => $username, 'reason' => $reason ));
		}
		return $this->xmlapi_query('suspendacct', array('user' => $username));
	}

	// This function allows you to view a list of suspended accounts on your server.
	function listsuspended() {
		return $this->xmlapi_query('listsuspended');
	}

	// This API function allows you permanently remove an account from a server.
	// $username = string
	// Optional: $keepdns = boolean (1 or 2). 1 = yes, 2 = no (this is the default value).
	function removeacct($username, $keepdns = null) {
		if (!isset($username)) {
			error_log("removeacct requires that a username is passed to it");
			return FALSE;
		}
		if ($keepdns) {
			return $this->xmlapi_query('removeacct', array('user' => $username, 'keepdns' => $keepdns));
		}
		return $this->xmlapi_query('removeacct', array('user' => $username));
	}

	// Using this API function, you can allow a user to access his or her cPanel account after it has been suspended.
	// $username = string
	function unsuspendacct($username){
		if (!isset($username)) {
			error_log("unsuspendacct requires that a username is passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('unsuspendacct', array('user' => $username));
	}

	// This API function allows you to change the hosting plan associated with a cPanel account.
	// $username = string, $pkg = string
	function changepackage($username, $pkg) {
		if (!isset($username) || !isset($pkg)) {
			error_log("changepackage requires that username and pkg are passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('changepackage', array('user' => $username, 'pkg' => $pkg));
	}

	// Using this API function, you can find out what privileges you have within WHM.
	function myprivs() {
		return $this->xmlapi_query('myprivs');
	}

	// Function available in WHM 11.25 and later: domainuserdata
	// This function displays information about a given domain, 
	// including addon and subdomains, whether CGI aliasing is enabled, log locations, and other details.
	
	// Function available in WHM 11.25 and later: setsiteip
	// This function allows you to change the IP address associated with a website, or a user's account, hosted on your server.
	
	####
	# DNS Functions
	####

	// This API function lets you create a DNS zone.
	// $domain = string, $ip = string
	function adddns($domain, $ip) {
		if (!isset($domain) || !isset($ip)) {
			error_log("adddns require that domain, ip are passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('adddns', array('domain' => $domain, 'ip' => $ip));
	}

	// Function available in WHM 11.25 and later: addzonerecord
	// This API function allows you to add a zone record.
	
	// Function available in WHM 11.25 and later: editzonerecord
	// This function allows you to edit an existing zone record.
	
	// Function available in WHM 11.25 and later: getzonerecord
	// This function allows you to view DNS zone records associated with a given domain.
	
	// This API function lets you delete a DNS zone.
	// $domain = string
	function killdns($domain) {
		if (!isset($domain)) {
			error_log("killdns requires that domain is passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('killdns', array('domain' => $domain));
	}

	// This API function lists all domains and DNS zones on your server.
	function listzones() {
		return $this->xmlapi_query('listzones');
	}

	// This API function displays the DNS zone configuration for a specific domain.
	// $domain = string
	function dumpzone($domain) {
		if (!isset($domain)) {
			error_log("dumpzone requires that a domain is passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('dumpzone', array('domain' => $domain));
	}

	// This API function retrives the IP address of a registered nameserver.
	// $nameserver = string
	function lookupnsip($nameserver) {
		if (!isset($nameserver)) {
			error_log("lookupnsip requres that a nameserver is passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('lookupnsip', array('nameserver' => $nameserver));
	}

	// Function available in WHM 11.25 and later: removezonerecord
	// This function allows you to remove a zone record from the server.
	
	// Function available in WHM 11.25 and later: resetzone
	// This API function will reset a DNS zone to its default values.
	
	####
	# Package Functions
	####

	// This API function adds a hosting package to your server.
	// $pkg = array('name' => string). Optional variables can be found at:
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/AddPackage
	function addpkg($pkg) {
		if (!isset($pkg['name'])) {
			error_log("addpkg requires that name is defined in the array passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('addpkg', $pkg);
	}

	// This API function deletes a hosting package from your server.
	// $pkgname = string
	function killpkg($pkgname) {
		if(!isset($pkgname)) {
			error_log("killpkg requires that the package name is passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('killpkg', array('pkg' => $pkgname));
	}

	// This function lets you edit aspects of a hosting package.
	// $pkg = array('name' => string). Optional variables can be found at:
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/EditPackage
	function editpkg($pkg) {
		if (!$isset($pkg['name'])) {
			error_log("editpkg requires that name is defined in the array passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('editpkg', $pkg);
	}

	// This API function lets you view all hosting packages available to the user.
	function listpkgs() {
		return $this->xmlapi_query('listpkgs');
	}

	####
	# Reseller functions
	####

	// This function allows you to confer reseller status to a user's account.
	// $username = string
	// Optional: $makeowner = boolean (0 or 1). Default is 1 (yes).
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/AddResellerPrivileges
	function setupreseller($username, $makeowner = '1') {
		if (!isset($username)) {
			error_log("setupreseller requires that username is passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('setupreseller', array('user' => $username, 'makeowner' => $makeowner));
	}

	// This function allows you to create a new ACL list to use when setting up reseller accounts.
	// $acl = array('acllist' => string). Optional variables can be found at:
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/CreateResellerACLList
	function saveacllist($acl) {
		if (!isset($acl['acllist'])) {
			error_log("saveacllist requires that acllist is defined in the array passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('saveacllist', $acl);
	}

	// This function lists the saved reseller ACL lists on the server. 
	function listacls() {
		return $this->xmlapi_query('listacls');
	}

	// This function lists all resellers on the server.
	function listresellers() {
		return $this->xmlapi_query('listresellers');
	}

	// This function shows statistics for a specific reseller's accounts.
	// $username = string
	function resellerstats($username) {
		if (!isset($username)) {
			error_log("resellerstats requires that a username is passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('resellerstats', array('reseller' => $username));
	}	

	// This function removes reseller status from a user's account.
	// $username = string
	function unsetupreseller($username) {
		if (!isset($username)) {
			error_log("unsetupreseller requires that a username is passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('unsetupreseller', array('user' => $username));
	}

	// This function specifies the ACL for a reseller, or modifies specific ACL features for a reseller.
	// $acl = array('reseller' => string). Optional variables can be found at:
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/SetResellersACLList
	function setacls($acl) {
		if (!isset($acl['reseller'])) {
			error_log("setacls requires that reseller is defined in the array passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('setacls', $acl);
	}

	// This function allows you to terminate a reseller's account.
	// $username = string
	// WARNING!: This will irrevocably remove all the accounts owned by that reseller!
	function terminatereseller($username) {
		if (!isset($reseller)) {
			error_log("terminatereseller requires that username is passed to it");
			return FALSE;
		}
		$verify = 'I%20understand%20this%20will%20irrevocably%20remove%20all%20the%20accounts%20owned%20by%20the%20reseller%20' . $username;
		return $this->xmlapi_query('terminatereseller', array('reseller' => $username, 'terminatereseller' => '1', 'verify' => $verify));
	}

	// Function available in WHM 11.25 and later: setresellerips
	// This function lets you add IP addresses to a reseller's account.
	
	// Function available in WHM 11.25 and later: setresellerlimits
	// This function lets you set limits on the amount of bandwidth and disk space a reseller can use.

	// Function available in WHM 11.25 and later: setresellermainip
	// This function lets you assign a main, shared IP address to a reseller's account.
	
	// Function available in WHM 11.25 and later: setresellerpackagelimit
	// This function allows you to control which packages resellers are able to use.
	// It also lets you define the number of times a package can be used by a reseller.

	// Function available in WHM 11.25 and later: suspendreseller
	// This function lets you suspend a reseller, thereby preventing the reseller from accessing his or her account.
	
	// Function available in WHM 11.25 and later: unsuspendreseller
	// This function lets you unsuspend a reseller, thereby allowing the reseller to access his or her account.

	// Function available in WHM 11.25 and later: acctcounts
	// This function lists the number of accounts owned by each reseller on the server.
	
	// Function available in WHM 11.25 and later: setresellernameservers
	// This function allows you to define a reseller's nameservers.
	
	####
	# Server information
	####

	// This function displays the server's hostname.
	function gethostname() {
		return $this->xmlapi_query('gethostname');
	}

	// This function displays the version of cPanel/WHM running on the server.
	function version() {
		return $this->xmlapi_query('version');
	}

	// This function displays your server's load average.
	function loadavg() {
		return $this->xmlapi_query('loadavg');
	}

	// This function displays a list of the languages available on your server.
	function getlanglist() {
		return $this->xmlapi_query('getlanglist');
	}

	####
	# Server administration
	####

	// This function allows you to restart your server.
	// Optional: $force = boolean (0 or 1)
	// 1 � Initiates the forceful reboot
	// 0 � Initiates a graceful reboot (default).
	// Remember: A forceful reboot may result in data loss if processes are still running when the server restarts.
	function reboot($force = null) {
		if ($force) {
			return $this->xmlapi_query('reboot', array('force' => $force));
		}
		return $this->xmlapi_query('reboot');
	}
	
	// This function allows you to add an IP address to your server.
	// $ip = string, $netmask = string
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/AddIPAddress
	function addip($ip, $netmask) {
		if (!isset($ip) || !isset($netmask)) {
			error_log("addip requires that an IP address and Netmask are passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('addip', array('ip' => $ip, 'netmask' => $netmask));
	}

	// This function allows you to delete an IP address from your server.
	// $opts = array('ip' => string). Optional variables can be found at:
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/DeleteIPAddress
	function delip($opts) {
		if (!isset($opts['ip'])) {
			error_log("delip requires that an IP is defined in the array passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('delip', $opts);
	}

	// This function allows you to list IP addresses associated with your server.
	function listips() {
		return $this->xmlapi_query('listips');
	}

	// This function allows you to set the hostname for your server.
	// Note: This name must be different from your domain name.
	// $hostname = string
	function sethostname($hostname) {
		if (!isset($hostname)) {
			error_log("sethostname requires that hostname is passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('sethostname', array('hostname' => $hostname));
	}

	// This function allows you to set the resolvers your server will use.
	// $resv = array('nameserver1' => string [, 'nameserver2' => string, 'nameserver3' => string])
	// nameserver1 is required, the other two are optional
	function setresolvers($resv) {
		if (!isset($resv['nameserver1'])) {
			error_log("setresolvers requires that nameserver1 is defined in the array passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('setresolvers', $resv);
	}

	// This function will list bandwidth usage per account.
	// $opts = array(). Optional variables can be found at:
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/ShowBw
	function showbw($opts = null) {
		if (is_array($opts)) {
			return $this->xmlapi_query('showbw', $opts);
		}
		return $this->xmlapi_query('showbw');
	}

	// Non-volatile variables are used to save data on your server.
	// This function allows you to set a non-volatile variable's value.
	// $key = string, $value = string
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/NvSet
	function nvset($key, $value) {
		if (!isset($key) || !isset($value)) {
			error_log("nvset requires that key and value are passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('nvset', array('key' => $key, 'value' => $value));
	}
	
	// This function allows you to retrieve and view a non-volatile variable's value.
	// $key = string
	function nvget($key) {
		if (!isset($key)) {
			error_log("nvget requires that key is passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('nvget', array('key' => $key));
	}
	
	####
	# Service functions
	####

	// This function lets you restart a service, or daemon, on your server.
	// $service = string
	// Acceptable values: named, interchange, ftpd, httpd, imap, cppop, exim, mysql, postgresql, sshd or tomcat 
	function restartsrv($service) {
		if (!isset($service)) {
			error_log("restartsrv requires that service is passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('restartservice', array('service' => $service));
	}

	// This function tells you which services (daemons) are enabled, installed, and monitored on your server.
	function servicestatus() {
		return $this->xmlapi_query('servicestatus');
	}

	// Function available in WHM 11.25 and later: configureservice
	// This function allows you to enable or disable a service,
	// and enable or disable monitoring of that service, as in the WHM Service Manager.
	
	####
	# SSL functions
	####

	// This function displays an SSL certificate, CA bundle, and private key for a specified domain,
	// or it can display a CA bundle and private key for a specified SSL certificate.
	// $args = array(['domain' => string] or ['crtdata' => string]) one is required, not both!
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/FetchSSL
	function fetchsslinfo($args) {
		if (!isset($args['domain']) && !isset($args['crtdata'])) {
			error_log("fetchsslinfo requires domain OR crtdata is passed to it");
		}
		if (isset($args['crtdata'])) {
			// crtdata must be URL-encoded!
			$args['crtdata'] = urlencode($args['crtdata']);
		}
		return $this->xmlapi_query('fetchsslinfo', $args);
	}
	
	// This function generates an SSL certificate.
	// $args = array('xemail' => string, 'host' => string, 'country' => string, 'state' => string, 'city' => string, 'co' => string, 'cod' => string, 'email' => string, 'pass' => string)
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/GenerateSSL
	function generatessl($args) {
		if (!isset($args['xemail']) || !isset($args['host']) || !isset($args['country']) || !isset($args['state']) || !isset($args['city']) || !isset($args['co']) || !isset($args['cod']) || !isset($args['email']) || !isset($args['pass'])) {
			error_log("generatessl requires that xemail, host, country, state, city, co, cod, email and pass are defined in the array passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('generatessl', $args);
	}
	
	// This function lets you install an SSL certificate onto the server.
	// $args = array('user' => string, 'domain' => string, 'cert' => string, 'key' => string, 'cab' => string, 'ip' => string)
	// Info: http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/InstallSSL
	function installssl($args) {
		if (!isset($args['user']) || !isset($args['domain']) || !isset($args['cert']) || !isset($args['key']) || !isset($args['cab']) || !isset($args['ip'])) {
			error_log("installssl requires that user, domain, cert, key, cab and ip are defined in the array passed to it");
			return FALSE;
		}
		return $this->xmlapi_query('installssl', $args);
	}
	
	// This function lists all domains on the server which have SSL certificates installed.
	function listcrts() {
		return $this->xmlapi_query('listcrts');
	}

	####
	# cPanel API1 functions
	# Note: A cPanel account username is required
	# Some cPanel features must be enabled to be able to use some function (f.e. park, unpark)
	####

	// This API1 function adds a emailaccount for a specific user.
	// $args = array('email_username', 'email_password', 'email_domain')
	function addpop($username, $args) {
		if (!isset($username) || !isset($args)) {
			error_log("addpop requires that a user and args are passed to it");
			return FALSE;
		}
		if (is_array($args) && (sizeof($args) < 3)) {
			error_log("addpop requires that args at least contains an email_username, email_password and email_domain");
			return FALSE;
		}
		return $this->api1_query($username, 'Email', 'addpop', $args);
	}

	####
	# cPanel API2 functions
	# Note: A cPanel account username is required
	# Some cPanel features must be enabled to be able to use some function
	####

	// This API2 function allows you to view the diskusage of a emailaccount.
	// $args = array('domain' => $email_domain, 'login' => $email_username)
	function getdiskusage($username, $args) {
		if (!isset($username) || !isset($args)) {
			error_log("getdiskusage requires that a username and args are passed to it");
			return FALSE;
		}
		if (is_array($args) && (!isset($args['domain']) || !isset($args['login']))) {
			error_log("getdiskusage requires that args at least contains an email_domain and email_username");
			return FALSE;
		}
		return $this->api2_query($username, 'Email', 'getdiskusage', $args);
	}

	// This API2 function allows you to list ftp-users associated with a cPanel account.
	function listftpwithdisk($username) {
		if (!isset($username)) {
			error_log("listftpwithdisk requires that user is passed to it");
			return FALSE;
		}
		return $this->api2_query($username, 'Ftp', 'listftpwithdisk');
	}

	// This API function displays a list of all parked domains for a specific user.
	function listparkeddomains($username) {
		if (!isset($username)) {
			error_log("listparkeddomains requires that a user is passed to it");
			return FALSE;
		}
		return $this->api2_query($username, 'Park', 'listparkeddomains');
	}
}
?>