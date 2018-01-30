<?php
/*
Plugin Name: Fin Simple Breadcrumbs
Description: This plugin adds customizable shortcode for generating breadcrumbs. Based on Really Simple Breadcrumbs by Christoph Weil.
Text Domain: fin-simple-breadcrumbs
Version: 1.0.3
Author: ikenfin
Author URI: https://ikfi.ru
Update Server: 
Min WP Version: 3.2.1
Max WP Version: 
*/

/*
	Plugin constants
*/
define('DEFAULT_FIN_SB_ALLOWED_TAGS', 'ol, ul, div');
define('DEFAULT_FIN_SB_SEPARATOR', '&raquo;');
define('DEFAULT_FIN_SB_CONTAINER', 'ul');
define('DEFAULT_FIN_SB_CONTAINER_CLASS', 'breadcrumbs');
define('DEFAULT_FIN_SB_LINK_CONTAINER', '<li><a class="%1$s" href="%2$s">%3$s</a></li>');
define('DEFAULT_FIN_SB_LINK_CONTAINER_CLASS', '');
define('DEFAULT_FIN_SB_END_LINK_CONTAINER', '<li><span class="%1$s">%2$s</span></li>');
define('DEFAULT_FIN_SB_END_LINK_CONTAINER_CLASS', 'last');

// create custom plugin settings menu
add_action('admin_menu', 'fin_simple_breadcrumbs_admin_menu');

function fin_simple_breadcrumbs_admin_menu() {
	add_options_page(
		'Options',
		'Fin Simple Breadcrumbs',
		'manage_options',
		'fin_simple_breadcrumbs.php',
		'fin_simple_breadcrumbs_settings_page'
	);

	//call register settings function
	add_action( 'admin_init', 'fin_simple_breadcrumbs_admin_init' );
}


function fin_simple_breadcrumbs_admin_init() {
	register_setting('fin_simple_breadcrumbs_settings', 'fin_simple_breadcrumbs_allowed_tags', 'strval');
	register_setting('fin_simple_breadcrumbs_settings', 'fin_simple_breadcrumbs_separator', 'strval');
	register_setting('fin_simple_breadcrumbs_settings', 'fin_simple_breadcrumbs_container', 'strval');
	register_setting('fin_simple_breadcrumbs_settings', 'fin_simple_breadcrumbs_container_class', 'strval');
	register_setting('fin_simple_breadcrumbs_settings', 'fin_simple_breadcrumbs_link_container', 'strval');
	register_setting('fin_simple_breadcrumbs_settings', 'fin_simple_breadcrumbs_link_class', 'strval');
	register_setting('fin_simple_breadcrumbs_settings', 'fin_simple_breadcrumbs_end_link_container', 'strval');
	register_setting('fin_simple_breadcrumbs_settings', 'fin_simple_breadcrumbs_end_link_container_class', 'strval');

	// кастомные страницы архивов для таксономий
	register_setting('fin_simple_breadcrumbs_settings', 'fin_simple_breadcrumbs_taxonomy_custom_pages');
}

function fin_simple_breadcrumbs_get_taxonomy_archive_page ($taxonomy) {
	$options = get_option('fin_simple_breadcrumbs_taxonomy_custom_pages', array());
	return isset($options[$taxonomy]) ? $options[$taxonomy] : null;
}

/*
	Register shortcode
*/
add_shortcode('fin_simple_breadcrumbs', 'fin_simple_breadcrumbs_shortcode');

function fin_simple_breadcrumbs_shortcode($atts = array()) {

	$defaults = array(
		'container' => get_option('fin_simple_breadcrumbs_container', DEFAULT_FIN_SB_CONTAINER),
		'container_class' => get_option('fin_simple_breadcrumbs_container_class', DEFAULT_FIN_SB_CONTAINER_CLASS),
		'link_container' => get_option('fin_simple_breadcrumbs_link_container', DEFAULT_FIN_SB_LINK_CONTAINER),
		'link_class' => get_option('fin_simple_breadcrumbs_link_class', DEFAULT_FIN_SB_LINK_CONTAINER_CLASS),
		'end_link_container' => get_option('fin_simple_breadcrumbs_end_link_container', DEFAULT_FIN_SB_END_LINK_CONTAINER),
		'end_link_class' => get_option('fin_simple_breadcrumbs_end_link_container_class', DEFAULT_FIN_SB_END_LINK_CONTAINER_CLASS),
		'separator' => get_option('fin_simple_breadcrumbs_separator', DEFAULT_FIN_SB_SEPARATOR),
		'custom_taxonomy' => false
	);

	$args = shortcode_atts($defaults, $atts);

    global $post;
	$separator = $opts['separator'];
	
	$tree_html = '';

	$container_close_tag = '';

	$allowed_tags = preg_split("/\, /", get_option('fin_simple_breadcrumbs_allowed_tags', 'ul, ol, nav, div'), -1,PREG_SPLIT_NO_EMPTY);

	if($args['container'] && in_array($args['container'], $allowed_tags)) {
		$container_close_tag = '</' . $args['container'] . '>';

		$tree_html .= '<' . $args['container'];
		if(trim($args['container_class']) != '') {
			$tree_html .= ' class="' . esc_attr($args['container_class']) . '"';
		}
		$tree_html .= '>';
	}

	$home = get_page(get_option('page_on_front'));

	if(!is_front_page()) {
		$tree_html .= sprintf($args['link_container'], $args['link_class'], get_home_url(), get_the_title(get_option('page_on_front'), true));

		$tree_html .= $args['separator'];

		/*
			Страница категории
		*/
		if(is_single()) {
			/*
				Integration with Custom Post Type Parents plugin
			*/
			if(class_exists('Custom_Post_Type_Parents')) {
				$cptp = Custom_Post_Type_Parents::get_instance();

				if($cptp->has_assigned_parent($post->post_type)) {
					$parents = array_filter(array_reverse($cptp->get_ancestor_ids($post->post_type)));

					foreach($parents as $parent) {
						// prevent duplicates (if page is child of home page)
						if (trim(get_permalink($parent), '/') == get_home_url())
							continue;

						$tree_html .= sprintf($args['link_container'], $args['link_class'], get_permalink($parent), get_the_title($parent));
					}
				}
			}

			if($args['custom_taxonomy']) {
				$terms = wp_get_post_terms($post->ID, $args['custom_taxonomy']);
				if(count($terms) > 0) {
					$term = $terms[0];
					$parents = get_ancestors($term->term_id, $args['custom_taxonomy']);
					
					foreach($parents as $parent) {
						$pTerm = get_term($parent);
						$tree_html .= sprintf($args['link_container'], $args['link_class'], get_term_link($parent), $pTerm->name);
					}

					$tree_html .= sprintf($args['link_container'], $args['link_class'], get_term_link($term->term_id), $term->name);
				}
			}

			foreach(get_the_category() as $category) {
				$tree_html .= sprintf($args['link_container'], $args['link_class'], get_term_link($category->cat_ID), $category->name);
			}

			$tree_html .= $args['separator'];
			$tree_html .= sprintf($args['end_link_container'], $args['end_link_class'], get_the_title());
		}
		/*
			Страница записей
		*/
		elseif(is_home()) {
			$home_page_id = get_option('page_for_posts');
			// if not using static page for posts, there will be 0
			if($home_page_id) {
				$tree_html .= sprintf($args['end_link_container'], $args['end_link_class'], get_the_title($home_page_id));
			}
		}
		/*
			Страница таксономии
		*/
		elseif(is_category() || is_tax()) {
			$currentTaxonomy = get_queried_object();
			$parents = get_ancestors($currentTaxonomy->term_id, $currentTaxonomy->taxonomy);

			$customArchivePage = fin_simple_breadcrumbs_get_taxonomy_archive_page($currentTaxonomy->taxonomy);

			if ($customArchivePage) {
				$page = get_page($customArchivePage);
				if ($home->ID != $page->ID) {
					$tree_html .= sprintf($args['link_container'], $args['link_class'], get_permalink($page), get_the_title($page));
						$tree_html .= $opts['separator'];
				}

				for ($i = count($page->ancestors)-1; $i >= 0; $i--) {
					if (($home->ID) != ($page->ancestors[$i])) {
						$tree_html .= sprintf($args['link_container'], $args['link_class'], get_permalink($page->ancestors[$i]), get_the_title($page->ancestors[$i]));
						$tree_html .= $opts['separator'];
					}
				}
			}

			foreach($parents as $parent) {
				$pTerm = get_term($parent);

				$tree_html .= sprintf($args['link_container'], $args['link_class'], get_term_link($parent), $pTerm->name);
			}

			$tree_html .= sprintf($args['end_link_container'], $args['end_link_class'], $currentTaxonomy->name);
		}
		/*
			Подстраница -> Страница
		*/
		elseif(is_page() && $post->post_parent) {
			for ($i = count($post->ancestors)-1; $i >= 0; $i--) {
				if (($home->ID) != ($post->ancestors[$i])) {
					$tree_html .= sprintf($args['link_container'], $args['link_class'], get_permalink($post->ancestors[$i]), get_the_title($post->ancestors[$i]));
					$tree_html .= $opts['separator'];
				}
			}

			$tree_html .= sprintf($args['end_link_container'], $args['end_link_class'], get_the_title());
		}
		/*
			Страница
		*/
		elseif(is_page()) {
			$tree_html .= sprintf($args['end_link_container'], $args['end_link_class'], get_the_title());
		}
		/*
			404
		*/
		elseif(is_404()) {
			$tree_html .= sprintf($args['end_link_container'], $args['end_link_class'], get_the_title());
		}
	}
	else {
		$tree_html .= get_bloginfo('name');
	}

	$tree_html .= $container_close_tag;

	return $tree_html;
}



// admin settings page
function fin_simple_breadcrumbs_settings_page() {
	$taxonomies = get_taxonomies();
	$taxonomy_pages = get_option('fin_simple_breadcrumbs_taxonomy_custom_pages', array());
?>
<div class="wrap">
    <h1>Fin simple breadcrumbs settings</h1>

    <form method="POST" action="options.php">
        <?php settings_fields('fin_simple_breadcrumbs_settings'); ?>
        <?php do_settings_sections('fin_simple_breadcrumbs_settings'); ?>
		<table class="form-table">
	        <tr valign="top">
				<th>Разрешенные теги</th>
				<td>
					<input type="text" name="fin_simple_breadcrumbs_allowed_tags" value="<?php echo esc_attr(get_option('fin_simple_breadcrumbs_allowed_tags', DEFAULT_FIN_SB_ALLOWED_TAGS)); ?>">
				</td>
	        </tr>
	        <tr valign="top">
				<th>Разделитель</th>
				<td>
					<input type="text" name="fin_simple_breadcrumbs_separator" value="<?php echo esc_attr(get_option('fin_simple_breadcrumbs_separator', DEFAULT_FIN_SB_SEPARATOR)); ?>">
				</td>
	        </tr>
	        <tr valign="top">
				<th>Контейнер по умолчанию</th>
				<td>
					<input type="text" name="fin_simple_breadcrumbs_container" value="<?php echo esc_attr(get_option('fin_simple_breadcrumbs_container', DEFAULT_FIN_SB_CONTAINER)); ?>">
				</td>
	        </tr>
	        <tr valign="top">
				<th>Класс контейнера по умолчанию</th>
				<td>
					<input type="text" name="fin_simple_breadcrumbs_container_class" value="<?php echo esc_attr(get_option('fin_simple_breadcrumbs_container_class', DEFAULT_FIN_SB_CONTAINER_CLASS)); ?>">
				</td>
	        </tr>
	        <tr valign="top">
				<th>Контейнер ссылки по умолчанию</th>
				<td>
					<input type="text" name="fin_simple_breadcrumbs_link_container" value="<?php echo esc_attr(get_option('fin_simple_breadcrumbs_link_container', '<a class="%1$s" href="%2$s">%3$s</a>')); ?>">
				</td>
	        </tr>
	        <tr valign="top">
				<th>Класс ссылки по умолчанию</th>
				<td>
					<input type="text" name="fin_simple_breadcrumbs_link_class" value="<?php echo esc_attr(get_option('fin_simple_breadcrumbs_link_class', DEFAULT_FIN_SB_LINK_CONTAINER_CLASS)); ?>">
				</td>
	        </tr>
	        <tr valign="top">
				<th>Контейнер конечного звена по умолчанию</th>
				<td>
					<input type="text" name="fin_simple_breadcrumbs_end_link_container" value="<?php echo esc_attr(get_option('fin_simple_breadcrumbs_end_link_container', DEFAULT_FIN_SB_END_LINK_CONTAINER)); ?>">
				</td>
	        </tr>
	        <tr valign="top">
				<th>Класс конечного звена по умолчанию</th>
				<td>
					<input type="text" name="fin_simple_breadcrumbs_end_link_container_class" value="<?php echo esc_attr(get_option('fin_simple_breadcrumbs_end_link_container_class', DEFAULT_FIN_SB_END_LINK_CONTAINER_CLASS)); ?>">
				</td>
	        </tr>

	        <tr valign="top">
	        	<th>Архивные страницы таксономий</th>
	        </tr>

	        <?php foreach ($taxonomies as $tax) : ?>
			<tr valign="top">
				<th><?php echo $tax; ?></th>
				<td>
					<?php 
						wp_dropdown_pages(array(
							'name' => 'fin_simple_breadcrumbs_taxonomy_custom_pages[' . $tax . ']',
							'show_option_none' => 'Не выбрано',
							'selected' => isset($taxonomy_pages[$tax]) ? $taxonomy_pages[$tax] : ''
						));
					?>
				</td>
    		</tr>
    		<?php endforeach; ?>
	    </table>

        <?php submit_button(); ?>
    </form>
</div>
<?php
}