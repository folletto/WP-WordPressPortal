<?php 
/*
Plugin Name: WordPress Portal
Plugin URI: http://digitalhymn.com/argilla/wpp
Description: This is a function library to ease themes development. It could be included in the theme or added as plugin. You can add an updated plugin to fix existing themes.
Author: Davide 'Folletto' Casali
Version: 0.6
Author URI: http://digitalhymn.com/
 ******************************************************************************************
 * WordPress Portal
 * WP Theming Functions Library
 * 
 * Last revision: 2007 09 26
 *
 * by Davide 'Folletto' Casali
 * www.digitalhymn.com
 * Copyright (C) 2006/2007 - Creative Commons (CC) by-sa 2.5
 * 
 * Based upon a library developed for key-one.it (Kallideas / Key-One)
 *
 */

/*
 * SUMMARY:
 *  wpp_foreach_post($filter, $limit): creates a "custom The Loop", with a filter match
 *  wpp_get_posts($filter, $limit): gets all the posts matching a filter
 *  wpp_uri_category($field, $default): gets the category of the loaded page
 *  wpp_in_category($nicename): [TheLoop] checks if the posts belongs to that category
 *  wpp_is_term_child_of($child, $parent): checks if the category is child of another (nicename)
 *  wpp_get_post_custom($custom, $before, $after, $optid): [TheLoop] gets the specified custom
 *  wpp_get_page_content($nicename, $on_empty): gets the specified page content
 *  wpp_is_admin($userid): check if the current logged user is an "administrator"
 *  wpp_get_last_comments($size): gets all the last comments
 *  wpp_get_last_comments_grouped($size): gets the last comments, one comment per post
 * 
 * DETAILS:
 * The most interesting function is the wpp_foreach_post() that in fact creates a custom
 * "The Loop", using the syntax:
 *          while($post = wpp_foreach_post($filter, $limit)) { ... }
 * 
 * The function wpp_uri_category() retrieves the correct category from the page currently loaded.
 *  If the uri opens a category, it returns the nicename of the category.
 *  If the uri opens a page, it returns the page nicename.
 *  If the uri opens a post, it returns the post category.
 * This is *really* useful to create complex sites, using the page hierarchy as structure.
 * 
 */

if (!function_exists('wpp_foreach_post') && !isset($WPP_VERSION)) {
	$WPP_VERSION = 'WordPressPortal/0.6';
	
	/****************************************************************************************************
	 * Creates a custom The Loop (i.e. like: while (have_posts()) : the_post(); [...] endwhile;).
	 * 
	 * The filter parameter in array mode filters additional special queries:
	 *   'category' => 'name', selects all the posts from a specific category using its nicename (slug)
	 *   'page' => 'name', retrieves the page defined by its nicename (slug)
	 *
	 * @param			filter string (SQL WHERE) or array (converted to SQL WHERE, AND of equals (==))
	 * @param			limit string (i.e. 1 or 1,10)
	 * @return		single post array
	 */
	function wpp_foreach_post($filter, $limit = null) {
		global $wpdb;
		global $__wpp_posts;						// working variables for the_wpp_loop
		
		global $__wpp_old_posts;				// backup: possible existing $post
		global $__wpp_old_previousday;	// backup: possible existing $previousday
		
		global $post, $id, $day;				// TheLoop emulation: content and working functions
		global $day, $previousday;			// TheLoop emulation: date functions
		
		// ****** Init
		$out = null;
		$where = array();
		
		if (!isset($__wpp_posts) || $__wpp_posts === null) {
			// *** Backup
			$__wpp_old_posts = $post;
			$__wpp_old_previousday = $previousday;
			
			// ****** Building SQL where clause. 
			if (is_array($filter)) {
				foreach ($filter as $key => $value) {
					if ($key == 'category') {
						// *** Special: category by nicename
						$catwhere = array("tr.term_taxonomy_id = '0'");
						$terms = wpp_get_terms_recursive($value);
						foreach ($terms as $term) {
							$catwhere[] = "tr.term_taxonomy_id = '" . $term->term_taxonomy_id . "'";
						}
						$where[] = '(' . join(' OR ', $catwhere) . ')';
					} else if ($key == 'page') {
						// *** Special: page by nicename
						$where[] = "post_name = '" . $value . "'";
					} else {
						// ***  Normal where condition
						$where[] = "" . $key . " = '" . $value . "'";
					}
				}
				$where = join(' AND ', $where);
			} else {
				$where = $filter;
				$filter = array();
			}
		
			// ****** Querying
			$query = "
				SELECT DISTINCT p.*
				FROM " . $wpdb->posts . " As p
				INNER JOIN " . $wpdb->term_relationships . " As tr ON tr.object_id = p.ID
				WHERE
					post_status = 'publish' AND 
					post_type = '" . (isset($filter['page']) ? "page" : "post") . "'
					" . ($where ? "AND " . $where : '') . "
				ORDER BY post_date DESC
					" . ($limit ? 'LIMIT ' . $limit : '') . "
				";
			
			$__wpp_posts = $wpdb->get_results($query);
		}
		
		// ****** Elaborate the custom The WPP Loop
		if (is_array($__wpp_posts) && sizeof($__wpp_posts) > 0) {
			// *** Next
			$post = array_shift($__wpp_posts);
			$id = $post->ID;
			$day = mysql2date('d.m.y', $post->post_date);
		
			$out = $post;
		} else {
			// *** Reset
			$post = null;
			$__wpp_posts = null;
		
			// *** Restore backup
			$post = $__wpp_old_posts;
			$id = $post->ID;
			$day = mysql2date('d.m.y', @$post->post_date);
			$previousday = $__wpp_old_previousday;
		}

		return $out;
	}

	/****************************************************************************************************
	 * Gets all the posts into an array. Wraps wpp_foreach_post().
	 *
	 * @param			filter string (SQL WHERE) or array (converted to SQL WHERE, AND of equals (==))
	 * @param			limit string (i.e. 1 or 1,10)
	 * @return		posts array
	 */
	function wpp_get_posts($filter, $limit = null) {
		$posts = array();

		while ($post = wpp_foreach_post($filter, $limit)) {
			$posts[] = $post;
		}

		return $posts;
	}
	
	/****************************************************************************************************
	 * Get the terms matching the nicename (slug) and all its CHILDREN in a flat array.
	 *
	 * @param			term nicename (slug)
	 * @param			(optional) depth of recursion (defult: -1, ALL)
	 * @param			(optional) taxonomy, defaults to 'category'
	 * @return		array of raw term rows
	 */
	function wpp_get_terms_recursive($ref, $levels = -1, $taxonomy = 'category') {
		global $wpdb;
		
		$out = array();
		
		if ($ref !== '') {
			// ****** Where
			if (strval($ref) === strval(intval($ref))) {
				$where = "AND tt.parent = '" . $ref . "'"; // *** INT, use id for PARENT
			} else {
				$where = "AND t.slug = '" . $ref . "'"; // *** STRING, use slug for TERM
			}
			
			// ****** Query
			$query = "
				SELECT *
				FROM " . $wpdb->term_taxonomy . " As tt
				INNER JOIN " . $wpdb->terms . " As t ON t.term_id = tt.term_id
				WHERE
					tt.taxonomy = '" . $taxonomy . "'
					" . $where . "
			";
			
			// ****** Data
			if ($terms = $wpdb->get_results($query)) {
				foreach ($terms as $term) {
					$out[] = $term; // Push
					
					if ($levels != 0) {
						$levels--;
						$out = array_merge($out, wpp_get_terms_recursive($term->term_id, $levels, $taxonomy));
					}
				}
			}
		}
		
		return $out;
	}
	
	/****************************************************************************************************
	 * Checks if the post in the_loop belongs to the specified category nicename.
	 * Different from in_category(), that checks for the id, not for the nicename.
	 *
	 * @param		container category nicename (slug)
	 * @param		(optional) optional parent nicename
	 * @return	boolean
	 */
	function wpp_in_category($nicename) {
		return wpp_is_term_child_of($nicename, get_the_category());
	}

	/****************************************************************************************************
	 * Checks if a category is child of another. Counts also self as true.
	 *
	 * @param		child category
	 * @param		parent category (or array)
	 * @return	boolean true
	 */
	function wpp_is_term_child_of($child_term, $parent_term) {
		if (is_array($parent_term)) {
			$terms = $parent_term;
		} else {
			$terms = wpp_get_terms_recursive(strval($parent_term));
		}

		foreach ($terms as $term) {
			if ($child_term == $term->slug) {
				return true;
			}
		}
	
		return false;
	}

	/****************************************************************************************************
	 * Get a specific custom item, optionally wrapped between two text strings.
	 * Works inside The Loop only. To be used used outside specify the optional id parameter.
	 *
	 * @param			custom field
	 * @param			before html
	 * @param			after html
	 * @param			optional id (to fetch the custom of a different post)
	 * @return		html output
	 */
	function wpp_get_post_custom($custom, $before = '', $after = '', $optid = 0) {
		global $id;

		$out = '';
		if ($id && !$optid) $optid = $id;

		$custom_fields = get_post_custom($optid);

		if (isset($custom_fields[$custom])) {
			$out = $before . $custom_fields[$custom][0] . $after;
		}

		return $out;
	}

	/****************************************************************************************************
	 * Returns the specified page, given a nicename.
	 *
	 * @param			page nicename
	 * @param			(optional) message on non-existing page
	 * @return		page content string
	 */
	function wpp_get_page_content($nicename, $on_empty = "The page '%s' is empty.") {
	  $out = '';
		
	  $posts = wpp_get_posts(array('page' => $nicename));
	  if ($posts[0]->post_content)
	    $out = $posts[0]->post_content;
	  else
	    $out = sprintf($on_empty, $nicename);
		
	  return $out;
	}

	/****************************************************************************************************
	 * Return the type of the 'zone' where we are and the matching id reference.
	 * It's like a normalized is_page/is_single/... with matching id.
	 * - types: page, post, author, search, category, date, tag, home
	 *
	 * @param			(optional) array shortcut (i.e. wpp_get_zone('id'))
	 * @return		array ['type' => '...', 'id' => 'n', 'terms' => array(...)]
	 */
	function wpp_get_zone($key = null) {
		$out = array(
			'type'  => 'none',
			'id'    => 0,
			'terms'	=> array()
			);
		
		global $__cache_wpp_get_zone; // Cache
		if (!is_array($__cache_wpp_get_zone)) {
			if (is_page()) {
				// *** We're in a PAGE
				global $post;
				$out['type'] = 'page';
				$out['id'] = $post->ID;
				$out['terms'] = array(get_term_by('slug', $post->post_name, 'category'));
			} else if (is_single()) {
				// *** We're in a POST
				global $post;
				$out['type'] = 'page';
				$out['id'] = $post->ID;
				$out['terms'] = wp_get_object_terms($post->ID, 'category');
			} else if (is_author()) {
				// *** We're in AUTHOR
				global $author;
				$out['type'] = 'author';
				$out['id'] = $author;
			} else if (is_search()) {
				// *** We're in a SEARCH
				global $s;
				$out['type'] = 'search';
				$out['id'] = $s;
			} else if (is_category()) {
				// *** We're in a CATEGORY
				global $cat;
				$out['type'] = 'cat';
				$out['id'] = $cat;
				$out['terms'] = array(get_term($cat, 'category'));
			} else if (is_date()) {
				// *** We're in a DATE
				global $year;
				$out['type'] = 'date';
				$out['id'] = $year;
			} else if ($_GET['tag']) {
				// *** We're in a TAG
				$out['type'] = 'tag';
				$out['id'] = $_GET['tag'];
			} else if (is_home()) {
				// *** We're in HOME
				global $paged;
				$out['type'] = 'home';
				$out['id'] = (intval($paged) ? intval($paged) : 1);
			} else if (is_404()) {
				// *** We're in 404
				global $paged;
				$out['type'] = '404';
				$out['id'] = '';
			}
			
			$__cache_wpp_get_zone = $out; // <-- Cache
		} else {
			$out = $__cache_wpp_get_zone; // --> Cache
		}
		
		// ****** Return
		if ($key === null) return $out;
		return $out[$key];
	}

	/****************************************************************************************************
	 * Checks if the specified user ID is an admin user
	 *
	 * @param		user id (0 for current logged user)
	 * @return	boolean
	 */
	function wpp_is_admin($uid = 0) {
	  global $wpdb, $current_user;
  
	  $out = false;
  
	  // ****** Get current logged user
	  if ($uid == 0 || strtolower($uid) == "me") {
	    if (isset($current_user) && isset($current_user->id) && $current_user->id > 0) {
	      $uid = $current_user->id;
	    }
	  }
  
	  // ****** Query check Admin
	  $query = "
			SELECT count(*) As isAdmin
			FROM " . $wpdb->usermeta . " As um
			WHERE
			  um.user_id = '" . $uid . "' AND
			  um.meta_key = 'wp_capabilities' AND
			  um.meta_value LIKE '%" . "\"administrator\"" . "%'
			LIMIT 1
			";
	
		// ****** Retrieving capabilities count
	  if ($users = $wpdb->get_results($query)) {
	  	// *** Exists
	  	if ($users[0]->isAdmin > 0) {
	  	  $out = true;
		  }
	  }
  
	  return $out;
	}

	/****************************************************************************************************
	 * Get comments list array.
	 *
	 * @param		number of comments to retrieve
	 * @param		optional post ID to relate comments
	 * @return	array
	 */
	function wpp_get_last_comments($size = 10, $id = 0) {
		global $wpdb;
		$out = array();
	
		$sqlPost = "";
		if ($id > 0) $sqlPost = "AND p.ID = '" . $id . "'";
	
		$comments = $wpdb->get_results("
			SELECT
				c.comment_ID, c.comment_author, c.comment_author_email,
				c.comment_date, c.comment_content, c.comment_post_ID,
				p.post_title, p.comment_count
			FROM " . $wpdb->comments . " as c
			INNER JOIN " . $wpdb->posts . " as p ON c.comment_post_ID = p.ID
			WHERE
				comment_approved = '1'
				" . $sqlPost . "
			ORDER BY comment_date_gmt DESC
			LIMIT 0," . $size);
	
		foreach ($comments as $comment) {
			$out[] = array(
				'id' => $comment->comment_ID,
				'author' => $comment->comment_author,
				'email' => $comment->comment_author_email,
				'md5' => md5($comment->comment_author_email),
				'date' => $comment->comment_date,
				'content' => $comment->comment_content,
				'post' => array(
					'id' => $comment->comment_post_ID,
					'title' => $comment->post_title,
					'comments' => $comment->comment_count
				)
			);
		}
	
		return $out;
	}

	/****************************************************************************************************
	 * Get comments list array.
	 * Requires MySQL 4.1+ (nested queries, but just two calls).
	 *
	 * @param		number of comments to retrieve
	 * @return	array
	 */
	function wpp_get_last_comments_grouped($size = 10) {
		global $wpdb;
		$out = array();
	
		$sqlPost = "";
		if ($id > 0) $sqlPost = "AND p.ID = '" . $id . "'";
	
		// ****** Get the ID of the Last Comment for Each Post (sorted by Comment Date DESC)
		$last = $wpdb->get_results("
			SELECT
				c.comment_ID, c.comment_post_ID
			FROM " . $wpdb->comments . " as c
			INNER JOIN
				(SELECT MAX(comment_ID) AS comment_ID FROM " . $wpdb->comments . " GROUP BY comment_post_ID) cg
				ON cg.comment_ID = c.comment_ID
			WHERE
				comment_approved = '1'
			ORDER BY comment_date_gmt DESC
			LIMIT 0," . $size);
	
		$where = '';
		foreach ($last as $comment) {
			if ($where) $where .= ' OR ';
			$where .= "comment_ID = '" . $comment->comment_ID . "'";
		}
		$where = '(' . $where . ')';
	
		// ****** Get the Last Comments details
		$comments = $wpdb->get_results("
			SELECT
				c.comment_ID, c.comment_author, c.comment_author_email,
				c.comment_date, c.comment_content, c.comment_post_ID,
				p.post_title, p.comment_count
			FROM " . $wpdb->comments . " as c
			INNER JOIN " . $wpdb->posts . " as p ON c.comment_post_ID = p.ID
			WHERE
				comment_approved = '1' AND
				" . $where . "
			ORDER BY comment_date_gmt DESC
			LIMIT 0," . $size);
	
		foreach ($comments as $comment) {
			$out[] = array(
				'id' => $comment->comment_ID,
				'author' => $comment->comment_author,
				'email' => $comment->comment_author_email,
				'md5' => md5($comment->comment_author_email),
				'date' => $comment->comment_date,
				'post' => array(
					'id' => $comment->comment_post_ID,
					'title' => $comment->post_title,
					'comments' => $comment->comment_count
				)
			);		
		}
	
		return $out;
	}
}
?>