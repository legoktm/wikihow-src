<?php

class TourPageRedirect 
{
	const EXPERIMENT_NAME = 'TOUR_PAGE_REDIRECT';
	
	public static function onArticleFromTitle(&$title, &$article) {
		global $wgOut;
		if($title && strtolower($title->getText()) == 'tour' && $title->getNamespace() == NS_PROJECT && Hydra::isEnabled(self::EXPERIMENT_NAME)) {
			$title = Title::newFromText('tour2', NS_PROJECT);	
			$wgOut->redirect($title->getFullURL());
		}
		return true;
	}
}
