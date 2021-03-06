<?php

class WikihowPreferences {

	/**
	 * These are preferences that we use, but are not set on the
	 * preferences page. We use this array both when registering the
	 * preferences and during a reset all (we don't ever want to reset these)
	 */
	static $otherPreferences = array(
		'profilebox_stats',
		'profilebox_fav1',
		'profilebox_fav2',
		'profilebox_fav3',
		'profilebox_favs',
		'profilebox_startedEdited',
		'profilebox_display',
		'image_license',
		'phpbb_user_lastvisit',
		'phpbb_user_session_page',
		'phpbb_user_session_time',
		'show_google_authorship',
		'gplus_uid',
		'variant',
		'useadvanced');

	public static function userResetAllOptions($newOptions, $oldOptions) {
		//these are the options we don't want to reset when we say reset all
		//reset all comes from the preferences page
		//these are set in other locations

		foreach(self::$otherPreferences as $prefName) {
			if(array_key_exists($prefName, $oldOptions))
				$newOptions[$prefName] = $oldOptions [$prefName];
		}

		return true;

	}

	public static function getPreferences( $user, &$preferences ) {
		$userGroups = $user->getGroups();

		$preferences['disablemarketingemail'] =
			array(
				'type' => 'toggle',
				'section' => 'echo/emailsettingsind',
				'label-message' => 'prefs-marketing',
				'id' => 'wpMarketingEmailFlag',
				'invert' => '1' //backwards preference. 1 = off, 0 = on
			);

		$preferences['usertalknotifications'] =
			array(
				'type' => 'toggle',
				'section' => 'echo/emailsettingsind',
				'label-message' => 'prefs-talk',
				'id' => 'wpUserTalkNotifications',
				'invert' => '1' //backwards preference. 1 = off, 0 = on
			);

		//MOVED TO ECHO
		// $preferences['thumbsnotifications'] =
			// array(
				// 'type' => 'toggle',
				// 'section' => 'wikihow/notifications',
				// 'label-message' => 'prefs-thumbsnot',
				// 'id' => 'wpThumbsNotifications',
				// 'invert' => '1' //backwards preference. 1 = off, 0 = on
			// );

		if (class_exists('ThumbsUp')) {
			$preferences['thumbsemailnotifications'] =
				array(
					'type' => 'toggle',
					'section' => 'echo/emailsettingsind',
					'label-message' => 'prefs-thumbsemail',
					'id' => 'wpThumbsEmailNotifications',
					'invert' => '1' //backwards preference. 1 = off, 0 = on
				);
		}

		$preferences['enableauthoremail'] =
			array(
				'type' => 'toggle',
				'section' => 'echo/emailsettingsind',
				'label-message' => 'prefs-authoremail',
				'id' => 'wpAuthorEmailNotifications',
				'invert' => '1' //backwards preference. 1 = off, 0 = on
			);
			
		$preferences['managearticlenotifications'] =
			array(
				'type' => 'info',
				'section' => 'echo/emailsettingsind',
				'id' => 'managearticlenotifications',
				'default' => '<a href="/Special:AuthorEmailNotification">Manage Article Email Notifications</a>',
				'raw' => true
			);

		$preferences['hidepersistantsavebar'] =
			array(
				'type' => 'toggle',
				'section' => 'editing/advancedediting',
				'label-message' => 'prefs-persistent',
				'id' => 'hidepersistantsavebar',
				'name' => 'wpOphidepersistantsavebar'
			);

		//MOVED TO ECHO
		// $preferences['ignorefanmail'] =
			// array(
				// 'type' => 'toggle',
				// 'section' => 'wikihow/notifications',
				// 'label-message' => 'prefs-fanmail',
				// 'id' => 'ignorefanmail',
				// 'name' => 'wpOpignorefanmail'
			// );

		if ( in_array('staff', $userGroups) || in_array('sysop', $userGroups) ) {
			$preferences['autopatrol'] =
				array(
					'type' => 'toggle',
					'section' => 'editing/advancedediting',
					'label-message' => 'prefs-autopatrol',
					'id' => 'autopatrol',
					'name' => 'wpOpautopatrol'
				);
		}

		$preferences['defaulteditor'] =
			array(
				'type' => 'select',
				'section' => 'editing/advancedediting',
				'label-message' => 'prefs-editor',
				'options' => array(
					"Advanced Editor" => 'advanced',
					"Guided Editor" => 'visual',
				),
				'id' => 'wpDefaultEditor',
				'name' => 'wpDefaultEditor'
			);
			
		$preferences['articlecreator'] =
			array(
				'type' => 'toggle',
				'section' => 'editing/advancedediting',
				'label-message' => 'prefs-articlecreator',
				'id' => 'wpArticleCreator',
				'name' => 'wpArticleCreator'
			);

		if (class_exists('RCTest') && ( in_array('staff', $userGroups) || in_array('sysop', $userGroups) || in_array('newarticlepatrol', $userGroups) )) {
			$preferences['rctest'] =
				array(
					'type' => 'toggle',
					'section' => 'rc/advancedrc',
					'label-message' => 'prefs-rctest',
					'id' => 'rctest',
					'name' => 'wpOprctest',
					'invert' => '1' //backwards preference. 1 = off, 0 = on
				);
		}

		$preferences['recent_changes_widget_show'] =
			array(
				'type' => 'toggle',
				'section' => 'rc/advancedrc',
				'label-message' => 'prefs-rcwidget',
				'id' => 'recent_changes_widget_show',
				'name' => 'wpOprecent_changes_widget_show'
			);

		if ( in_array('sysop', $userGroups) || in_array('newarticlepatrol', $userGroups) ) {
			$preferences['welcomer'] =
				array(
					'type' => 'toggle',
					'section' => 'wikihow',
					'label-message' => 'prefs-welcomer',
					'id' => 'welcomer',
					'name' => 'wpOpwelcomer'
				);
		}

		/**
		 * Register the ones that we use, but are not accessible
		 * through the preferences page
		 */
		foreach(self::$otherPreferences as $prefName) {
			$preferences[$prefName] = array( 'type' => 'api');
		}

		/**** MOVING core preferences around ****/
		// if(array_key_exists('emailaddress', $preferences))
			// $preferences['emailaddress']['section'] = 'email';
		// if(array_key_exists('enotifwatchlistpages', $preferences))
			// $preferences['enotifwatchlistpages']['section'] = 'email';
		// if(array_key_exists('disablemail', $preferences))
			// $preferences['disablemail']['section'] = 'email';
		// if(array_key_exists('ccmeonemails', $preferences))
			// $preferences['ccmeonemails']['section'] = 'email';
		// if(array_key_exists('enotifminoredits', $preferences))
			// $preferences['enotifminoredits']['section'] = 'email';
		// if(array_key_exists('enotifrevealaddr', $preferences))
			// $preferences['enotifrevealaddr']['section'] = 'email';

		if(array_key_exists('enotifwatchlistpages', $preferences))
			$preferences['enotifwatchlistpages']['section'] = 'echo/emailsettingsind';
		if(array_key_exists('disablemail', $preferences))
			$preferences['disablemail']['section'] = 'echo/emailsettingsind';
		if(array_key_exists('enotifminoredits', $preferences))
			$preferences['enotifminoredits']['section'] = 'echo/emailsettingsind';
		if(array_key_exists('enotifrevealaddr', $preferences))
			$preferences['enotifrevealaddr']['section'] = 'echo/emailsettingsind';
			
		//remove a couple we don't need any longer
		unset($preferences['ccmeonemails']);
		unset($preferences['emailaddress']);

		return true;
	}

}