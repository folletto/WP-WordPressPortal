<?php 
/*
Plugin Name: WordPress Portal
Plugin URI: http://digitalhymn.com/argilla/wpp
Description: This is a function library to ease themes development. It could be included in the theme or added as plugin. You can add an updated plugin to fix existing themes.
Author: Davide 'Folletto' Casali
Version: 0.8.1
Author URI: http://digitalhymn.com/
 ******************************************************************************************
 * WordPress Portal
 * WP Theming Functions Library
 * 
 * Last revision: 2009 01 25
 *
 * by Davide 'Folletto' Casali <folletto AT gmail DOT com>
 * www.digitalhymn.com
 * Copyright (C) 2006/2007 - Creative Commons (CC) by-sa 2.5
 * 
 * Based upon a library developed for key-one.it (Kallideas / Key-One)
 * Thanks to Roberto Ostinelli and Alessandro Morandi.
 *
 */

/*
 * SUMMARY:
 *  wpp::foreach_post($filter, $limit): creates a custom TheLoop, with a filter match
 *  wpp::get_posts($filter, $limit): gets all the posts matching a filter
 *  wpp::foreach_attachment(): creates a custom TheLoop for the attachments, can be used inside TheLoop
 *  wpp::get_attachments($filter, $limit): gets all the posts matching a filter
 *  wpp::get_post_custom($custom, $before, $after, $optid): [TheLoop] gets the specified custom
 *  wpp::uri_category($field, $default): gets the category of the loaded page
 *  wpp::in_category($nicename): [TheLoop] checks if the posts belongs to that category
 *  wpp::is_term_child_of($child, $parent): checks if the category is child of another (nicename)
 *  wpp::get_page_content($nicename, $on_empty): gets the specified page content
 *  wpp::get_zone(): gets an array containing ['type' => '...', 'id' => 'n', 'terms' => array(...)]
 *  wpp::is_admin($userid): check if the current logged user is an "administrator"
 *  wpp::get_last_comments($size): gets all the last comments
 *  wpp::get_last_comments_grouped($size): gets the last comments, one comment per post
 *  wpp::get_pages_root(): gets the root page of the current page subtree
 *  wpp::list_pages_of_section(): like wp_list_pages() but getting only the pages of the section
 * 
 * DETAILS:
 * The most interesting function is the wpp_foreach_post() that in fact creates a custom
 * "The Loop", using the syntax:
 *          while(wpp::foreach_post(array(...), 10)) { ... }
 * 
 * The function wpp::get_zone() retrieves the correct term from the page currently loaded.
 * For every loaded page it tries to match a tag (from the 'category' taxonomy).
 * It is like an evolved $wp_query->get_queried_object_id().
 * This is *really* useful to create complex sites, using the page hierarchy as structure,
 * matching the page slug with a category slug, using the page content as the section body.
 * 
 */

if (!isset($WPP_VERSION) && !class_exists("wpp")) {
  $WPP_VERSION = 'WordPressPortal/0.8.1';
  
  class wpp {
    
    static $loops = array();
    static $loops_backups = array();
    static $virtual_page = array();
    
    function foreach_anything($loopname, $filter = array(), $limit = -1) {
      /****************************************************************************************************
       * Internal function.
       * Please use the specific functions: foreach_post, foreach_attachment.
       * Creates a custom The Loop to access anything: posts, pages, revisions, attachments.
       * Syntax:
       *   while(wpp::foreach_anything('posts', array(...), 10) { ... }
       * 
       * @param     loop name
       * @param      query parameteres array
       * @return    item or false
       */
      global $wp_query;
      // TheLoop variables
      global $post;
      global $previousday;
      
      $out = false;
      
      if (wpp::is_foreach_init_season($loopname)) {
        // *** Backup
        wpp::$loops_backups[$loopname] = array('post' => $post, 'previousday' => $previousday);
        
        // *** Filter
        if ($limit > 0) $filter['posts_per_page'] = $limit;
        
        // *** Make sure minimum defaults are used
        $defaults = array(
          'post_type' => 'any', // any | attachment | post | page
          'post_status' => 'published', // any | published | draft
          'post_parent' => 0,
        );
        
        // *** Query
        $args = wp_parse_args($filter, $defaults);
        wpp::$loops[$loopname] = new WP_Query($args);
      }
      
      // ****** Elaborate the custom The WPP Loop
      if (wpp::$loops[$loopname]->have_posts()) {
        // *** Next
        wpp::$loops[$loopname]->the_post();
        $out = $post;
      } else {
        // *** Reset
        unset(wpp::$loops[$loopname]); // kill custom loop
        $out = $post = null;
    
        // *** Restore backup
        $post = wpp::$loops_backups[$loopname]['post'];
        setup_postdata($post); // WP hook
        $previousday = wpp::$loops_backups[$loopname]['previousday'];
      }

      return $out;
    }
    function is_foreach_init_season($loopname) {
      /****************************************************************************************************
       * Internal function.
       * Checks if the named loop is already present (and so, able to loop).
       * 
       * @param     internal loop name
       * @return    boolean
       */
      return (!isset(wpp::$loops[$loopname]) || wpp::$loops[$loopname] === null);
    }
    
    function foreach_post($filter = array(), $limit = null) {
      /****************************************************************************************************
       * Creates a custom The Loop (i.e. like: while (have_posts()) : the_post(); [...] endwhile;).
       * Syntax:
       *   while(wpp::foreach_post(array(...), 10)) { ... }
       * 
       * @param     query parameteres array
       * @param      limit number of items
       * @return    item or false
       */
      $loopname = 'posts';
      if (wpp::is_foreach_init_season($loopname)) {
        if (!is_array($filter)) $filter = array();
        
        if (isset($filter['category'])) {
          $filter['category_name'] = $filter['category'];
          unset($filter['category']); // kill shortcut
        }
        if (isset($filter['page'])) {
          $filter['post_name'] = $filter['page'];
          unset($filter['page']); // kill shortcut
        }
        
        if (!isset($filter['post_type'])) {
          $filter['post_type'] = 'post';
        }
      }
      
      return wpp::foreach_anything($loopname, $filter, $limit);
    }
    function get_posts($filter, $limit = null) {
      /****************************************************************************************************
       * Gets all the posts into an array. Wraps wpp_foreach_post().
       *
       * @param      filter string (SQL WHERE) or array (converted to SQL WHERE, AND of equals (==))
       * @param      limit string (i.e. 1 or 1,10)
       * @return    posts array
       */
      $posts = array();

      while ($post = wpp::foreach_post($filter, $limit)) {
        $posts[] = $post;
      }

      return $posts;
    }
    
    function foreach_attachment($filter = array(), $limit = -1) {
      /****************************************************************************************************
       * Creates a custom The Loop to list attachments of the current post (or the passed one)
       * Syntax:
       *   while(wpp::foreach_attachment(array(...), 10)) { ... }
       * 
       * @param     query parameteres array
       * @param      limit number of items
       * @return    item or false
       */
      global $post;
      
      $loopname = 'attachments';
      if (wpp::is_foreach_init_season($loopname)) {
        if (!is_array($filter)) $filter = array();
        
        if (!isset($filter['post_parent'])) $filter['post_parent'] = $post->ID;
        
        if (isset($filter['name'])) {
          $filter['s'] = $filter['name'];
          unset($filter['name']); // kill shortcut
        }
        if (isset($filter['s'])) $filter['exact'] = true;
        
        $filter['post_type'] = 'attachment';
        $filter['post_status'] = 'any';
        $filter['orderby'] = "menu_order ASC, title DESC";
      }
      
      return wpp::foreach_anything($loopname, $filter, $limit);
    }
    function get_attachments($filter = array(), $limit = -1) {
      /****************************************************************************************************
       * Gets all the posts into an array. Wraps wpp_foreach_post().
       *
       * @param      filter string (SQL WHERE) or array (converted to SQL WHERE, AND of equals (==))
       * @param      limit string (i.e. 1 or 1,10)
       * @return    posts array
       */
      $posts = array();

      while ($post = wpp::foreach_attachment($filter, $limit)) {
        $posts[] = $post;
      }

      return $posts;
    }
    
    function get_post_custom($custom, $before = '', $after = '', $optid = 0) {
      /****************************************************************************************************
       * Get a specific custom item, optionally wrapped between two text strings.
       * Works inside The Loop only. To be used used outside specify the optional id parameter.
       *
       * @param      custom field
       * @param      before html
       * @param      after html
       * @param      optional id (to fetch the custom of a different post)
       * @return    html output
       */
      global $id;

      $out = '';
      if ($id && !$optid) $optid = $id;

      $custom_fields = get_post_custom($optid);

      if (isset($custom_fields[$custom])) {
        $out = $before . $custom_fields[$custom][0] . $after;
      }

      return $out;
    }
    
    function get_terms_recursive($ref, $levels = -1, $taxonomy = 'category') {
      /****************************************************************************************************
       * Get the terms matching the nicename (slug) and all its CHILDREN in a flat array.
       *
       * @param      term nicename (slug)
       * @param      (optional) depth of recursion (defult: -1, ALL)
       * @param      (optional) taxonomy, defaults to 'category'
       * @return    array of raw term rows
       */
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
              $out = array_merge($out, wpp::get_terms_recursive($term->term_id, $levels, $taxonomy));
            }
          }
        }
      }
    
      return $out;
    }
    function in_category($nicename) {
      /****************************************************************************************************
       * Checks if the post in the_loop belongs to the specified category nicename.
       * Different from in_category(), that checks for the id, not for the nicename.
       *
       * @param    container category nicename (slug)
       * @param    (optional) optional parent nicename
       * @return  boolean
       */
      return wpp::is_term_child_of($nicename, get_the_category());
    }
    function is_term_child_of($child_term, $parent_term) {
      /****************************************************************************************************
       * Checks if a category is child of another. Counts also self as true.
       *
       * @param    child category
       * @param    parent category (or array)
       * @return  boolean true
       */
      if (is_array($parent_term)) {
        $terms = $parent_term;
      } else {
        $terms = wpp::get_terms_recursive(strval($parent_term));
      }

      foreach ($terms as $term) {
        if ($child_term == $term->slug) {
          return true;
        }
      }
  
      return false;
    }
    
    function get_page_content($nicename, $on_empty = "The page '%s' is empty.") {
      /****************************************************************************************************
       * Returns the specified page, given a nicename.
       *
       * @param      page nicename
       * @param      (optional) message on non-existing page
       * @return    page content string
       */
      $out = '';
    
      $posts = wpp::get_posts(array('page' => $nicename));
      if ($posts[0]->post_content)
        $out = $posts[0]->post_content;
      else
        $out = sprintf($on_empty, $nicename);
    
      return $out;
    }

    function get_zone($key = null, $taxonomy = 'category') {
      /****************************************************************************************************
       * Return the type of the 'zone' where we are, the matching id reference and the associated terms
       * It's like an improved $wp_query->get_queried_object_id().
       *
       * - returned zones: page, post, author, search, category, date, tag, home
       * (matching is_page, is_single, is_author, is_search, is_category, is_date, is_tag, is_home)
       *
       * @param      (optional) array shortcut (i.e. wpp_get_zone('id'))
       * @return    array ['type' => '...', 'id' => 'n', 'terms' => array(...)]
       */
      global $wp_query;
      
      $out = array(
        'type' => 'none',
        'id' => 0,
        'terms' => array(),
        'taxonomy' => $taxonomy
        );
      
      global $__cache_wpp_get_zone; // Cache
      if (!is_array($__cache_wpp_get_zone) || $__cache_wpp_get_zone['taxonomy'] != $taxonomy) {
        if (is_page()) {
          // *** We're in a PAGE
          global $post;
          $out['type'] = 'page';
          $out['id'] = $post->ID;
          $out['terms'] = array(get_term_by('slug', $post->post_name, $taxonomy));
        } else if (is_single()) {
          // *** We're in a POST
          global $post;
          $out['type'] = 'page';
          $out['id'] = $post->ID;
          $out['terms'] = wp_get_object_terms($post->ID, $taxonomy);
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
          $out['terms'] = array(get_term_by('slug', $s, $taxonomy));
        } else if (is_category()) {
          // *** We're in a CATEGORY
          global $cat;
          $out['type'] = 'cat';
          $out['id'] = $cat;
          $out['terms'] = array(get_term($cat, $taxonomy));
        } else if (is_date()) {
          // *** We're in a DATE
          global $m, $year, $monthnum;
          $out['type'] = 'date';
          $out['id'] = ($m ? $m : $year . str_pad($monthnum, 2, '0', STR_PAD_LEFT));
        } else if (is_tag()) {
          // *** We're in a TAG
          global $tag, $tag_id;
          $out['type'] = 'tag';
          $out['id'] = $tag;
          $out['terms'] = array(get_term($tag_id, $taxonomy));
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
        
        // Cleanup
        if (isset($out['terms'][0]) && $out['terms'][0] == false) $out['terms'] = array();
        
        $__cache_wpp_get_zone = $out; // <-- Cache
      } else {
        $out = $__cache_wpp_get_zone; // --> Cache
      }
    
      // ****** Return
      if ($key === null) return $out;
      return $out[$key];
    }
    
    function is_admin($uid = 0) {
      /****************************************************************************************************
       * Checks if the specified user ID is an admin user
       *
       * @param    user id (0 for current logged user)
       * @return  boolean
       */
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

    function get_last_comments($size = 10, $id = 0) {
      /****************************************************************************************************
       * Get comments list array.
       *
       * @param    number of comments to retrieve
       * @param    optional post ID to relate comments
       * @return  array
       */
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
    function get_last_comments_grouped($size = 10) {
      /****************************************************************************************************
       * Get comments list array.
       * Requires MySQL 4.1+ (nested queries, but just two calls).
       *
       * @param    number of comments to retrieve
       * @return  array
       */
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
    
    function get_pages_root() {
      /****************************************************************************************************
       * Returns the page at the top of the current pages subtree.
       * Copyright (C) 2007 + GNU/GPL2 by Roberto Ostinelli [http://www.ostinelli.net]
       * Modified by Davide 'Folletto' Casali.
       * 
       * @return  returns array('root', 'levels'), where root is a partial page object.
       */
      global $wp_query, $wpdb, $post;
      
      $out = array(
        'page' => null,
        'levels' => 0
      );
      
      global $__cache_wpp_list_pages_of_section; // Cache
      if (!is_array($__cache_wpp_list_pages_of_section)) {
        // *** Get all the pages
        $query = "
          SELECT ID, post_parent, post_title, post_name, post_type
          FROM $wpdb->posts WHERE post_type = 'page' AND post_status = 'publish'
        ";
        if ($post->post_type == 'page' && $results = $wpdb->get_results($query)) {
          // *** Generate (key, value) pairs
          $pages = array();
          foreach ($results as $result) {
            $pages[$result->ID] = $result;
          }
          // *** Walk the "tree" up to root
          $root = $post;
          while($root->post_parent) {
            $root = $pages[$root->post_parent];
            $out['levels']++;
          }
      
          $out['page'] = $root;
        }
        
        $__cache_wpp_list_pages_of_section = $out; // <-- Cache
      } else {
        $out = $__cache_wpp_list_pages_of_section; // --> Cache
      }
      
      // ****** Closing
      return $out;
    }
    function list_pages_of_section($arguments = '&title_li=') {
      /****************************************************************************************************
       * Echoes (HTML) the pages under the same parent page.
       * Copyright (C) 2007 + GNU/GPL2 by Roberto Ostinelli [http://www.ostinelli.net]
       * Modified by Davide 'Folletto' Casali.
       * 
       * @param    (optional) formatting arguments for wp_list_pages()
       * @param    (optional) boolean false to disable echo and trigger return data behaviour
       */
      $root = wpp::get_pages_root();
      return wp_list_pages($arguments . "&child_of=" . $root['page']->ID);
    }
    
    function add_virtual_page($url, $handlers = array()) {
      /****************************************************************************************************
       * Dynamically inject URL handlers inside WP structure.
       * 
       * @param    virtual URL to be handled (i.e. 'path/to/handle')
       * @param    php pages to be called (i.e. array(get_template_directory() . "/virtual.php", dirname(__FILE__) . "/virtual.php"));
       */
      if (is_array($handlers) && sizeof($handlers)) {
        // ****** Prepare data
        $url = rtrim($url, "/");
        wpp::$virtual_page[$url] = array(
          'handlers' => $handlers,
        );
        
  			// ****** SmartAss Rewrite
  			$url_wanted = rtrim(dirname($_SERVER['PHP_SELF']), "/") . "/" . $url;
  			$url_requested = substr(trim($_SERVER['REQUEST_URI']), 0, strlen($url_wanted));
  			if ($url_wanted == $url_requested) {
  				// ****** Add Filter
  				$pages = "";
  				$fx = create_function('$handler', '
  				  $out = $handler;
  				  
        		foreach (array("' . join($handlers, '", "') . '") as $custom_template) {
        			if (file_exists($custom_template)) {
        				$out = $custom_template;
        				break;
        			}
        		}

        		return $out;
  				');
  				add_filter('404_template', $fx);
          
  				// ****** Service Operations
  				wpp::$virtual_page[$url]['purl'] = explode("/", substr(trim($_SERVER['REQUEST_URI']), strlen($url_wanted) + 1));
  			}
      }
    }
  }
}

?>