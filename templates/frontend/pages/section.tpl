{**
 * plugins/generic/browseBySection/templates/frontend/pages/section.tpl
 *
 * Copyright (c) 2017 Simon Fraser University
 * Copyright (c) 2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Display the reader-facing section page.
 *
 * @uses $section Section
 * @uses $sectionPath string The URL path for this section
 * @uses $sectionDescription string
 * @uses $articles array List of Submission objects
 * @uses $issues array List of Issue objects the $articles are published in
 * @uses $currentlyShowingStart int 20 in `20-30 of 100 results`
 * @uses $currentlyShowingEnd int 30 in `20-30 of 100 results`
 * @uses $countMax int 100 in `20-30 of 100 results`
 * @uses $currentlyShowingPage int 2 in `2 of 10 pages`
 * @uses $countMaxPage int 10 in `2 of 10 pages`.
 *}

{include file="frontend/components/header.tpl" pageTitleTranslated=$section->getLocalizedTitle()}

<div class="page page_section page_section_{$sectionPath|escape}">
	<h1 class="page_title">
		{$section->getLocalizedTitle()|escape}
	</h1>

	<div class="section_description">
		{$sectionDescription}
	</div>

	{if $articles|@count}
		<ul class="cmp_article_list">
			{foreach from=$articles item=article}
				<li>
					{* TODO remove section=null workaround. article_summary.tpl expects a specific section array. See issue_toc.tpl. *}
					{include file="frontend/objects/article_summary.tpl" section=null showDatePublished=true hideGalleys=true}
				</li>
			{/foreach}
		</ul>
	{else}
		<p class="section_empty">
			{translate key="plugins.generic.browseBySection.emptySection"}
		</p>
	{/if}

	{* TODO Refactor this to use a common pattern for all `cmp_pagination` needs *}
	<div class="cmp_pagination">
		{$currentlyShowingStart}-{$currentlyShowingEnd} of {$countMax}
		{if $urlPrevPage}
			<a href="{$urlPrevPage|escape}">Prev</a>
		{/if}
		{if $urlNextPage}
			<a href="{$urlNextPage|escape}">Next</a>
		{/if}
	</div>

</div><!-- .page -->

{include file="frontend/components/footer.tpl"}
