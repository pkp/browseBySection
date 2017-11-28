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
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;
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

		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$sections = $sectionDao->getByContextId($contextId);

		$sectionExists = false;
		while ($section = $sections->next()) {
			if ($section->getData('browseByEnabled') && $section->getData('browseByPath') === $sectionPath) {
				$sectionExists = true;
				break;
			}
		}

		if (!$sectionExists) {
			$request->getDispatcher()->handle404();
			exit;
		}

		$browseByPerPage = $section->getData('browseByPerPage');
		if (empty($browseByPerPage)) {
			$browseByPerPage = BROWSEBYSECTION_DEFAULT_PER_PAGE;
		}

		import('lib.pkp.classes.submission.Submission'); // Import status constants

		$params = array(
			'count' => $browseByPerPage,
			'offset' => $page ? ($page - 1) * $browseByPerPage : 0,
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

		$issues = array();
		if (!empty($publishedArticles)) {
			$issueIds = array_map(function($article) {
				return $article->getIssueId();
			}, $publishedArticles);
			$issueIds = array_unique($issueIds);
			$issueDao = DAORegistry::getDAO('IssueDAO');
			foreach ($issueIds as $issueId) {
				$issue = $issueDao->getById($issueId);
				if ($issue->getPublished()) {
					$issues[] = $issue;
				}
			}
		}

		$currentlyShowingStart = $params['offset'] + 1;
		$currentlyShowingEnd = $params['offset'] + (count($submissions) < $browseByPerPage ? count($submissions) : $browseByPerPage);
		$currentlyShowingPage = $page;
		$countMaxPage = floor($countMax / $browseByPerPage) + ($countMax % $browseByPerPage ? 1 : 0);

		$dispatcher = $request->getDispatcher();
		$urlPrevPage = '';
		if ($currentlyShowingPage > 1) {
			$urlPrevPage = $dispatcher->url(
				$request,
				ROUTE_PAGE,
				null,
				'section',
				'view',
				array($sectionPath, $currentlyShowingPage === 2 ? null : $currentlyShowingPage - 1)
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
				array($sectionPath, $currentlyShowingPage + 1)
			);
		}

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'section' => $section,
			'sectionPath' => $sectionPath,
			'sectionDescription' => $browseByDescription,
			'articles' => $publishedArticles,
			'issues' => $issues,
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
