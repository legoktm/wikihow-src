<?php

class TourRedirect {
	const EXPERIMENT_NAME = 'tour_redirect';

	public static function onNewUserRedirect(&$redirectURL) {
		if(Hydra::isEnabled(self::EXPERIMENT_NAME)) {
			$redirectURL = '/wikiHow:tour';
		}
		return(true);
	}

}
