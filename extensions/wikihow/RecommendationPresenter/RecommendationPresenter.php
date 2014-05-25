<?php

$wgAutoloadClasses['RecommendationPresenter'] = dirname( __FILE__ ) . '/RecommendationPresenter.class.php';
$wgHooks['ArticleSaveComplete'][] = 'RecommendationPresenter::onArticleSaveComplete';
$wgHooks['BeforeInitialize'][] = 'RecommendationPresenter::onBeforeInitialize';
$wgHooks['ArticleDeleteComplete'][] = 'RecommendationPresenter::onArticleDeleteComplete';
