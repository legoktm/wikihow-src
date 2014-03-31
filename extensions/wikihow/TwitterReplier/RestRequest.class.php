<?php
class RestRequest
{

	protected $url;
	protected $verb;
	protected $requestBody;
	protected $requestLength;
	protected $username;
	protected $password;
	protected $acceptType;
	protected $responseBody;
	protected $responseInfo;

	public function __construct( $url = null, $verb = 'GET', $requestBody = null )
	{
		$this->url = $url;
		$this->verb = $verb;
		$this->requestBody = $requestBody;
		$this->requestLength = 0;
		$this->username = null;
		$this->password = null;
		$this->acceptType = 'application/json';
		$this->responseBody = null;
		$this->responseInfo = null;

		if ( $this->requestBody !== null ) {
			switch ( $verb ) {
				case "GET":
					$this->buildGetUrl();
					break;
				case "POST":
					$this->buildPostBody();
					break;
			}
		}
	}

	public function flush()
	{
		$this->requestBody = null;
		$this->requestLength = 0;
		$this->verb = 'GET';
		$this->responseBody = null;
		$this->responseInfo = null;
	}

	public function execute()
	{
		$ch = curl_init();
		$this->setAuth( $ch );

		try {
			switch ( strtoupper( $this->verb ) ) {
				case 'GET':
					$this->executeGet( $ch );
					break;
				case 'POST':
					$this->executePost( $ch );
					break;
				case 'PUT':
					$this->executePut( $ch );
					break;
				case 'DELETE':
					$this->executeDelete( $ch );
					break;
				default:
					throw new InvalidArgumentException( 'Current verb (' . $this->verb . ') is an invalid REST verb.' );
			}
		}
		catch ( InvalidArgumentException $e ) {
			curl_close( $ch );
			throw $e;
		}
		catch ( Exception $e ) {
			curl_close( $ch );
			throw $e;
		}
	}

	public function buildPostBody( $data = null )
	{
		$data = $data !== null ? $data : $this->requestBody;

		if ( !is_array( $data ) ) {
			throw new InvalidArgumentException( 'Invalid data, array expected' );
		}

		$data = http_build_query( $data, '', '&' );

		$this->requestBody = $data;
	}

	public function buildGetUrl( $data = null )
	{

		$data = $data !== null ? $data : $this->requestBody;

		if ( !is_array( $data ) ) {
			throw new InvalidArgumentException( 'Invalid data, array expected' );
		}

		$data = http_build_query( $data, '', '&' );
		$data = str_replace( '+', '%20', $data );
		$this->requestBody = $data;
		$this->url = $this->url . '?' . $this->requestBody;
	}

	public function displayResponse()
	{
		return $this->responseBody;
	}

	/*public function save_tweets()
	{
		try {
			$mysqli = new mysqli( 'localhost', 'twitter', 'twitter', 'twitter' );
		}
		catch ( Exception $e ) {
			echo $e->getMessage();
		}
		
		$decodedBody = self::decode_response_body();

		foreach ( $decodedBody->results as $tweet ) {
			$sql = "INSERT IGNORE INTO wheatly_tweets ( id, tweet_id, tweet, twitter_user_id, search_category_id, reply_status, response_object, created_on, updated_on ) 
						VALUES ( null, '" .  $tweet->id_str  . "',  '" . mysqli_real_escape_string( $mysqli, $tweet->text ) . "', '" . $tweet->from_user_id_str . "', '1', '0', '" . base64_encode( json_encode( $tweet ) ) . "', '" . date( "Y-m-d H:i:s" ) . "', '" . date( "Y-m-d H:i:s" ) . "')";
			
			echo $sql . '<br /><br />';
			
			if( !$mysqli->query( $sql ) ){
				echo $mysqli->error . '<br />';
				exit;
			}
		}
	}

	private function decode_response_body()
	{
		return json_decode( $this->responseBody );
	}*/

	protected function doExecute( &$curlHandle )
	{
		$this->setCurlOpts( $curlHandle );
		$this->responseBody = curl_exec( $curlHandle );
		$this->responseInfo = curl_getinfo( $curlHandle );

		curl_close( $curlHandle );
	}

	protected function executeGet( $ch )
	{
		$this->doExecute( $ch );
	}

	protected function executePost( $ch )
	{
		
	}

	protected function executePut( $ch )
	{
		
	}

	protected function executeDelete( $ch )
	{
		
	}

	protected function setCurlOpts( &$curlHandle )
	{
		curl_setopt( $curlHandle, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $curlHandle, CURLOPT_URL, $this->url );
		curl_setopt( $curlHandle, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curlHandle, CURLOPT_HTTPHEADER, array( 'Accept: ' . $this->acceptType ) );
		curl_setopt( $curlHandle, CURLOPT_SSL_VERIFYPEER, false );
	}

	protected function setAuth( &$curlHandle )
	{
		
	}

}
