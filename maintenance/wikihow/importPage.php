<?php

// options:
// s - site http auth password
// p - user password

// script to import a page from the main wikihow into the local wikihow
require_once( __DIR__ . '/../Maintenance.php' );

class ImportPages extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'sitepass', 'site basic auth password', true, true, 's');
		$this->addOption( 'username', 'name of user', true, true, 'u');
		$this->addOption( 'userpass', 'password for user', true, true, 'p');
		$this->addOption( 'pagetitle', 'page title', true, true, 't');
		$this->addOption( 'fullhistory', 'get the full history', false, false, 'f');
		$this->addOption( 'templates', 'download included templates', false, false);
		$this->addOption( 'namespace', 'download into this namespace', false, true, 'n');
	}

	public function execute() {
		// get the input options
		$password = $this->getOption('userpass');
		$sitePass = $this->getOption('sitepass');
		$pageTitle = $this->getOption('pagetitle');
		$fullHistory = $this->getOption('fullhistory');
		$templates = $this->getOption('templates');
		$namespace = $this->getOption('namespace');
		$user = $this->getOption('username');

		$url = "http://diy:$sitePass@localhost";

		// log in to website:
		$ch = curl_init(); 
		$loginUrl = "$url/api.php?action=login";
		curl_setopt($ch, CURLOPT_URL, $loginUrl);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "format=xml&lgname=$user&lgpassword=$password");
		curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
		curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		// have to close curl so it saves cookies
		curl_close($ch);     
		//echo "$result\n";
		$token = shell_exec("grep wiki_sharedToken cookie.txt | awk '{ print $7 }' 2>&1");
		//echo "will confirm the token of: $token \n";
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $loginUrl);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "format=xml&lgname=$user&lgpassword=$password&lgtoken=$token");
		curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
		curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		curl_close($ch);     
		//echo "$result\n";

		//////////////// now that we are logged in request the edit token
		$ch = curl_init(); 
		$tokenUrl = "$url/api.php?action=tokens&format=php";
		curl_setopt($ch, CURLOPT_URL, $tokenUrl);
		curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
		curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		$output = curl_exec($ch); 
		//echo "$output\n";
		$data = unserialize($output);

		//echo "edit token is: ".$data['tokens']['edittoken']."\n";
		$editToken = urlencode($data['tokens']['edittoken']);

		$interwikiSource = "en";

		$importUrl = "$url/api.php?action=import";
		if ($fullHistory) {
			$importUrl = $importUrl."&fullhistory";
		}
		if ($templates) {
			$importUrl = $importUrl."&templates";
		}
		if ($namespace) {
			$importUrl = $importUrl."&namespace=$namespace";
		}
		//echo "importUrl is: $importUrl \n";
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $importUrl);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "format=xml&interwikisource=$interwikiSource&interwikipage=$pageTitle&token=$editToken");
		curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
		curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		$output = curl_exec($ch); 
		curl_close($ch);     
		echo "$output\n";
	}
}

$maintClass = "ImportPages";
require_once( RUN_MAINTENANCE_IF_MAIN );
