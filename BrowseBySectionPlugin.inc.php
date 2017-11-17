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
import('plugins.generic.browseBySection.classes.SectionPublishedArticlesDAO');
import('plugins.generic.browseBySection.classes.BrowseBySectionDAO');

define('BROWSEBYSECTION_DEFAULT_PER_PAGE', 30);
define('BROWSEBYSECTION_NMI_TYPE', 'BROWSEBYSECTION_NMI_');

class BrowseBySectionPlugin extends GenericPlugin {

	/**
	 * @copydoc Plugin::register
	 */
	public function register($category, $path) {
		$success = parent::register($category, $path);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;
		if ($success && $this->getEnabled()) {
			DAORegistry::registerDAO('SectionPublishedArticlesDAO', new SectionPublishedArticlesDAO());
			DAORegistry::registerDAO('BrowseBySectionDAO', new BrowseBySectionDAO());
			HookRegistry::register('LoadHandler', array($this, 'loadPageHandler'));
			HookRegistry::register('Templates::Manager::Sections::SectionForm::AdditionalMetadata', array($this, 'addSectionFormFields'));
			HookRegistry::register('sectionform::initdata', array($this, 'initDataSectionFormFields'));
			HookRegistry::register('sectionform::readuservars', array($this, 'readSectionFormFields'));
			HookRegistry::register('sectionform::execute', array($this, 'executeSectionFormFields'));
			HookRegistry::register('NavigationMenus::itemTypes', array($this, 'addMenuItemTypes'));
			HookRegistry::register('NavigationMenus::displaySettings', array($this, 'setMenuItemDisplayDetails'));
			$this->_registerTemplateResource();
		}
		return $success;
	}

	/**
	 * @copydoc PKPPlugin::getTemplatePath
	 */
	public function getTemplatePath($inCore = false) {
		return $this->getTemplateResourceName() . ':templates/';
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
		$output .= $smarty->fetch($this->getTemplatePath() . 'controllers/grids/settings/section/form/sectionFormAdditionalFields.tpl');

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
		$sectionId = $sectionForm->getSectionId();
		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		$browseBySectionDao = DAORegistry::getDAO('BrowseBySectionDAO');
		$settings = $browseBySectionDao->getSectionSettings($sectionId);
		$browseByDescription = array();
		foreach ($settings as $setting) {
			if ($setting['setting_name'] === 'browseByDescription') {
				$browseByDescription[$setting['locale']] = $setting['setting_value'];
			} else {
				$sectionForm->setData($setting['setting_name'], $setting['setting_value']);
			}
		}
		$sectionForm->setData('browseByDescription', $browseByDescription);
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
		$request = Application::getRequest();

		$sectionForm->setData('browseByEnabled', $request->getUserVar('browseByEnabled'));
		$sectionForm->setData('browseByPath', $request->getUserVar('browseByPath'));
		$sectionForm->setData('browseByPerPage', $request->getUserVar('browseByPerPage'));
		$sectionForm->setData('browseByDescription', $request->getUserVar('browseByDescription'));
	}

	/**
	 * Save additional fields in the section editing form
	 *
	 * @param $hookName string `sectionform::execute`
	 * @param $args array [
	 *		@option SectionForm
	 *		@option Section
	 *		@option Request
	 * ]
	 */
	public function executeSectionFormFields($hookName, $args) {
		$sectionForm = $args[0];
		$section = $args[1];
		$request = $args[2];
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		// Force a valid browseByPath
		$browseByPath = $sectionForm->getData('browseByPath') ? $sectionForm->getData('browseByPath') : '';
		if (empty($browseByPath)) {
			$browseByPath = strtolower($section->getTitle(AppLocale::getPrimaryLocale()));
		}
		$browseByPath =	preg_replace('/[^A-Za-z0-9-_]/', '', str_replace(' ', '-', $browseByPath));

		// Force a valid browseByPerPage
		$browseByPerPage = $sectionForm->getData('browseByPerPage') ? $sectionForm->getData('browseByPerPage') : '';
		if (!ctype_digit($browseByPerPage)) {
			$browseByPerPage = null;
		}

		$sectionSettings = array(
			array(
				'name' => 'browseByEnabled',
				'value' => $sectionForm->getData('browseByEnabled'),
				'type' => 'bool'
			),
			array(
				'name' => 'browseByPath',
				'value' => $browseByPath,
				'type' => 'string'
			),
			array(
				'name' => 'browseByPerPage',
				'value' => $browseByPerPage,
				'type' => 'int'
			),
			array(
				'name' => 'browseByDescription',
				'value' => $sectionForm->getData('browseByDescription'),
				'type' => 'string'
			),
		);

		$browseBySectionDao = DAORegistry::getDAO('BrowseBySectionDAO');
		$browseBySectionDao->insertSectionSettings($section->getId(), $sectionSettings);
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
		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		if (!$contextId) {
			return;
		}

		$browseBySectionDao = DAORegistry::getDAO('BrowseBySectionDAO');
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$results = $sectionDao->getByContextId($contextId);

		while ($section = $results->next()) {
			$sections[] = $section;
		}

		foreach ($sections as $section) {
			$browseByEnabled = false;
			$sectionSettings = $browseBySectionDao->getSectionSettings($section->getId());
			foreach ($sectionSettings as $sectionSetting) {
				if ($sectionSetting['setting_name'] === 'browseByEnabled' && !empty($sectionSetting['setting_value'])) {
					$browseByEnabled = true;
				}
			};

			if (!$browseByEnabled) {
				continue;
			}

			$types[BROWSEBYSECTION_NMI_TYPE . $section->getId()] = array(
				'title' => __('plugins.generic.browseBySection.navMenuItem', array('name' => $section->getLocalizedTitle())),
				'description' => __('plugins.generic.browseBySection.navMenuItem.description'),
			);
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
			$sectionId = substr($navigationMenuItem->getType(), $typePrefixLength);
			$sectionPath = $sectionId;
			$browseBySectionDao = DAORegistry::getDAO('BrowseBySectionDAO');
			$sectionSettings = $browseBySectionDao->getSectionSettings($sectionId);
			foreach ($sectionSettings as $sectionSetting) {
				if ($sectionSetting['setting_name'] === 'browseByPath') {
					$sectionPath = $sectionSetting['setting_value'];
				}
			}
			$request = Application::getRequest();
			$dispatcher = $request->getDispatcher();
			$navigationMenuItem->setUrl($dispatcher->url(
				$request,
				ROUTE_PAGE,
				null,
				'section',
				'view',
				$sectionPath
			));
		}
	}
}

?>
