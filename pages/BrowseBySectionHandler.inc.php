<?php

/**
 * @file plugins/generic/browseBySection/pages/BrowseBySectionHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class BrowseBySectionHandler
 * @ingroup plugins_generic_browsebysection
 *
 * @brief Handle reader-facing router requests for the browse by section plugin
 */

import('classes.handler.Handler');

define('BROWSEBYSECTION_DEFAULT_PER_PAGE', 20);

class BrowseBySectionHandler extends Handler {

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextRequiredPolicy');
		$this->addPolicy(new ContextRequiredPolicy($request));

		import('classes.security.authorization.OjsJournalMustPublishPolicy');
		$this->addPolicy(new OjsJournalMustPublishPolicy($request));

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * View a section
	 *
	 * @param $args array [
	 *		@option string Section ID
	 *		@option string page number
 	 * ]
	 * @param $request PKPRequest
	 * @return null|JSONMessage
	 */
	public function view($args, $request) {
		$sectionPath = isset($args[0]) ? $args[0] : null;
		$page = isset($args[1]) && ctype_digit($args[1]) ? (int) $args[1] : 1;
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;
		$browseBySectionPlugin = PluginRegistry::getPlugin('generic', 'browsebysectionplugin');

		// The page $arg can only contain an integer that's not 1. The first page
		// URL does not include page $arg
		if (isset($args[1]) && (!ctype_digit($args[1]) || $args[1] == 1)) {
			$request->getDispatcher()->handle404();
			exit;
		}

		if (!$sectionPath) {
			$request->getDispatcher()->handle404();
			exit;
		}

		$browseBySectionDao = DAORegistry::getDAO('BrowseBySectionDAO');
		$sectionId = $browseBySectionDao->getSectionIdByPath($sectionPath);

		if (!$sectionId) {
			$request->getDispatcher()->handle404();
			exit;
		}

		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$section = $sectionDao->getById($sectionId, $contextId);

		if (!$section) {
			$request->getDispatcher()->handle404();
			exit;
		}

		$sectionSettings = $browseBySectionDao->getSectionSettings($section->getId());

		$browseByEnabled = null;
		$browseByPath = '';
		$browseByDescription = array();
		$currentLocale = AppLocale::getLocale();
		foreach ($sectionSettings as $sectionSetting) {
			if ($sectionSetting['setting_name'] === 'browseByEnabled') {
				$browseByEnabled = $sectionSetting['setting_value'];
			}
			if ($sectionSetting['setting_name'] === 'browseByPath') {
				$browseByPath = $sectionSetting['setting_value'];
			}
			if ($sectionSetting['setting_name'] === 'browseByDescription' && $sectionSetting['locale'] === $currentLocale) {
				$browseByDescription = $sectionSetting['setting_value'];
			}
		}

		if (empty($browseByEnabled)) {
			$request->getDispatcher()->handle404();
			exit;
		}

		import('lib.pkp.classes.submission.Submission'); // Import status constants

		$params = array(
			'count' => BROWSEBYSECTION_DEFAULT_PER_PAGE,
			'offset' => $page ? ($page - 1) * BROWSEBYSECTION_DEFAULT_PER_PAGE : 0,
			'orderBy' => 'datePublished',
			'sectionIds' => array($section->getId()),
			'status' => STATUS_PUBLISHED,
		);

		import('classes.core.ServicesContainer');
		$submissionService = ServicesContainer::instance()->get('submission');
		$submissions = $submissionService->getSubmissions($contextId, $params);
		$countMax = $submissionService->getSubmissionsMaxCount($contextId, $params);

		if ($page > 1 && !count($submissions)) {
			$request->getDispatcher()->handle404();
			exit;
		}

		$publishedArticles = array();
		if (!empty($submissions)) {
			$submissionIds = array_map(function($submission) {
				return $submission->getId();
			}, $submissions);
			$sectionPublishedArticlesDao = DAORegistry::getDAO('SectionPublishedArticlesDAO');
			$publishedArticles = $sectionPublishedArticlesDao->getPublishedArticlesByIds($submissionIds);
		}

		$currentlyShowingStart = $params['offset'] + 1;
		$currentlyShowingEnd = $params['offset'] + (count($submissions) < BROWSEBYSECTION_DEFAULT_PER_PAGE ? count($submissions) : BROWSEBYSECTION_DEFAULT_PER_PAGE);
		$currentlyShowingPage = $page;
		$countMaxPage = floor($countMax / BROWSEBYSECTION_DEFAULT_PER_PAGE) + ($countMax % BROWSEBYSECTION_DEFAULT_PER_PAGE ? 1 : 0);

		$dispatcher = $request->getDispatcher();
		$urlPrevPage = '';
		if ($currentlyShowingPage > 1) {
			$urlPrevPage = $dispatcher->url(
				$request,
				ROUTE_PAGE,
				null,
				'section',
				'view',
				array($section->getId(), $currentlyShowingPage === 2 ? null : $currentlyShowingPage - 1)

			);
		}
		$urlNextPage = '';
		if ($countMaxPage > $currentlyShowingPage) {
			$urlNextPage = $dispatcher->url(
				$request,
				ROUTE_PAGE,
				null,
				'section',
				'view',
				array($section->getId(), $currentlyShowingPage + 1)
			);
		}

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'section' => $section,
			'sectionPath' => $browseByPath,
			'sectionDescription' => $browseByDescription,
			'articles' => $publishedArticles,
			'currentlyShowingStart' => $currentlyShowingStart,
			'currentlyShowingEnd' => $currentlyShowingEnd,
			'countMax' => $countMax,
			'currentlyShowingPage' => $currentlyShowingPage,
			'countMaxPage' => $countMaxPage,
			'urlPrevPage' => $urlPrevPage,
			'urlNextPage' => $urlNextPage,
		));

		$plugin = PluginRegistry::getPlugin('generic', 'browsebysectionplugin');

		return $templateMgr->display($plugin->getTemplatePath() . 'frontend/pages/section.tpl');
	}
}

?>
