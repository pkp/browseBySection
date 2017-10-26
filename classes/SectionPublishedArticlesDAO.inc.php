<?php
/**
 * @file classes/SectionPublishedArticlesDAO.inc.php
 *
 * Copyright (c) 2017 Simon Fraser University
 * Copyright (c) 2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SectionPublishedArticlesDAO
 * @ingroup article
 * @see PublishedArticle
 *
 * @brief Additional operations for the PublishedArticleDAO needed for section
 *  browsing.
 */

import('classes.article.PublishedArticleDAO');

class SectionPublishedArticlesDAO extends PublishedArticleDAO {

	/**
	 * Retrieve Published Articles by ids
	 * @param $publishedArticleIds array
	 * @return array
	 */
	public function getPublishedArticlesByIds($publishedArticleIds) {
		$result = $this->retrieve(
			'SELECT	ps.*,
				s.*,
				' . $this->getFetchColumns() . '
			FROM	published_submissions ps
				JOIN submissions s ON (ps.submission_id = s.submission_id)
				' . $this->getFetchJoins() . '
			WHERE ps.submission_id IN(' . join(',', array_map('intval', $publishedArticleIds)) . ')
			ORDER BY ps.date_published DESC',
			$this->getFetchParameters()
		);

		$publishedArticles = array();
		while (!$result->EOF) {
			$publishedArticles[] = $this->_fromRow($result->GetRowAssoc(false));
			$result->MoveNext();
		}

		$result->Close();
		return $publishedArticles;
	}

}
