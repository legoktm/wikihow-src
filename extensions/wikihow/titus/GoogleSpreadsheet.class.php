<?php

class GoogleSpreadsheet 
{
	// Authentication code
	private $auth;

	public function login($email, $password) {
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, "https://www.google.com/accounts/ClientLogin");
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$data = array('accountType' => 'GOOGLE', 'Email' => $email, 'Passwd' => $password, 'service' => 'wise');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		
		$res=curl_exec($ch);
		curl_close($ch);
		$auths=preg_split("/[\r\n]+/", $res);
		$autha=array();
		foreach($auths as $auth) {
			$kv = preg_split("/=/", $auth);
			$autha[ $kv[0] ] = $kv[1];
		}
		if(empty($autha['Auth'])) {
			return false;
		}
		else {
			$this->_auth = $autha['Auth'];
		}
	}
	private function doRequest($url, $params=array()) {
		if(!$this->_auth) {
			return(false);	
		}
		$ch=curl_init();
		if($params) {
			$first=true;
			foreach($params as $k=>$v) {
				if($first) {
					$url .= "?";
					$first=false;
				}
				else {
					$url .= "&";	
				}
				$url .= $k . "=" . $v;	
			}
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/atom+xml", "Authorization: GoogleLogin auth=" . $this->_auth));
		$res=curl_exec($ch);
		return($res);
	}
	public function getSpreadsheets() {
		
		$res=$this->doRequest("https://spreadsheets.google.com/feeds/spreadsheets/private/full");
		$ret=array();
		$xml=simplexml_load_string($res);
		foreach($xml->entry as $e) {
			$ret[(string)$e->content]=(string)$e->id;
		}
		return($ret);
	}
	public function getWorksheets($url) {
		$res=$this->doRequest($url);
		$ret=array();
		$xml=simplexml_load_string($res);
		foreach($xml->entry as $e) {
			if(preg_match("https://spreadsheets.google.com/feeds/spreadsheets/private/full/(.+)",(string)$e->id,$matches )) {
				$ret[(string)$e->content]=$matches[1];	
			}
		}
		return($ret);
	}
	public function getCols($worksheet,$startCol,$endCol,$startRow=1) {
		$url="https://spreadsheets.google.com/feeds/cells/" . $worksheet . "/private/full";
		$res=$this->doRequest($url,array("min-row"=>$startRow,"min-col"=>$startCol,"max-col"=>$endCol));
		$n=0;
		$xml=simplexml_load_string($res);
		$row = array();
		$cols=array();
		foreach($xml->entry as $e) {
			if($n > ($endCol - $startCol)) {
				$cols[] = $row;
				$n=0;	
			}
			$row[$n] = (string)$e->content;
			$n++;
		}
		$cols[] = $row;
		return($cols);
	}
	
	public function getColsWithSpaces($worksheet,$startCol,$endCol,$startRow=1) {
		$url="https://spreadsheets.google.com/feeds/cells/" . $worksheet . "/private/full";
		$res=$this->doRequest($url,array("min-row"=>$startRow,"min-col"=>$startCol,"max-col"=>$endCol));
		$xml=simplexml_load_string($res);
		$row = array();
		$cols = array();
		$last_pos = 'A';
		foreach($xml->entry as $e) {
			$pos = strtoupper((string)$e->title);
			//new row?
			if (substr($pos,-1,1) !== substr($last_pos,-1,1) && $pos != 'A2') {
				$cols[] = $row;
				$row = array();	
			}
			else {
				//did we skip an empty cell?
				$diff = (ord(substr($pos,0,1)) - ord(substr($last_pos,0,1)));
				for ($i=1; $i < $diff; $i++) {
					$row[] = '';
				}
			}
			
			$row[] = (string)$e->content;
			$last_pos = $pos;
		}
		$cols[] = $row;
		return($cols);
	}
	
	public function getHeaders($worksheet) {
		$url="https://spreadsheets.google.com/feeds/cells/" . $worksheet . "/private/full";
		$res=$this->doRequest($url,array("max-row"=>1));
		$n=0;
		$xml=simplexml_load_string($res);
		$cols = array();
		foreach($xml->entry as $e) {
			$cols[] = trim((string)$e->content);
		}
		return($cols);
	}
}
