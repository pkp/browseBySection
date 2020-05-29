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

		if (!$sectionPath || !$contextId) {
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

		$browseByOrder = $section->getData('browseByOrder');
		// ordering defaults to datePublished DESC for backwards compatibility (if option is unset)
		if (strpos($browseByOrder, 'title') !== false) {
			$orderBy = 'title';
		} else {
			$orderBy = 'datePublished';
		}
		if (strpos($browseByOrder, 'Asc') !== false) {
			$orderDir = 'ASC';
		} else {
			$orderDir = 'DESC';
		}
		import('classes.submission.Submission'); // Import status constants

		$params = [
			'contextId' => $contextId,
			'count' => $browseByPerPage,
			'offset' => $page ? ($page - 1) * $browseByPerPage : 0,
			'orderBy' => $orderBy,
			'orderDirection' => $orderDir,
			'sectionIds' => [(int) $section->getId()],
			'status' => STATUS_PUBLISHED,
		];

		$result = Services::get('submission')->getMany($params);
		$total = Services::get('submission')->getMax($params);

		if ($page > 1 && !$result->valid()) {
			$request->getDispatcher()->handle404();
			exit;
		}

		$submissions = [];
		$issueIds = [];
		foreach ($result as $submission) {
			$submissions[] = $submission;
			if ($submission->getCurrentPublication()->getData('issueId')) {
				$issueIds[] = $submission->getCurrentPublication()->getData('issueId');
			}
		}

		$result = Services::get('issue')->getMany([
			'contextId' => $contextId,
			'isPublished' => true,
			'issueIds' => array_unique($issueIds),
		]);
		$issues = iterator_to_array($result);

		$showingStart = $params['offset'] + 1;
		$showingEnd = min($params['offset'] + $params['count'], $params['offset'] + count($submissions));
		$nextPage = $total > $showingEnd ? $page + 1 : null;
		$prevPage = $showingStart > 1 ? $page - 1 : null;

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'section' => $section,
			'sectionPath' => $sectionPath,
			'sectionDescription' => $section->getLocalizedData('browseByDescription'),
			'articles' => $submissions,
			'issues' => $issues,
			'showingStart' => $showingStart,
			'showingEnd' => $showingEnd,
			'total' => $total,
			'nextPage' => $nextPage,
			'prevPage' => $prevPage,
		));

		$plugin = PluginRegistry::getPlugin('generic', 'browsebysectionplugin');

		return $templateMgr->display($plugin->getTemplateResource('frontend/pages/section.tpl'));
	}
}

