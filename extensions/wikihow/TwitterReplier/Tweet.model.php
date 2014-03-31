<?php

class TweetModel
{

	private $dbw, $dbr;

	const TWEETS_TABLE = 'twitterreplier_tweets';
	const REPLY_TABLE = 'twitterreplier_reply_tweets';
	const REPLY_LOG_TABLE = 'twitterreplier_reply_log';
	const TIME_LOCK = '10 minutes ago';
	const MAX_TWEETS = '20';
	
	function __construct()
	{
		if ( empty( $this->dbw ) ) {
			$this->dbw = wfGetDB( DB_MASTER );
		}

		if ( empty( $this->dbr ) ) {
			$this->dbr = wfGetDB( DB_SLAVE );
		}
	}

	function getTweets( $lastTweetId = null )
	{
		$sql = 'SELECT ' . self::TWEETS_TABLE . '.id,
				' . self::TWEETS_TABLE . '.tweet_id,
				' . self::TWEETS_TABLE . '.tweet,
				' . self::TWEETS_TABLE . '.twitter_user_id,
				' . self::TWEETS_TABLE . '.search_category_id,
				' . self::TWEETS_TABLE . '.reply_status,
				' . self::TWEETS_TABLE . '.response_object,
				' . self::TWEETS_TABLE . '.twitter_created_on,
				' . self::TWEETS_TABLE . '.created_on,
				' . self::TWEETS_TABLE . '.updated_on 
				FROM `twitterreplier_tweets`
				LEFT JOIN ' . self::REPLY_TABLE . ' ON ' . self::REPLY_TABLE . '.in_reply_to_tweet_id = ' . self::TWEETS_TABLE . '.tweet_id 
				WHERE  ' . self::REPLY_TABLE . '.in_reply_to_tweet_id IS NULL
				AND ( ' . self::TWEETS_TABLE . '.reply_status = 0 OR ' . self::TWEETS_TABLE . '.updated_on < ' .  $this->dbr->addQuotes( date( "Y-m-d H:i:s", strtotime( self::TIME_LOCK ) ) ) . ')';
		
		if( !empty( $lastTweetId ) ) {
			$sql .= ' AND tweet_id > ' . $this->dbr->addQuotes( $lastTweetId );
		}
		
		$sql .= ' ORDER BY twitter_created_on DESC';
		$sql .= ' LIMIT ' . self::MAX_TWEETS;
		$res = $this->dbr->query( $sql, __METHOD__ );
		
		while ( $row = $res->fetchRow() ) {
			$tweets[] = $row;
		}

		$this->dbr->freeResult( $res );

		return $tweets;
	}

	function insertTweet( $tweet )
	{
		$resp = false;
		if ( !empty( $tweet ) && is_object( $tweet ) ) {

			$field_values['tweet_id'] = $tweet->id_str;
			//$field_values['tweet'] = strip_tags( $tweet->text );
			$field_values['tweet'] = $tweet->text;
			$field_values['twitter_user_id'] = $tweet->from_user_id_str;
			$field_values['search_category_id'] = '1';
			$field_values['reply_status'] = '0';
			$field_values['response_object'] = base64_encode( json_encode( $tweet ) );
			$field_values['twitter_created_on'] = date( "Y-m-d H:i:s", strtotime( $tweet->created_at ) );
			$field_values['created_on'] = date( "Y-m-d H:i:s" );
			$field_values['updated_on'] = date( "Y-m-d H:i:s" );

			try {
				$resp = $this->dbw->insert( self::TWEETS_TABLE, $field_values, __METHOD__, array( 'IGNORE' ) );

				// check if we actually inserted it
				if ( $resp ) {
					$affectedRows = $this->dbw->affectedRows();
					$resp = $affectedRows > 0 ? true : false;
				}
			}
			catch ( Exception $e ) {
				// is there some sort of handler?
				echo $e->getMessage();
			}
		}
		else {
			echo 'invalid tweet';
		}

		return $resp;
	}
	
	function insertInboxQTweet( $tweet )
	{
		$resp = false;
		
		if ( !empty( $tweet ) && is_object( $tweet ) ) {
			// remodel tweet object so template doesn't need to change
			$tweet->profile_image_url = $tweet->profileImageUrl;
			$tweet->from_user = $tweet->profileName;
			
			$field_values['tweet_id'] = $tweet->sourceId;
			$field_values['tweet'] = $tweet->text;
			$field_values['twitter_user_id'] = '';
			$field_values['search_category_id'] = $tweet->search_category_id;
			$field_values['reply_status'] = '0';
			$field_values['response_object'] = base64_encode( json_encode( $tweet ) );
			$field_values['twitter_created_on'] = date( "Y-m-d H:i:s", $tweet->timestamp );
			$field_values['created_on'] = date( "Y-m-d H:i:s" );
			$field_values['updated_on'] = date( "Y-m-d H:i:s" );
			
			try {
				$resp = $this->dbw->insert( self::TWEETS_TABLE, $field_values, __METHOD__, array( 'IGNORE' ) );

				// check if we actually inserted it
				if ( $resp ) {
					$affectedRows = $this->dbw->affectedRows();
					$resp = $affectedRows > 0 ? true : false;
				}
			}
			catch ( Exception $e ) {
				// is there some sort of handler?
				echo $e->getMessage();
				//el( $e->getMessage() );
			}
		}
		else {
			echo 'invalid tweet';
		}

		return $resp;
	}
	
	function repliedByWho( $replyStatusId )
	{
		$twitterUserId = $this->dbr->selectField( self::REPLY_TABLE,
			'twitter_user_id',
			array( 'in_reply_to_tweet_id' => $replyStatusId ),
			__METHOD__ );
		return $twitterUserId;
	}

	function insertReplyTweet( $tweet, $replyStatusId )
	{
		global $wgUser;
		
		$resp = false;
		$whUserId = $wgUser->getId();
		
		if ( !empty( $tweet ) && is_array( $tweet ) ) {
			$field_values['twitter_user_id'] = !empty( $tweet['user']['id_str'] ) ? $tweet['user']['id_str'] : '';
			//$field_values['in_reply_to_tweet_id'] = !empty( $tweet['in_reply_to_status_id_str'] ) ? $tweet['in_reply_to_status_id_str'] : '';
			$field_values['in_reply_to_tweet_id'] = $replyStatusId;
			$field_values['reply_tweet_id'] = !empty( $tweet['id_str'] ) ? $tweet['id_str'] : '';
			$field_values['reply_tweet'] = $tweet['text'];
			$field_values['wikihow_user_id'] = !empty( $whUserId ) ? $whUserId : '';
			$field_values['created_on'] = date( "Y-m-d H:i:s" );
			$field_values['updated_on'] = date( "Y-m-d H:i:s" );
			
			try {
				$resp = $this->dbw->insert( self::REPLY_TABLE, $field_values, __METHOD__ );
				// check if we actually inserted it
				if ( $resp ) {
					$affectedRows = $this->dbw->affectedRows();
					$resp = $affected > 0 ? true : false;
				}
			}
			catch ( Excepion $e ) {
				echo $e->getMessage();
			}
		}
		return $resp;
	}

/*
 * db schema:
CREATE TABLE twitterreplier_reply_log (
	trrl_added TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	trrl_reply_id VARCHAR(255) NOT NULL,
	trrl_reply_handle VARCHAR(255) NOT NULL,
	trrl_wh_user VARCHAR(255) NOT NULL DEFAULT 0,
	trrl_orig_id VARCHAR(255) NOT NULL,
	trrl_orig_handle VARCHAR(255) NOT NULL,
	trrl_reply_tweet TEXT,
	trrl_url VARCHAR(255) NOT NULL,
	trrl_orig_tweet TEXT,
	index (trrl_added)
);
 */
	function insertReplyLog( $replyTweet, $response, $origAuthor, $origTweet )
	{
		global $wgUser;
		$username = '';
		if ($wgUser) {
			$username = $wgUser->getName();
		}

		$replyId = $response['id_str'];
		if (empty($replyId)) $replyId = '';
		$fromTwitter = $response['user']['screen_name'];
		if (empty($fromTwitter)) $fromTwitter = '';

		$url = 'http://twitter.com/' . ($fromTwitter ? $fromTwitter : 'x') . '/status/' . $replyId;

		$field_values = array(
			'trrl_reply_id' => $replyId,
			'trrl_reply_handle' => $fromTwitter,
			'trrl_wh_user' => $username,
			'trrl_orig_id' => $replyTweet['in_reply_to_status_id'],
			'trrl_orig_handle' => $origAuthor,
			'trrl_reply_tweet' => $replyTweet['status'],
			'trrl_url' => $url,
			'trrl_orig_tweet' => strip_tags( $origTweet ),
		);
		$this->dbw->insert( self::REPLY_LOG_TABLE, $field_values, __METHOD__ );
	}

	function lockTweet( $tweetId, $userIdentifier )
	{
		$resp = false;

		if ( !empty( $tweetId ) ) {
			/*
			$field_values['reply_status'] = '1';
			$field_values['locked_by'] = $userIdentifier;
			$field_values['updated_on'] = date( "Y-m-d H:i:s" );

			$cond = array( );
			$cond['reply_status'] = '0';
			$cond['tweet_id'] = $tweetId;
			
			// or
			

			try {
				$this->dbw->update( self::TWEETS_TABLE, $field_values, $cond );
				$affectedRows = $this->dbw->affectedRows();

				if ( $affectedRows > 0 ) {
					$resp = true;
				}
			}
			catch ( Exception $e ) {
				// TODO: is there a execption hanlder
				echo $e->getMessage();
			}
			*/
			$sql = "UPDATE " . self::TWEETS_TABLE . " 
					LEFT JOIN " . self::REPLY_TABLE . " ON " . self::REPLY_TABLE . ".in_reply_to_tweet_id = " . self::TWEETS_TABLE . ".tweet_id
					SET " . self::TWEETS_TABLE . ".reply_status = " . self::TWEETS_TABLE . ".reply_status + 1, 
					" . self::TWEETS_TABLE . ".locked_by =  " . $this->dbw->addQuotes( $userIdentifier ) . ",
					" . self::TWEETS_TABLE . ".updated_on = " . $this->dbw->addQuotes( date( "Y-m-d H:i:s" ) ) . "
					WHERE tweet_id = " . $this->dbw->addQuotes( $tweetId ) . "
					AND " . self::REPLY_TABLE . ".in_reply_to_tweet_id IS NULL
					AND (	" . self::TWEETS_TABLE . ".reply_status = 0
							OR " . self::TWEETS_TABLE . ".locked_by = " . $this->dbw->addQuotes( $userIdentifier ) . "
							OR " . self::TWEETS_TABLE . ".updated_on < " . $this->dbw->addQuotes( date( "Y-m-d H:i:s", strtotime( self::TIME_LOCK ) ) ) . "
					)";
			
			//el( $sql );
			try {
				$this->dbw->query( $sql, __METHOD__ );
				$affectedRows = $this->dbw->affectedRows();
				
				if( $affectedRows > 0 ) {
					$resp = true;
				}

			}
			catch( Exception $e ) {
				echo $e->getMessage();
			}
		}
		
		return $resp;
	}

	function unlockTweet( $tweetId, $userIdentifier )
	{
		$resp = false;

		if ( !empty( $tweetId ) && !empty( $userIdentifier ) ) {
			$field_values['reply_status'] = '0';
			$field_values['locked_by'] = '';
			$field_values['updated_on'] = date( "Y-m-d H:i:s" );

			$cond = array( );
			//$cond['reply_status'] = '1';
			$cond['tweet_id'] = $tweetId;
			$cond['locked_by'] = $userIdentifier;

			$this->dbw->update( self::TWEETS_TABLE, $field_values, $cond, __METHOD__ );

			$affectedRows = $this->dbw->affectedRows();
			
			if ( $affectedRows > 0 ) {
				$resp = true;
			}
		}

		return $resp;
	}
}
