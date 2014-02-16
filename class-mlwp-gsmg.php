<?php 
/**
 * Extension for the Google XML Sitemaps plugin
 *
 * Adds sitemaps for each language, thus making every translation available
 * in the site's sitemap. 
 * 
 * You can see the original plugin here - {@link http://wordpress.org/plugins/google-sitemap-generator/ Google XML Sitemaps}
 *
 * @package Multilingual WP
 * @author Nikola Nikolov <nikolov.tmw@gmail.com>
 * @copyright Copyleft (?) 2012-2013, Nikola Nikolov
 * @license {@link http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3}
 * @since 0.1
 */

/**
* 
*/
class MLWP_GSMG extends GoogleSitemapGeneratorStandardBuilder {
	/**
	 * Override the default construct method for GoogleSitemapGeneratorStandardBuilder
	 *
	 * This way we add our actions with higher(lower index) priority,
	 * so then we can remove the default actions in MLWP_GSMG::remove_def_actions()
	 */
	function __construct() {
		add_action( "sm_build_index", array( $this, "language_index" ), 1, 1 );
		add_action( "sm_build_content", array( $this, "language_content" ), 1, 3 );

		$this->is_sd = Multilingual_WP::LT_SD == _mlwp()->lang_mode;

		$this->remove_def_actions();
	}

	/**
	 * Removes the default actions added by GoogleSitemapGeneratorStandardBuilder::__construct()
	 * 
	 * By removing the default actions, we make sure that there
	 * won't be duplication issues. Also we make sure to only remove
	 * the GoogleSitemapGeneratorStandardBuilder::Index()
	 * and GoogleSitemapGeneratorStandardBuilder::Content() functions
	 */
	private function remove_def_actions() {
		global $wp_filter;

		// We loop through all of the filters data, because add_filter() creates a unique index and GoogleSitemapGeneratorStandardBuilder 
		// creates an annonymous instance - so we can't reference it in any way

		// We don't modify the index on sub-domained sites - we already have language information from the subdomain
		if ( ! $this->is_sd && isset( $wp_filter['sm_build_index'][10] ) && $wp_filter['sm_build_index'][10] ) {
			foreach ( $wp_filter['sm_build_index'][10] as $id => $data ) {
				if ( is_array( $data['function'] ) && is_object( $data['function'][0] ) && get_class( $data['function'][0] ) == 'GoogleSitemapGeneratorStandardBuilder' && $data['function'][1] == 'Index' ) {
					unset( $wp_filter['sm_build_index'][10][ $id ] );
				}
			}
		}

		if ( isset( $wp_filter['sm_build_content'][10] ) && $wp_filter['sm_build_content'][10] ) {
			foreach ( $wp_filter['sm_build_content'][10] as $id => $data ) {
				if ( is_array( $data['function'] ) && is_object( $data['function'][0] ) && get_class( $data['function'][0] ) == 'GoogleSitemapGeneratorStandardBuilder' && $data['function'][1] == 'Content' ) {
					unset( $wp_filter['sm_build_content'][10][ $id ] );
				}
			}
		}
	}

	/**
	 * @param $gsg GoogleSitemapGenerator
	 */
	public function language_index( $gsg ) {
		global $wpdb;

		if ( $this->is_sd ) {
			return;
		}

		$mlwp = _mlwp();
		$blogUpdate = strtotime( get_lastpostdate( 'blog' ) );
		$languages = $mlwp->get_options( 'languages' );

		foreach ( $mlwp->get_enabled_languages() as $language ) {
			$prefix = $mlwp::QUERY_VAR . "-{$language}-";
			$mlwp->current_lang = $language;
			$mlwp->locale = $languages[ $language ]['locale'];
			$mlwp->fix_rewrite();

			$gsg->AddSitemap( "{$prefix}misc", null, $blogUpdate );

			if ( $gsg->GetOption( "in_arch" ) ) {
				$gsg->AddSitemap( "{$prefix}archives", null, $blogUpdate );
			}
			if ( $gsg->GetOption( "in_auth" ) ) {
				$gsg->AddSitemap( "{$prefix}authors", null, $blogUpdate );
			}

			$taxonomies = $this->GetEnabledTaxonomies( $gsg );
			foreach ( $taxonomies AS $tax ) {
				$gsg->AddSitemap( "{$prefix}tax", $tax, $blogUpdate );
			}

			$pages = $gsg->GetPages();
			if ( count( $pages ) > 0 ) {
				$gsg->AddSitemap( "{$prefix}externals", null, $blogUpdate );
			}

			$enabledPostTypes = $gsg->GetActivePostTypes();

			if ( count( $enabledPostTypes ) > 0 ) {

				$excludedPostIDs = $gsg->GetExcludedPostIDs( $gsg );
				$exPostSQL = "";
				if ( count( $excludedPostIDs ) > 0 ) {
					$exPostSQL = "AND p.ID NOT IN (" . implode( ",", $excludedPostIDs ) . ")";
				}

				$excludedCategoryIDs = $gsg->GetExcludedCategoryIDs( $gsg );
				$exCatSQL = "";
				if ( count( $excludedCategoryIDs ) > 0 ) {
					$exCatSQL = "AND ( p.ID NOT IN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode( ",", $excludedCategoryIDs ) . ")))";
				}

				foreach ( $enabledPostTypes AS $postType ) {
					$q = "
						SELECT
							YEAR(p.post_date) AS `year`,
							MONTH(p.post_date) AS `month`,
							COUNT(p.ID) AS `numposts`,
							MAX(p.post_date) as `last_mod`
						FROM
							{$wpdb->posts} p
						WHERE
							p.post_password = ''
							AND p.post_type = '" . $wpdb->escape( $postType ) . "'
							AND p.post_status = 'publish'
							$exPostSQL
							$exCatSQL
						GROUP BY
							YEAR(p.post_date),
							MONTH(p.post_date)
						ORDER BY
							p.post_date DESC";

					$posts = $wpdb->get_results( $q );

					if ( $posts ) {
						foreach( $posts as $post ) {
							$gsg->AddSitemap( "{$prefix}pt", $postType . "-" . sprintf( "%04d-%02d", $post->year, $post->month ), $gsg->GetTimestampFromMySql( $post->last_mod ) );
						}
					}
				}
			}
		}
	}

	/**
	 * @param $gsg GoogleSitemapGenerator
	 * @param $type String
	 * @param $params array
	 */
	public function language_content( $gsg, $type, $params ) {
		$mlwp = _mlwp();
		if ( ( $type == $mlwp::QUERY_VAR && $params ) || $this->is_sd ) {
			$mlwp->is_custom_sitemap = true;
			$_params = explode( '-', $params );
			if ( count( $_params ) > 1 ) {
				$lang = ! $this->is_sd ? array_shift( $_params ) : $mlwp->current_lang;
				if ( $mlwp->is_enabled( $lang ) ) {
					// If we're not on a sub-domain, we have to fiddle around with the params
					if ( ! $this->is_sd ) {
						$languages = $mlwp->get_options( 'languages' );
						$mlwp->current_lang = $lang;
						$mlwp->locale = $languages[ $lang ]['locale'];
						$mlwp->fix_rewrite();

						$type = array_shift( $_params );
					}

					$this->Content( $gsg, $type, implode( '-', $_params ) );
				}
			} elseif ( $mlwp->is_enabled( $params ) ) {
				// $this->language_index( $gsg, $params );
				$this->Content( $gsg, $type, $params );
			}
			$mlwp->is_custom_sitemap = false;
		} else {
			$this->Content( $gsg, $type, $params );
		}
	}
}