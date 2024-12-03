<?php

/**
 * @file plugins/generic/browseBySection/BrowseBySectionPlugin.inc.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class BrowseBySectionPlugin
 * @ingroup plugins_generic_browsebysection
 *
 * @brief Allow visitors to browse journal content by section.
 */

namespace APP\plugins\generic\browseBySection;

use PKP\core\PKPApplication;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\config\Config;
use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\browseBySection\pages\BrowseBySectionHandler;

define('BROWSEBYSECTION_DEFAULT_PER_PAGE', 30);
define('BROWSEBYSECTION_NMI_TYPE', 'BROWSEBYSECTION_NMI_');

class BrowseBySectionPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path);
        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return $success;
        }
        if ($success && $this->getEnabled()) {
            Hook::add('LoadHandler', [$this, 'loadPageHandler']);
            Hook::add('Templates::Manager::Sections::SectionForm::AdditionalMetadata', [$this, 'addSectionFormFields']);
            Hook::add('Schema::get::section', function ($hookName, $args) {
                $schema = &$args[0];

                $schema->properties->browseByEnabled = (object)[
                            'type' => 'boolean',
                            'apiSummary' => true,
                            'validation' => ['nullable']
                        ];

                $schema->properties->browseByPath = (object)[
                            'type' => 'string',
                            'apiSummary' => true,
                            'validation' => ['nullable']
                        ];

                $schema->properties->browseByPerPage = (object)[
                            'type' => 'number',
                            'apiSummary' => true,
                            'validation' => ['nullable']
                        ];

                $schema->properties->browseByOrder = (object)[
                            'type' => 'string',
                            'apiSummary' => true,
                            'validation' => ['nullable']
                        ];

                $schema->properties->browseByDescription = (object)[
                            'type' => 'string',
                            'apiSummary' => true,
                            'multilingual' => true,
                            'validation' => ['nullable']
                        ];
            });
            Hook::add('sectionform::initdata', [$this, 'initDataSectionFormFields']);
            Hook::add('sectionform::readuservars', [$this, 'readSectionFormFields']);
            Hook::add('sectionform::execute', [$this, 'executeSectionFormFields']);
            Hook::add('NavigationMenus::itemTypes', [$this, 'addMenuItemTypes']);
            Hook::add('NavigationMenus::displaySettings', [$this, 'setMenuItemDisplayDetails']);
            Hook::add('SitemapHandler::createJournalSitemap', [$this, 'addSitemapURLs']);
            $this->_registerTemplateResource();
        }
        return $success;
    }

    /**
     * @copydoc PKPPlugin::getDisplayName
     */
    public function getDisplayName()
    {
        return __('plugins.generic.browseBySection.name');
    }

    /**
     * @copydoc PKPPlugin::getDescription
     */
    public function getDescription()
    {
        return __('plugins.generic.browseBySection.description');
    }

    /**
     * Load the handler to deal with browse by section page requests
     *
     * @param $hookName string `LoadHandler`
     * @param $args array [
     *         @option string page
     *         @option string op
     *         @option string sourceFile
     * ]
     * @return bool
     */
    public function loadPageHandler($hookName, $args)
    {
        $page = $args[0];
        $handler =& $args[3];

        if ($this->getEnabled() && $page === 'section') {
            $handler = new BrowseBySectionHandler($this);
            return true;
        }

        return false;
    }

    /**
     * Add fields to the section editing form
     *
     * @param $hookName string `Templates::Manager::Sections::SectionForm::AdditionalMetadata`
     * @param $args array [
     *        @option array [
     *                @option name string Hook name
     *                @option sectionId int
     *        ]
     *        @option Smarty
     *        @option string
     * ]
     * @return bool
     */
    public function addSectionFormFields($hookName, $args)
    {
        $smarty =& $args[1];
        $output =& $args[2];
        $output .= $smarty->fetch($this->getTemplateResource('controllers/grids/settings/section/form/sectionFormAdditionalFields.tpl'));

        return false;
    }

    /**
     * Initialize data when form is first loaded
     *
     * @param $hookName string `sectionform::initData`
     * @parram $args array [
     *        @option SectionForm
     * ]
     */
    public function initDataSectionFormFields($hookName, $args)
    {
        $sectionForm = $args[0];
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : PKPApplication::CONTEXT_ID_NONE;

        $section = $sectionForm->getSectionId() ? Repo::section()->get($sectionForm->getSectionId(), $contextId) : null;

        if ($section) {
            $sectionForm->setData('browseByEnabled', $section->getData('browseByEnabled'));
            $sectionForm->setData('browseByPath', $section->getData('browseByPath'));
            $sectionForm->setData('browseByPerPage', $section->getData('browseByPerPage'));
            $sectionForm->setData('browseByDescription', $section->getData('browseByDescription'));
            $sectionForm->setData('browseByOrder', $section->getData('browseByOrder'));
        }

        $orderTypes = [
            'datePubDesc' => 'catalog.sortBy.datePublishedDesc',
            'datePubAsc' => 'catalog.sortBy.datePublishedAsc',
            'titleAsc' => 'catalog.sortBy.titleAsc',
            'titleDesc' => 'catalog.sortBy.titleDesc',
        ];
        $sectionForm->setData('orderTypes', $orderTypes);
    }

    /**
     * Read user input from additional fields in the section editing form
     *
     * @param $hookName string `sectionform::readUserVars`
     * @parram $args array [
     *        @option SectionForm
     *        @option array User vars
     * ]
     */
    public function readSectionFormFields($hookName, $args)
    {
        $sectionForm =& $args[0];
        $request = Application::get()->getRequest();

        $sectionForm->setData('browseByEnabled', $request->getUserVar('browseByEnabled'));
        $sectionForm->setData('browseByPath', $request->getUserVar('browseByPath'));
        $sectionForm->setData('browseByPerPage', $request->getUserVar('browseByPerPage'));
        $sectionForm->setData('browseByDescription', $request->getUserVar('browseByDescription', null));
        $sectionForm->setData('browseByOrder', $request->getUserVar('browseByOrder'));
    }

    /**
     * Save additional fields in the section editing form
     *
     * @param $hookName string `sectionform::execute`
     * @param $args array [
     *        @option SectionForm
     * ]
     */
    public function executeSectionFormFields($hookName, $args)
    {
        $sectionForm = $args[0];
        $request = Application::get()->getRequest();
        $section = Repo::section()->get($sectionForm->getSectionId(), $request->getContext()->getId());

        $section->setData('browseByEnabled', $sectionForm->getData('browseByEnabled'));
        $section->setData('browseByDescription', $sectionForm->getData('browseByDescription'));
        $section->setData('browseByOrder', $sectionForm->getData('browseByOrder'));

        // Force a valid browseByPath
        $browseByPath = $sectionForm->getData('browseByPath') ? $sectionForm->getData('browseByPath') : '';
        if (empty($browseByPath)) {
            $browseByPath = strtolower($section->getTitle($request->getContext()->getPrimaryLocale()));
        }
        $section->setData('browseByPath', preg_replace('/[^A-Za-z0-9-_]/', '', str_replace(' ', '-', $browseByPath)));

        // Force a valid browseByPerPage
        $browseByPerPage = $sectionForm->getData('browseByPerPage') ? $sectionForm->getData('browseByPerPage') : '';
        if (!ctype_digit($browseByPerPage)) {
            $browseByPerPage = null;
        }
        $section->setData('browseByPerPage', $browseByPerPage);

        Repo::section()->edit($section, ['browseByEnabled', 'browseByDescription', 'browseByOrder', 'browseByPath', 'browseByPerPage']);
    }

    /**
     * Add Navigation Menu Item types for linking to sections
     *
     * @param $hookName string
     * @param $args array [
     *        @option array Existing menu item types
     * ]
     */
    public function addMenuItemTypes($hookName, $args)
    {
        $types =& $args[0];
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : PKPApplication::CONTEXT_ID_NONE;

        $sections = Repo::section()->getCollector()->filterByContextIds([$contextId])->getMany();

        foreach ($sections as $section) {
            if ($section->getData('browseByEnabled')) {
                $types[BROWSEBYSECTION_NMI_TYPE . $section->getId()] = [
                    'title' => __('plugins.generic.browseBySection.navMenuItem', ['name' => $section->getLocalizedTitle()]),
                    'description' => __('plugins.generic.browseBySection.navMenuItem.description'),
                ];
            }
        }
    }

    /**
     * Set the display details for the custom menu item types
     *
     * @param $hookName string
     * @param $args array [
     *        @option NavigationMenuItem
     * ]
     */
    public function setMenuItemDisplayDetails($hookName, $args)
    {
        $navigationMenuItem =& $args[0];
        $typePrefixLength = strlen(BROWSEBYSECTION_NMI_TYPE);

        if (substr($navigationMenuItem->getType(), 0, $typePrefixLength) === BROWSEBYSECTION_NMI_TYPE) {
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            $contextId = $context ? $context->getId() : PKPApplication::CONTEXT_ID_NONE;
            $sectionId = substr($navigationMenuItem->getType(), $typePrefixLength);
            $section = Repo::section()->get($sectionId, $contextId);

            if ($section) {
                if (!$section->getData('browseByEnabled')) {
                    $navigationMenuItem->setIsDisplayed(false);
                } else {
                    $sectionPath = $section->getData('browseByPath') ? $section->getData('browseByPath') : $sectionId;
                    $dispatcher = $request->getDispatcher();
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'section',
                        'view',
                        htmlspecialchars($sectionPath)
                    ));
                }
            }
        }
    }

    /**
     * Add the browse by section URLs to the sitemap
     *
     * @param $hookName string
     * @param $args array
     * @return boolean
     */
    public function addSitemapURLs($hookName, $args)
    {
        $doc = $args[0];
        $rootNode = $doc->documentElement;

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if ($context) {
            $sections = Repo::section()->getCollector()
                ->filterByContextIds([$context->getId()])
                ->getMany();
            foreach ($sections as $section) {
                if ($section->getData('browseByEnabled')) {
                    $sectionPath = $section->getData('browseByPath') ? $section->getData('browseByPath') : $section->getId();
                    // Create and append sitemap XML "url" element
                    $url = $doc->createElement('url');
                    $url->appendChild($doc->createElement('loc', htmlspecialchars($request->url($context->getPath(), 'section', 'view', $sectionPath), ENT_COMPAT, 'UTF-8')));
                    $rootNode->appendChild($url);
                }
            }
        }
        return false;
    }
}
