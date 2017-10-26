<?php
/**
 * @file classes/BrowseBySectionDAO.inc.php
 *
 * Copyright (c) 2017 Simon Fraser University
 * Copyright (c) 2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class BrowseBySectionDAO
 * @ingroup journal
 * @see Section
 *
 * @brief Additional operations for the BrowseBySectionDAO needed for section
 *  browsing.
 */

import('classes.journal.SectionDAO');

class BrowseBySectionDAO extends SectionDAO {

	/** @var array browse by setting names */
	public $settingNames = array(
		'browseByEnabled',
		'browseByPath',
		'browseByPerPage',
		'browseByDescription'
	);

	/**
	 * Get section by it's URL path
	 * @param $sectionPath string
	 * @return Section
	 */
	public function getSectionIdByPath($sectionPath) {

		$result = $this->retrieve(
			'SELECT section_id FROM section_settings WHERE setting_name = "browseByPath" AND setting_value = ?',
			$sectionPath
		);

		$sectionId = isset($result->fields['section_id']) ? $result->fields['section_id'] : null;

		return $sectionId;
	}

	/**
	 * Get section's browseBy settings
	 *
	 * @param $sectionId int
	 * @return array
	 */
	public function getSectionSettings($sectionId) {

		$paramMarkers = array_map(function($setting) {
			return '?';
		}, $this->settingNames);

		$result = $this->retrieve(
			'SELECT * FROM section_settings WHERE section_id = ? AND setting_name IN(' . join(', ', $paramMarkers) . ')',
			array_merge(
				array((int) $sectionId),
				$this->settingNames
			)
		);

		$settings = array();
		while (!$result->EOF) {
			$settings[] = $result->GetRowAssoc(false);
			$result->MoveNext();
		}
		$result->Close();

		return $settings;
	}

	/**
	 * Update section's browseBy settings
	 *
	 * @param $sectionId int
	 * @param $settings array [
	 *		@option array {
	 *				@option name Setting name
	 *				@option value string|array Optionally pass hash of localized values
	 *				@option type
	 *		}
	 * ]
	 */
	public function insertSectionSettings($sectionId, $settings) {

		$settingNames = array_map(function($setting) {
			return $setting['name'];
		}, $settings);

		$this->deleteSectionSettings($sectionId, $settingNames);

		foreach ($settings as $setting) {
			if (is_array($setting['value'])) {
				foreach ($setting['value'] as $locale => $localeValue) {
					$this->update(
						'INSERT INTO section_settings
							(section_id, locale, setting_name, setting_value, setting_type)
							VALUES
							(?, ?, ?, ?, ?)',
						array(
							(int) $sectionId,
							$locale,
							$setting['name'],
							$localeValue,
							$setting['type']
						)
					);
				}
			} else {
				$this->update(
					'INSERT INTO section_settings
					(section_id, setting_name, setting_value, setting_type)
					VALUES
					(?, ?, ?, ?)',
					array(
						(int) $sectionId,
						$setting['name'],
						$setting['value'],
						$setting['type']
					)
				);
			}
		}
	}

	/**
	 * Delete section's browseBy settings
	 *
	 * TODO call this when a section is deleted.
	 *
	 * @param $sectionId int
	 * @param $settingNames array List of setting_name values to delete. If empty, all
	 *  browse by setting values for this section will be deleted.
	 */
	public function deleteSectionSettings($sectionId, $settingNames = array()) {

		if (empty($settingNames)) {
			$settingNames = $this->settingNames;
		}

		foreach ($settingNames as $settingName) {
			$this->update('DELETE FROM section_settings WHERE section_id = ? AND setting_name = ?', array((int) $sectionId, $settingName));
		}
	}
}
