<?php

/**
 * @file plugins/generic/browseBySection/pages/BrowseBySectionHandler.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BrowseBySectionHandler
 * @brief Handle reader-facing router requests for the browse by section plugin
 */

namespace APP\plugins\generic\browseBySection\pages;

use APP\facades\Repo;
use APP\handler\Handler;
use APP\security\authorization\OjsJournalMustPublishPolicy;
use APP\submission\Collector as SubmissionCollector;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\Role;
use PKP\submission\PKPSubmission;
use PKP\userGroup\UserGroup;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BrowseBySectionHandler extends Handler
{
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextRequiredPolicy($request));
        $this->addPolicy(new OjsJournalMustPublishPolicy($request));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * View a section
     *
     * @param $args array [
     *        @option string Section ID
     *        @option string page number
      * ]
     * @param $request PKPRequest
     * @return null|JSONMessage
     */
    public function view($args, $request)
    {
        $sectionPath = $args[0] ?? null;
        $page = isset($args[1]) && ctype_digit($args[1]) ? (int) $args[1] : 1;
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : PKPApplication::SITE_CONTEXT_ID;

        // The page $arg can only contain an integer that's not 1. The first page
        // URL does not include page $arg
        if (isset($args[1]) && (!ctype_digit($args[1]) || $args[1] == 1)) {
            throw new NotFoundHttpException();
        }

        if (!$sectionPath || !$contextId) {
            throw new NotFoundHttpException();
        }

        $sections = Repo::section()->getCollector()->filterByContextIds([$contextId])->getMany();

        $sectionExists = false;
        foreach ($sections as $section) {
            if ($section->getData('browseByEnabled') && $section->getData('browseByPath') === $sectionPath) {
                $sectionExists = true;
                break;
            }
        }

        if (!$sectionExists) {
            throw new NotFoundHttpException();
        }

        $browseByPerPage = $section->getData('browseByPerPage');
        if (empty($browseByPerPage)) {
            $browseByPerPage = BROWSEBYSECTION_DEFAULT_PER_PAGE;
        }

        $browseByOrder = $section->getData('browseByOrder');
        // ordering defaults to datePublished DESC for backwards compatibility (if option is unset)

        $orderBy = str_contains($browseByOrder, 'title') ?
            SubmissionCollector::ORDERBY_TITLE : SubmissionCollector::ORDERBY_DATE_PUBLISHED;
        $orderDir = str_contains($browseByOrder, 'Asc') ?
            SubmissionCollector::ORDER_DIR_ASC : SubmissionCollector::ORDER_DIR_DESC;

        $offset = $page ? ($page - 1) * $browseByPerPage : 0;

        $collector = Repo::submission()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterBySectionIds([$section->getId()])
            ->filterByStatus([PKPSubmission::STATUS_PUBLISHED])
            ->offset($offset)
            ->limit($browseByPerPage)
            ->orderBy($orderBy, $orderDir);
        $submissionsIterator = $collector->getMany();
        $total = $collector->limit(null)->offset(null)->getCount();

        if ($page > 1 && !count($submissionsIterator)) {
            throw new NotFoundHttpException();
        }

        $articleGroups = [];
        $submissions = [];
        $issueIds = [];
        foreach ($submissionsIterator as $submission) {
            $submissions[] = $submission;
            if ($submission->getCurrentPublication()->getData('issueId')) {
                $issueIds[] = $submission->getCurrentPublication()->getData('issueId');
            }
        }
        if ($orderBy === 'title') {
            // segment groups alphabetically
            $key = '';
            $group = [];
            foreach ($submissions as $article) {
                $newKey = mb_substr($article->getCurrentPublication()->getLocalizedTitle(), 0, 1);
                if ($newKey !== $key) {
                    if (count($group)) {
                        $articleGroups[] = ['key' => $key, 'articles' => $group];
                    }
                    $group = [];
                    $key = $newKey;
                }
                $group[] = $article;
            }
            if (count($group)) {
                $articleGroups[] = ['key' => $key, 'articles' => $group];
            }
        } else {
            // one continuous group
            $articleGroups[] = ['key' => null, 'articles' => $submissions];
        }

        $issuesIterator = Repo::issue()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByPublished(true)
            ->filterByIssueIds(array_unique($issueIds))
            ->getMany();
        $issues = iterator_to_array($issuesIterator);

        $showingStart = $offset + 1;
        $showingEnd = min($offset + $browseByPerPage, $offset + count($submissions));
        $nextPage = $total > $showingEnd ? $page + 1 : null;
        $prevPage = $showingStart > 1 ? $page - 1 : null;

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'section' => $section,
            'sectionPath' => $sectionPath,
            'authorUserGroups' => UserGroup::withRoleIds([Role::ROLE_ID_AUTHOR])
                ->withContextIds([$context->getId()])
                ->get(),
            'sectionDescription' => $section->getLocalizedData('browseByDescription'),
            'articleGroups' => $articleGroups,
            'issues' => $issues,
            'showingStart' => $showingStart,
            'showingEnd' => $showingEnd,
            'total' => $total,
            'nextPage' => $nextPage,
            'prevPage' => $prevPage,
        ]);

        $plugin = PluginRegistry::getPlugin('generic', 'browsebysectionplugin');

        return $templateMgr->display($plugin->getTemplateResource('frontend/pages/section.tpl'));
    }
}
