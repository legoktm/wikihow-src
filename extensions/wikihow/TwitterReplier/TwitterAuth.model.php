<?php
/**
 * @property Database $dbw
 * @property Database $dbr
 */
class TwitterAuthModel
{

	private $dbw, $dbr;

	const COOKIE_TABLE = 'twitterreplier_cookie';
	const OAUTH_TABLE = 'twitterreplier_oauth';

	function __construct()
	{
		if ( empty( $this->dbw ) ) {
			$this->dbw = wfGetDB( DB_MASTER );
		}

		if ( empty( $this->dbr ) ) {
			$this->dbr = wfGetDB( DB_SLAVE );
		}
	}

	public function isValidHash( $hash )
	{
		$resp = false;
		$fields = array( );
		$fields[] = 'hash';

		$conds = array( );
		$conds['hash'] = $hash;

		try {
			$resp = $this->dbr->selectField( self::COOKIE_TABLE, $fields, $conds );
		}
		catch ( Exception $e ) {
			echo $e->getMessage();
			echo $e->getLine();
		}

		return $resp;
	}

	public function getHash()
	{
		return !empty( $_COOKIE[TRCOOKIE] ) ? $_COOKIE[TRCOOKIE] : false;
	}

	public function getUserToken( $userId, $userIdType = 'twitter_user_id' )
	{
		if( empty( $userId ) ) {
			return false;
		}
		
		$token = false;

		$fields = array( );
		$fields[] = 'token';

		switch ( $userIdType ) {
			case "twitter_user_id":
			case "wikihow_user_id":
				$where = array( );
				$where[$userIdType] = $userId;
				break;
			default:
				return false;
		}

		try {
			$token = $this->dbr->selectField( self::OAUTH_TABLE, $fields, $where );
		}
		catch ( Exception $e ) {
			echo $e->getMessage();
			echo $e->getLine();
		}

		return $token;
	}

	public function getUserSecret( $userId, $userIdType = 'twitter_user_id' )
	{
		if( empty( $userId ) ) {
			return false;
		}
		
		$secret = false;

		$fields = array( );
		$fields[] = 'secret';

		switch ( $userIdType ) {
			case "twitter_user_id":
			case "wikihow_user_id":
				$where = array( );
				$where[$userIdType] = $userId;
				break;
			default:
				return false;
		}


		try {
			$secret = $this->dbr->selectField( self::OAUTH_TABLE, $fields, $where );
		}
		catch ( Exception $e ) {
			echo $e->getMessage();
			echo $e->getLine();
		}

		return $secret;
	}

	public function getUserTwitterIdByHash( $hash )
	{
		if( !empty( $hash ) && strlen( $hash ) > 0 ) {
			$twitterUserId = false;
			$fields = array( );
			$fields[] = 'twitter_user_id';

			$where = array( );
			$where['hash'] = $hash;

			try {
				$twitterUserId = $this->dbw->selectField( self::COOKIE_TABLE, $fields, $where );
			}
			catch ( Exception $e ) {
				echo $e->getMessage();
				echo $e->getLine();
			}
		}
		else {
			
		}

		return $twitterUserId;
	}

	public function removeTwitterIdByWHUserId( $whUserId )
	{
		$this->dbw->delete( self::OAUTH_TABLE, array( 'wikihow_user_id' => $whUserId ), __METHOD__ );
		return $this->dbw->affectedRows() > 0;
	}

	public function getUserTwitterIdByWHUserId( $whUserId )
	{
		
		/* TODO: I think the joins option is added in a later versio of MW
		  
		  $fields = array();
		  $fields[] = 'twitter_user_id';

		  $where = array();
		  $where['wikihow_user_id'] = $whUserId;

		  $joins = array();
		  $joins[self::OAUTH_TABLE] = array( 'LEFT JOIN' => self::OAUTH_TABLE . '.twitter_user_id = ' . self::COOKIE_TABLE . '.twitter_user_id' );


		  try {
		  $twitterUserId = $this->dbr->selectField( self::COOKIE_TABLE, $fields, $where, null, null, $joins );
		  el( $this->dbr->lastQuery(), __FUNCTION__ );
		  }
		  catch ( Exception $e ) {
		  echo $e->getMessage();
		  }
		 */
		$twitterUserId = false;
		
		if( !empty( $whUserId ) && $whUserid > 0 ) {
			$sql = "SELECT " . self::COOKIE_TABLE . ".twitter_user_id 
					FROM " . self::OAUTH_TABLE . " 
					LEFT JOIN " . self::COOKIE_TABLE . " ON " . self::COOKIE_TABLE . ".twitter_user_id = " . self::OAUTH_TABLE . ".twitter_user_id
					WHERE wikihow_user_id = " . $this->dbr->addQuotes( $whUserId );

			try {
				$results = $this->dbr->query( $sql );

				$row = $this->dbr->fetchRow( $results );

				return $row['twitter_user_id'];
			}
			catch ( Exception $e ) {
				//errorLog( $e->getMessage(), $e->getLine(), $e->getFile() );
			}
		}
		else {
			
		}

		return $twitterUserId;
	}

	public function saveAccessToken( $token, $secret, $twitterUserId, $whUserId )
	{
		// TODO: add two way encryption
		$sql = "INSERT IGNORE INTO " . self::OAUTH_TABLE . "
			 (id, token, secret, twitter_user_id, wikihow_user_id, created_on, updated_on )
			 VALUES ( NULL, " . $this->dbw->addQuotes( $token ) . ", " . $this->dbw->addQuotes( $secret ) . ", " . $this->dbw->addQuotes( $twitterUserId ) . ", " . $this->dbw->addQUotes( $whUserId ) . ", " . $this->dbw->addQuotes( date( "Y-m-d H:i:s" ) ) . ", " . $this->dbw->addQuotes( date( "Y-m-d H:i:s" ) ) . ")
			 ON DUPLICATE KEY UPDATE token=VALUES(token), secret=VALUES(secret), updated_on=VALUES(updated_on)";

		if ( $whUserId > 0 ) {
			$sql .= ', wikihow_user_id=VALUES(wikihow_user_id)';
		}

		try {
			$this->dbw->query( $sql );
		}
		catch ( Exception $e ) {
			echo $e->getMessage();
			echo $e->getLine();
		}
	}

	public function saveHash( $hash, $twitterUserId )
	{
		$createdOn = date( "Y-m-d H:i:s" );
		$updatedOn = date( "Y-m-d H:i:s" );

		$sql = "INSERT IGNORE INTO " . self::COOKIE_TABLE . " ( id, twitter_user_id, hash, created_on, updated_on )
			VALUES ( NULL, " . $this->dbw->addQuotes( $twitterUserId ) . ", " . $this->dbw->addQuotes( $hash ) . ", " . $this->dbw->addQuotes( $createdOn ) . ", " . $this->dbw->addQuotes( $updatedOn ) . " )
			ON DUPLICATE KEY UPDATE updated_on=VALUES( updated_on ), hash=VALUES(hash)";
		
		try {
			$this->dbw->query( $sql );
		}
		catch ( Exception $e ) {

		}
	}

	public function validateUserTokens( $token, $secret )
	{
		$twitterObj = new EpiTwitter( WH_TWITTER_TIF_CONSUMER_KEY, WH_TWITTER_TIF_CONSUMER_SECRET, $token, $secret );
		$twitterInfo = $twitterObj->get_accountVerify_credentials();
		$twitterInfo->response;

		//TODO: finish off this method
	}

	public function updateWHUserId( $twitterUserId, $whUserId )
	{
		if ( !empty( $twitterUserId ) && $twitterUserId > 0 && is_numeric( $whUserId ) && $whUserId > 0 ) {
			$field_values = array( );
			$field_values['wikihow_user_id'] = $whUserId;

			$cond = array( );
			$cond['wikihow_user_id'] = '0';
			$cond['twitter_user_id'] = $twitterUserId;

			try {
				$this->dbw->update( self::OAUTH_TABLE, $field_values, $cond );
			}
			catch ( Exception $e ) {
				echo $e->getMessage();
				echo $e->getLine();
			}
		}
	}

	public function getUserAvatar()
	{
		$avatar = false;
		$twitterId = $this->getTwitterId();

		$token = $this->getUserToken( $twitterId );
		$secret = $this->getUserSecret( $twitterId );

		try {
			$twitterObj = new EpiTwitter( WH_TWITTER_TIF_CONSUMER_KEY, WH_TWITTER_TIF_CONSUMER_SECRET, $token, $secret );
			$twitterInfo = $twitterObj->get_accountVerify_credentials();
			$avatar = $twitterInfo->profile_image_url;
		}
		catch ( Exception $e ) {
			//echo $e->getMessage();
			
		}

		return $avatar;
	}

	public function getUserScreenName()
	{
		global $wgUser;

		$screenName = false;
		$twitterId = $this->getTwitterId();
		
		if ( !empty( $twitterId ) ) {
			$token = $this->getUserToken( $twitterId );
			$secret = $this->getUserSecret( $twitterId );

			try {
				$twitterObj = new EpiTwitter( WH_TWITTER_TIF_CONSUMER_KEY, WH_TWITTER_TIF_CONSUMER_SECRET, $token, $secret );
				$twitterInfo = $twitterObj->get_accountVerify_credentials();
				
				$screenName = $twitterInfo->screen_name;
			}
			catch ( Exception $e ) {
				//errorLog( $e->getMessage(), __LINE__, __FILE__ );
			}
		}
		
		return $screenName;
	}
	
	public function unlinkTwitterAccount()
	{
		$twitterId = $this->getTwitterId();
		
		// remove cookie
		$this->deleteTwitterCookie();
		
		// remove account from oauth table
		return $this->deleteAccount( $twitterId );
	}
	
	public function getTwitterId()
	{
		global $wgUser;
		
		$twitterId = false;
		
		$whUserId = $wgUser->getId();
		
		if( !empty( $whUserId ) && $whUserId > 0 ) {
			$twitterId = $this->getUserTwitterIdByWHUserId( $whUserId );
		}
		
		if( empty( $twitterId ) || !$twitterId ) {
			$hash = $this->getHash();
		
			if ( !empty( $hash ) && strlen( $hash ) > 0 ) {
				$twitterId = $this->getUserTwitterIdByHash( $hash );
			}
			else if( !empty( $_SESSION['hash'] ) ) {
				$twitterId = $this->getUserTwitterIdByHash( $_SESSION['hash'] );
			}
		}
		
		return $twitterId;
	}
	
	public function generateTwitterCookie( $twitterUserId )
	{
		global $wgCookieDomain, $wgCookiePath;

		// TODO: is there someother unique id generator?
		$hash = sha1( $this->getUniqueId( 128 ) );

		if ( is_numeric( $twitterUserId ) && $twitterUserId > 0 ) {
			if ( !$this->isValidHash( $hash ) ) {
				// sets cookie to be valid for 7 days
				if ( !setcookie( TRCOOKIE, $hash, time() + 604800, $wgCookiePath, $wgCookieDomain, false, true ) ) {
					//el( 'Unable to set cookie: ' . $_SERVER['HTTP_HOST'] );
				}
				else {
					$_SESSION['hash'] = $hash;
				}

				$this->saveHash( $hash, $twitterUserId );
				
				return $hash;
			}
			else {
				return $this->generateTwitterCookie( $twitterUserId );
			}
		}
		else {
			throw new InvalidArgumentException( 'twitterUserId must be numeric: ' . $twitterUserId );
		}
	}
	
	public function deleteTwitterCookie()
	{
		global $wgCookieDomain, $wgCookiePath;
		
		//setcookie( TRCOOKIE, '', time() - 604800, $wgCookiePath, $wgCookieDomain, false, true );
		setcookie( TRCOOKIE, '', time() - 604800 );
		setcookie( TRCOOKIE, '', time() - 604800, '/' );
	}
	
	private function deleteAccount( $twitterId )
	{
		$cond = array();
		$cond['twitter_user_id'] = $twitterId;
		$result = true;
		
		$result = $this->dbw->delete( self::COOKIE_TABLE, $cond );
		
		$result = $this->dbw->delete( self::OAUTH_TABLE, $cond );
		
		return $result;
	}
	
	/**
	 * Generates a unique random number
	 *
	 * @param int $length length of unique number
	 * @param string $pool additional characters to use in pool of characters
	 * @return string
	 */
	function getUniqueId( $length=20, $pool="" )
	{
		// set pool of possible char
		if ( $pool == "" ) {
			$pool = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
			$pool .= "abcdefghijklmnopqrstuvwxyz";
			// $pool .= "0123456789";
		}
		$poolLen = strlen( $pool );

		mt_srand( (double)microtime() * 1000000 );

		$unique_id = "";

		for ( $index = 0; $index < $length; $index++ ) {
			$unique_id .= substr( $pool, mt_rand() % $poolLen, 1 );
		}

		return $unique_id;
	}
}
