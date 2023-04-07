{**
 * templates/controllers/grid/settings/section/form/sectionFormAdditionalFields.tpl
 *
 * Copyright (c) 2017 Simon Fraser University
 * Copyright (c) 2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Add fields for section browsing to the section edit form
 *
 * @uses $browseByEnabled boolean Should this section be browseable?
 *}
<div style="clear:both;">
	{fbvFormSection title="plugins.generic.browseBySection.browsingLabel" list="true"}
		{fbvElement type="checkbox" value="1" id="browseByEnabled" checked=$browseByEnabled label="plugins.generic.browseBySection.enableBrowsing"}
	{/fbvFormSection}
	{fbvFormSection title="plugins.generic.browseBySection.browseByPathLabel" list="true"}
		{fbvElement type="text" id="browseByPath" value=$browseByPath label="plugins.generic.browseBySection.browseByPathDescription"}
	{/fbvFormSection}
	{fbvFormSection title="plugins.generic.browseBySection.browseByPerPageLabel" list="true"}
		{fbvElement type="text" id="browseByPerPage" value=$browseByPerPage label="plugins.generic.browseBySection.browseByPerPageDescription"}
	{/fbvFormSection}
	{fbvFormSection title="plugins.generic.browseBySection.browseByDescriptionLabel"}
		{fbvElement type="textarea" multilingual=true id="browseByDescription" value=$browseByDescription rich=true}
	{/fbvFormSection}
	{fbvFormSection for="orderType" title="plugins.generic.browseBySection.orderBy"}
		{fbvElement type="select" id="browseByOrder" from=$orderTypes selected=$browseByOrder size=$fbvStyles.size.SMALL}
	{/fbvFormSection}
</div>
