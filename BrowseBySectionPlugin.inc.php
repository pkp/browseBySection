<?php

/**
 * @file plugins/generic/browseBySection/BrowseBySectionPlugin.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class BrowseBySectionPlugin
 * @ingroup plugins_generic_browsebysection
 *
 * @brief Allow visitors to browse journal content by section.
 */
import('lib.pkp.classes.plugins.GenericPlugin');

define('BROWSEBYSECTION_DEFAULT_PER_PAGE', 30);
define('BROWSEBYSECTION_NMI_TYPE', 'BROWSEBYSECTION_NMI_');

class BrowseBySectionPlugin extends GenericPlugin {

	/**
	 * @copydoc Plugin::register
	 */
	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;
		if ($success && $this->getEnabled()) {
			HookRegistry::register('LoadHandler', array($this, 'loadPageHandler'));
			HookRegistry::register('sectiondao::getAdditionalFieldNames', array($this, 'addSectionDAOFieldNames'));
			HookRegistry::register('sectiondao::getLocaleFieldNames', array($this, 'addSectionDAOLocaleFieldNames'));
			HookRegistry::register('Templates::Manager::Sections::SectionForm::AdditionalMetadata', array($this, 'addSectionFormFields'));
			HookRegistry::register('sectionform::initdata', array($this, 'initDataSectionFormFields'));
			HookRegistry::register('sectionform::readuservars', array($this, 'readSectionFormFields'));
			HookRegistry::register('sectionform::execute', array($this, 'executeSectionFormFields'));
			HookRegistry::register('NavigationMenus::itemTypes', array($this, 'addMenuItemTypes'));
			HookRegistry::register('NavigationMenus::displaySettings', array($this, 'setMenuItemDisplayDetails'));
			HookRegistry::register('SitemapHandler::createJournalSitemap', array($this, 'addSitemapURLs'));
			$this->_registerTemplateResource();
		}
		return $success;
	}

	/**
	 * @copydoc PKPPlugin::getDisplayName
	 */
	public function getDisplayName() {
		return __('plugins.generic.browseBySection.name');
	}

	/**
	 * @copydoc PKPPlugin::getDescription
	 */
	public function getDescription() {
		return __('plugins.generic.browseBySection.description');
	}

	/**
	 * Load the handler to deal with browse by section page requests
	 *
	 * @param $hookName string `LoadHandler`
	 * @param $args array [
	 * 		@option string page
	 * 		@option string op
	 * 		@option string sourceFile
	 * ]
	 * @return bool
	 */
	public function loadPageHandler($hookName, $args) {
		$page = $args[0];

		if ($this->getEnabled() && $page === 'section') {
			$this->import('pages/BrowseBySectionHandler');
			define('HANDLER_CLASS', 'BrowseBySectionHandler');
			return true;
		}

		return false;
	}

	/**
	 * Add section settings to SectionDAO
	 *
	 * @param $hookName string
	 * @param $args array [
	 *		@option SectionDAO
	 *		@option array List of additional fields
	 * ]
	 */
	public function addSectionDAOFieldNames($hookName, $args) {
		$fields =& $args[1];
		$fields[] = 'browseByEnabled';
		$fields[] = 'browseByPath';
		$fields[] = 'browseByPerPage';
		$fields[] = 'browseByOrder';
	}

	/**
	 * Add localized section settings to SectionDAO
	 *
	 * @param $hookName string
	 * @param $args array [
	 *		@option SectionDAO
	 *		@option array List of additional localized fields
	 * ]
	 */
	public function addSectionDAOLocaleFieldNames($hookName, $args) {
		$fields =& $args[1];
		$fields[] = 'browseByDescription';
	}

	/**
	 * Add fields to the section editing form
	 *
	 * @param $hookName string `Templates::Manager::Sections::SectionForm::AdditionalMetadata`
	 * @param $args array [
	 *		@option array [
	 *				@option name string Hook name
	 *				@option sectionId int
	 *		]
	 *		@option Smarty
	 *		@option string
	 * ]
	 * @return bool
	 */
	public function addSectionFormFields($hookName, $args) {
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
	 *		@option SectionForm
	 * ]
	 */
	public function initDataSectionFormFields($hookName, $args) {
		$sectionForm = $args[0];
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$section = $sectionDao->getById($sectionForm->getSectionId(), $contextId);

		if ($section) {
			$sectionForm->setData('browseByEnabled', $section->getData('browseByEnabled'));
			$sectionForm->setData('browseByPath', $section->getData('browseByPath'));
			$sectionForm->setData('browseByPerPage', $section->getData('browseByPerPage'));
			$sectionForm->setData('browseByDescription', $section->getData('browseByDescription'));
			$orderTypes = array(
				'titleAsc' => 'catalog.sortBy.titleAsc',
				'titleDesc' => 'catalog.sortBy.titleDesc',
				'datePubDesc' => 'catalog.sortBy.datePublishedDesc',
				'datePubAsc' => 'catalog.sortBy.datePublishedAsc'
			);
			$sectionForm->setData('orderTypes', $orderTypes);
			$sectionForm->setData('browseByOrder', $section->getData('browseByOrder'));
		}
	}

	/**
	 * Read user input from additional fields in the section editing form
	 *
	 * @param $hookName string `sectionform::readUserVars`
	 * @parram $args array [
	 *		@option SectionForm
	 *		@option array User vars
	 * ]
	 */
	public function readSectionFormFields($hookName, $args) {
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
	 *		@option SectionForm
	 * ]
	 */
	public function executeSectionFormFields($hookName, $args) {
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$sectionForm = $args[0];
		$section = $sectionDao->getById($sectionForm->getSectionId(), Application::getRequest()->getContext()->getId());

		$section->setData('browseByEnabled', $sectionForm->getData('browseByEnabled'));
		$section->setData('browseByDescription', $sectionForm->getData('browseByDescription'));
		$section->setData('browseByOrder', $sectionForm->getData('browseByOrder'));

		// Force a valid browseByPath
		$browseByPath = $sectionForm->getData('browseByPath') ? $sectionForm->getData('browseByPath') : '';
		if (empty($browseByPath)) {
			$browseByPath = strtolower($section->getTitle(AppLocale::getPrimaryLocale()));
		}
		$section->setData('browseByPath', preg_replace('/[^A-Za-z0-9-_]/', '', str_replace(' ', '-', $browseByPath)));

		// Force a valid browseByPerPage
		$browseByPerPage = $sectionForm->getData('browseByPerPage') ? $sectionForm->getData('browseByPerPage') : '';
		if (!ctype_digit($browseByPerPage)) {
			$browseByPerPage = null;
		}
		$section->setData('browseByPerPage', $browseByPerPage);

		$sectionDao->updateObject($section);
	}

	/**
	 * Add Navigation Menu Item types for linking to sections
	 *
	 * @param $hookName string
	 * @param $args array [
	 *		@option array Existing menu item types
	 * ]
	 */
	public function addMenuItemTypes($hookName, $args) {
		$types =& $args[0];
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$sections = $sectionDao->getByContextId($contextId);

		while ($section = $sections->next()) {
			if ($section->getData('browseByEnabled')) {
				$types[BROWSEBYSECTION_NMI_TYPE . $section->getId()] = array(
					'title' => __('plugins.generic.browseBySection.navMenuItem', array('name' => $section->getLocalizedTitle())),
					'description' => __('plugins.generic.browseBySection.navMenuItem.description'),
				);
			}
		}
	}

	/**
	 * Set the display details for the custom menu item types
	 *
	 * @param $hookName string
	 * @param $args array [
	 *		@option NavigationMenuItem
	 * ]
	 */
	public function setMenuItemDisplayDetails($hookName, $args) {
		$navigationMenuItem =& $args[0];
		$typePrefixLength = strlen(BROWSEBYSECTION_NMI_TYPE);

		if (substr($navigationMenuItem->getType(), 0, $typePrefixLength) === BROWSEBYSECTION_NMI_TYPE) {
			$request = Application::get()->getRequest();
			$context = $request->getContext();
			$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;
			$sectionId = substr($navigationMenuItem->getType(), $typePrefixLength);
			$sectionDao = DAORegistry::getDAO('SectionDAO');
			$section = $sectionDao->getById($sectionId, $contextId);
			if (!$section->getData('browseByEnabled')) {
				$navigationMenuItem->setIsDisplayed(false);
			} else {
				$sectionPath = $section->getData('browseByPath') ? $section->getData('browseByPath') : $sectionId;
				$dispatcher = $request->getDispatcher();
				$navigationMenuItem->setUrl($dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'section',
					'view',
					htmlspecialchars($sectionPath)
				));
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
	function addSitemapURLs($hookName, $args) {
		$doc = $args[0];
		$rootNode = $doc->documentElement;

		$request = Application::getRequest();
		$context = $request->getContext();
		if ($context) {
			$sectionDao = DAORegistry::getDAO('SectionDAO');
			$sections = $sectionDao->getByContextId($context->getId());
			while ($section = $sections->next()) {
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

