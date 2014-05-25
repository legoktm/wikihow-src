<?php

if ( !defined('MEDIAWIKI') ) exit;

class EchoWikiHowFormatter extends EchoBasicFormatter {
   /**
    * @param $event EchoEvent
    * @param $param
    * @param $message Message
    * @param $user User
    */
   protected function processParam( $event, $param, $message, $user ) {
       if ( $param === 'difflink' ) {
           $eventData = $event->getExtra();
           if ( !isset( $eventData['revid'] ) ) {
               $message->params(  );
               return;
           }
           $this->setTitleLink(
               $event,
               $message,
               array(
                   'class' => 'mw-echo-diff',
                   'linkText' => wfMessage( 'notification-thumbs-up-diff-link' )->text(),
                   'param' => array(
                       'oldid' => $eventData['revid'],
                       'diff' => 'prev',
                   )
               )
           );
       } else {
           parent::processParam( $event, $param, $message, $user );
       }
   }
}

class EchoWikihowHooks {

	function onBeforeCreateEchoEvent( &$notifications, &$notificationCategories, &$icons ) {
		/******************* THUMBS UP ***/
		$notificationCategories['thumbs-up'] = array(
			'priority' => 1,
			'tooltip' => 'echo-pref-tooltip-thumbs-up',
		);

		$notifications['thumbs-up'] = array(
			'primary-link' => array( 'message' => 'notification-link-text-respond-to-user', 'destination' => 'agent' ),
			'secondary-link' => array( 'message' => 'notification-link-text-view-edit', 'destination' => 'diff' ),
			'category' => 'thumbs-up',
			'group' => 'interactive',
			'formatter-class' => 'EchoWikiHowFormatter',
			'title-message' => 'notification-thumbs-up',
			'title-params' => array( 'agent', 'difflink', 'title' ),
			'payload' => array( 'summary' ),
			'icon' => 'thumbs-up',
		);

		$icons['thumbs-up'] = array(
			'path' => "wikihow/EchoWikihow/images/thumb_up_x2.png",
		);


		/******************* KUDOS/FAN MAIL ***/
		$notificationCategories['kudos'] = array(
			'priority' => 1,
			'tooltip' => 'echo-pref-tooltip-kudos',
		);

		$notifications['kudos'] = array(
			'primary-link' => array( 'message' => 'notification-link-text-respond-to-user', 'destination' => 'agent' ),
			'secondary-link' => array( 'message' => 'notification-link-text-view-edit', 'destination' => 'diff' ),
			'category' => 'kudos',
			'group' => 'interactive',
			'formatter-class' => 'EchoWikiHowFormatter',
			'title-message' => 'notification-kudos',
			'title-params' => array( 'agent', 'difflink', 'title' ),
			'payload' => array( 'summary' ),
			'icon' => 'kudos',
		);

		$icons['kudos'] = array(
			'path' => "wikihow/EchoWikihow/images/passion_x2.png",
		);

		//remap some mw msgs
		$notifications['edit-user-talk']['email-body-batch-message'] = 'notification-edit-talk-page-email-batch-body-wh';
		
		//remap some icons
		$notifications['welcome']['icon'] = 'star';

		//redefine icons we use here
		$icons['chat'] = array('path' => "wikihow/EchoWikihow/images/conversation_x2.png");
		$icons['linked'] = array('path' => "wikihow/EchoWikihow/images/crossed_x2.png");
		$icons['placeholder'] = array('path' => "wikihow/EchoWikihow/images/notification_x2.png");
		$icons['star'] = array('path' => "wikihow/EchoWikihow/images/featured_x2.png");

		//disable the revert notification option
		unset($notificationCategories['reverted']);
		unset($notifications['reverted']);

	   return true;
	}

	function onEchoGetDefaultNotifiedUsers( $event, &$users ) {
		switch ( $event->getType() ) {
			case 'kudos':
				$extra = $event->getExtra();
				if ( !$extra || !isset( $extra['kudoed-user-id'] ) ) {
					break;
				}
				$recipientId = $extra['kudoed-user-id'];
				$recipient = User::newFromId( $recipientId );
				$users[$recipientId] = $recipient;
				break;

			case 'thumbs-up':
				$extra = $event->getExtra();
				if ( !$extra || !isset( $extra['thumbed-user-id'] ) ) {
					break;
				}
				$recipientId = $extra['thumbed-user-id'];
				$recipient = User::newFromId( $recipientId );
				$users[$recipientId] = $recipient;
				break;

			case 'edit-user-talk':
				//do not notify about a talk page msg if it is also a thumbs up
				$extra = $event->getExtra();

				if ( $extra && isset( $extra['content'] ) ) {
					if (strpos($extra['content'],'<!--thumbsup-->') !== false) {
						//it's a thumbs up! ABORT! ABORT!
						$users = array();
						break;
					}
					if (strpos($extra['content'],'<!--welcomeuser-->') !== false) {
						//it's a welcome! ABORT! ABORT!
						//(because it gets sent from Welcomebot; we have our own in HAWelcome)
						$users = array();
					}
					if (strpos($extra['content'],'<!--massmessage-->') !== false) {
						//it's a mass message! ABORT! ABORT!
						//(because it gets sent from MessengerBot; we have our own in MassMessage)
						$users = array();
					}
				}
				break;
		}
		return true;
	}

	function onGetPreferences( $user, &$preferences ) {
		unset($preferences['echo-show-alert']);

		if(array_key_exists('emailauthentication', $preferences)) {
			$preferences['emailauthentication']['section'] = 'echo/emailsettings';

			//move it to the proper place in the array
			$ema = $preferences['emailauthentication'];
			unset($preferences['emailauthentication']);
			$preferences['emailauthentication'] = $ema;
		}

		return true;
	}

	function onAccountCreated( $user, $byEmail ) {
		//default settings for new users that are different than default
		// $user->setOption( 'echo-subscriptions-web-kudos', true );
		// $user->saveSettings();

		//welcome new user!
		EchoEvent::create( array(
			'type' => 'welcome',
			'agent' => $user,
		) );

		return true;
	}

	//DON'T SEND EMAIL THROUGH ECHO
	//we can do this ourselves...
	function onEchoAbortEmailNotification( $user, $event ) {
		return false;
	}

}

