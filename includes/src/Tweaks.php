<?php
/**
 * Docket Cache.
 *
 * @author  Nawawi Jamili
 * @license MIT
 *
 * @see    https://github.com/nawawi/docket-cache
 */

namespace Nawawi\DocketCache;

\defined('ABSPATH') || exit;

final class Tweaks
{
    public function wpquery()
    {
        // vipcom: prevent core from doing filename lookups for media search.
        // https://core.trac.wordpress.org/ticket/39358
        add_action(
            'pre_get_posts',
            function () {
                remove_filter('posts_clauses', '_filter_query_attachment_filenames');
            },
            PHP_INT_MAX
        );

        // vipcom: improve perfomance of the _WP_Editors::wp_link_query method
        add_filter(
            'wp_link_query_args',
            function ($query) {
                $query['no_found_rows'] = true;

                return $query;
            },
            PHP_INT_MAX
        );

        // vipcom: disable custom fields meta box dropdown (very slow)
        add_filter('postmeta_form_keys', '__return_false');
    }

    public function misc()
    {
        // wp: if only one post is found by the search results, redirect user to that post
        add_action(
            'template_redirect',
            function () {
                if (is_search()) {
                    global $wp_query;
                    if (1 === (int) $wp_query->post_count && 1 === (int) $wp_query->max_num_pages) {
                        wp_redirect(get_permalink($wp_query->posts['0']->ID));
                        exit;
                    }
                }
            },
            PHP_INT_MAX
        );

        // wp: hide update notifications to non-admin users
        add_action(
            'admin_head',
            function () {
                if (!current_user_can('update_core')) {
                    remove_action('admin_notices', 'update_nag', 3);
                }
            },
            PHP_INT_MAX
        );

        // jetpack: enables object caching for the response sent by instagram when querying for instagram image html
        // https://developer.jetpack.com/hooks/instagram_cache_oembed_api_response_body/
        // Removed in Jetpack 9.1.0
        //add_filter('instagram_cache_oembed_api_response_body', '__return_true');

        if (nwdcx_consfalse('TWEAKS_WPCOOKIE_DISABLED')) {
            // wp: comment cookie lifetime, default to 30000000 second = 12 months
            add_filter(
                'comment_cookie_lifetime',
                function () {
                    return 12 * HOUR_IN_SECONDS;
                },
                -PHP_INT_MAX
            );

            // wp: protected post, expire when browser close
            add_filter(
                'post_password_expires',
                function () {
                    return 0;
                },
                -PHP_INT_MAX
            );
        }
    }

    public function headerjunk()
    {
        // wp: header junk
        add_action(
            'after_setup_theme',
            function () {
                remove_action('wp_head', 'rsd_link');
                remove_action('wp_head', 'wp_generator');
                remove_action('wp_head', 'feed_links', 2);
                remove_action('wp_head', 'feed_links_extra', 3);
                remove_action('wp_head', 'index_rel_link');
                remove_action('wp_head', 'wlwmanifest_link');
                remove_action('wp_head', 'start_post_rel_link', 10, 0);
                remove_action('wp_head', 'parent_post_rel_link', 10, 0);
                remove_action('wp_head', 'adjacent_posts_rel_link', 10, 0);
                remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
                remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
            },
            PHP_INT_MAX
        );

        add_filter('the_generator', '__return_empty_string', PHP_INT_MAX);
        add_filter('x_redirect_by', '__return_false', PHP_INT_MAX);
    }

    public function pingback()
    {
        // wp: disable pingback
        add_action(
            'pre_ping',
            function (&$links) {
                foreach ($links as $l => $link) {
                    if (0 === strpos($link, get_option('home'))) {
                        unset($links[$l]);
                    }
                }
            },
            PHP_INT_MAX
        );

        // wp: disable and remove do_pings
        // https://wp-mix.com/wordpress-clean-up-do_pings/
        add_action(
            'plugins_loaded',
            function () {
                if (isset($_GET['doing_wp_cron'])) {
                    remove_action('do_pings', 'do_all_pings');
                    wp_clear_scheduled_hook('do_pings');
                }
            },
            PHP_INT_MAX
        );

        // wp: disable xmlrpc
        // https://www.wpbeginner.com/plugins/how-to-disable-xml-rpc-in-wordpress/
        // https://kinsta.com/blog/xmlrpc-php/
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('pre_update_option_enable_xmlrpc', '__return_false');
        add_filter('pre_option_enable_xmlrpc', '__return_zero');

        // additional
        add_filter('pings_open', '__return_false');
        add_filter('pre_option_default_ping_status', '__return_zero');
        add_filter('pre_option_default_pingback_flag', '__return_zero');
        add_filter(
            'xmlrpc_methods',
            function ($methods) {
                unset($methods['pingback.ping']);
                unset($methods['pingback.extensions.getPingbacks']);
                unset($methods['wp.getUsersBlogs']);
                unset($methods['system.multicall']);
                unset($methods['system.listMethods']);
                unset($methods['system.getCapabilities']);
                unset($methods['demo.sayHello']);

                return $methods;
            }
        );

        add_action(
            'xmlrpc_call',
            function ($method) {
                if ('pingback.ping' !== $method) {
                    return;
                }
                http_response_code(403);
                exit('This site does not have pingback.');
            }
        );

        add_filter(
            'template_redirect',
            function () {
                header_remove('X-Pingback');
            },
            PHP_INT_MAX
        );

        add_filter(
            'wp_headers',
            function ($headers) {
                unset($headers['X-Pingback']);

                return $headers;
            },
            PHP_INT_MAX
        );

        add_action(
            'plugins_loaded',
            function () {
                if (isset($_SERVER['REQUEST_URI']) && '/xmlrpc.php' === $_SERVER['REQUEST_URI']) {
                    http_response_code(403);
                    exit('xmlrpc.php not available.');
                }

                // additional
                if (isset($_SERVER['SCRIPT_FILENAME']) && 'xmlrpc.php' === basename($_SERVER['SCRIPT_FILENAME'])) {
                    http_response_code(403);
                    exit('xmlrpc.php not available.');
                }
            },
            PHP_INT_MAX
        );
    }

    private function has_woocommerce()
    {
        return isset($GLOBALS['woocommerce']) && \is_object($GLOBALS['woocommerce']);
    }

    public function woocommerce_misc()
    {
        // wc: action_scheduler_migration_dependencies_met
        add_filter('action_scheduler_migration_dependencies_met', '__return_false', PHP_INT_MAX);

        // wc: disable background image regeneration
        add_filter('woocommerce_background_image_regeneration', '__return_false', PHP_INT_MAX);

        // wc: remove marketplace suggestions
        // https://rudrastyh.com/woocommerce/remove-marketplace-suggestions.html
        add_filter('woocommerce_allow_marketplace_suggestions', '__return_false', PHP_INT_MAX);

        // wc: remove connect your store to WooCommerce.com admin notice
        add_filter('woocommerce_helper_suppress_admin_notices', '__return_true', PHP_INT_MAX);

        // wc: disable the WooCommere Marketing Hub
        add_filter(
            'woocommerce_admin_features',
            function ($features) {
                $marketing = array_search('marketing', $features);
                unset($features[$marketing]);

                return $features;
            },
            PHP_INT_MAX
        );
        add_filter('woocommerce_marketing_menu_items', '__return_empty_array', PHP_INT_MAX);

        // jetpack
        add_filter('jetpack_just_in_time_msgs', '__return_false', PHP_INT_MAX);
        add_filter('jetpack_show_promotions', '__return_false', PHP_INT_MAX);
    }

    public function woocommerce_admin_disabled()
    {
        // wc: disable the WooCommerce Admin
        add_filter('woocommerce_admin_disabled', '__return_true', PHP_INT_MAX);
    }

    public function woocommerce_dashboard_status_remove()
    {
        add_action(
            'wp_dashboard_setup',
            function () {
                if (!$this->has_woocommerce()) {
                    return;
                }

                remove_meta_box('woocommerce_dashboard_status', 'dashboard', 'normal');
                remove_meta_box('woocommerce_dashboard_recent_reviews', 'dashboard', 'normal');
                remove_meta_box('woocommerce_network_orders', 'dashboard', 'normal');
            },
            PHP_INT_MAX
        );
    }

    public function woocommerce_widget_remove()
    {
        add_action(
            'widgets_init',
            function () {
                if (!$this->has_woocommerce()) {
                    return;
                }

                // includes/wc-widget-functions.php
                $widgets = [
                    'WC_Widget_Cart',
                    'WC_Widget_Layered_Nav_Filters',
                    'WC_Widget_Layered_Nav',
                    'WC_Widget_Price_Filter',
                    'WC_Widget_Product_Categories',
                    'WC_Widget_Product_Search',
                    'WC_Widget_Product_Tag_Cloud',
                    'WC_Widget_Products',
                    'WC_Widget_Recently_Viewed',
                    'WC_Widget_Top_Rated_Products',
                    'WC_Widget_Recent_Reviews',
                    'WC_Widget_Rating_Filter',
                ];
                foreach ($widgets as $widget) {
                    // remove
                    unregister_widget($widget);

                    // prevent error notice _doing_it_wrong
                    // see wp-includes/widgets.php -> the_widget()
                    register_widget($widget, null);
                }
            },
            PHP_INT_MAX
        );
    }

    public function woocommerce_cart_fragments_remove()
    {
        add_action(
            'wp_enqueue_scripts',
            function () {
                $id = 'wc-cart-fragments';
                $wp_scripts = $GLOBALS['wp_scripts'];
                $src = $wp_scripts->registered[$id]->src;
                $wp_scripts->registered[$id]->src = null;

                $code = '(function() {';
                $code .= 'var checkhash = function() {';
                $code .= 'var n = "woocommerce_cart_hash";';
                $code .= 'var h = document.cookie.match("(^|;) ?" + n + "=([^;]*)(;|$)");';
                $code .= 'return h ? h[2] : null;';
                $code .= '};';
                $code .= 'var checkscript = function() {';
                $code .= 'var src = "'.$src.'";';
                $code .= 'var id = "docket-cache-wccartfragment";';
                $code .= 'if ( null !== document.getElementById(id) ) {';
                $code .= 'return false;';
                $code .= 'if ( checkhash() ) {';
                $code .= 'var script = document.createElement("script");';
                $code .= 'script.id = id;';
                $code .= 'script.src = src;';
                $code .= 'script.async = true;';
                $code .= 'document.head.appendChild(script);';
                $code .= '';
                $code .= '}';
                $code .= '}';
                $code .= '};';
                $code .= 'checkscript();';
                $code .= 'document.addEventListener("click", function(){setTimeout(checkscript,1000);});';
                $code .= '})();';
                wp_add_inline_script('jquery', $code);
            },
            999
        );
    }

    public function post_missed_schedule()
    {
        if (!nwdcx_wpdb($wpdb)) {
            return false;
        }

        $suppress = $wpdb->suppress_errors(true);

        // check
        $query = "SELECT ID FROM `{$wpdb->posts}` WHERE post_status='future' ORDER BY ID ASC LIMIT 1";
        $check = $wpdb->query($query);

        if ($check < 1) {
            return false;
        }

        $limit = 1000;

        $now = gmdate('Y-m-d H:i:59');
        $args = [
            'public' => true,
            'exclude_from_search' => false,
            '_builtin' => false,
        ];

        $post_types = get_post_types($args, 'names', 'and');
        if (!empty($post_types) && \is_array($post_types)) {
            $types = implode("','", $post_types);
            $query = $wpdb->prepare("SELECT ID FROM `{$wpdb->posts}` WHERE post_type in ('post','page','%s') AND post_status='future' AND post_date_gmt < %s ORDER BY ID ASC LIMIT %d", $types, $now, $limit);
        } else {
            $query = $wpdb->prepare("SELECT ID FROM `{$wpdb->posts}` WHERE post_type in ('post','page') AND post_status='future' AND post_date_gmt < %s ORDER BY ID ASC LIMIT %d", $now, $limit);
        }

        $results = $wpdb->get_results($query, ARRAY_A);

        if (!empty($results)) {
            while ($row = @array_shift($results)) {
                $id = $row['ID'];
                wp_publish_post($id);
            }
        }

        $wpdb->suppress_errors($suppress);

        return true;
    }

    public function wpemoji()
    {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

        add_filter('emoji_svg_url', '__return_false');

        add_filter(
            'tiny_mce_plugins',
            function ($plugins) {
                if (\is_array($plugins)) {
                    return array_diff($plugins, ['wpemoji']);
                }

                return [];
            }
        );

        add_filter(
            'wp_resource_hints',
            function ($urls, $relation_type) {
                if ('dns-prefetch' === (string) $relation_type) {
                    $emoji_url = 'https://s.w.org/images/core/emoji/';
                    foreach ($urls as $key => $url) {
                        if (false !== strpos($url, $emoji_url)) {
                            unset($urls[$key]);
                        }
                    }
                }

                return $urls;
            },
            10,
            2
        );
    }

    public function wpembed()
    {
        if (isset($GLOBALS['wp']) && \is_object($GLOBALS['wp']) && isset($GLOBALS['wp']->public_query_vars)) {
            $GLOBALS['wp']->public_query_vars = array_diff($GLOBALS['wp']->public_query_vars, ['embed']);
        }

        if (isset($GLOBALS['wp_embed']) && \is_object($GLOBALS['wp_embed'])) {
            remove_filter('the_content', [$GLOBALS['wp_embed'], 'autoembed'], 8);
        }

        remove_filter('the_content_feed', '_oembed_filter_feed_content');
        remove_action('plugins_loaded', 'wp_maybe_load_embeds', 0);
        add_filter('pre_option_embed_autourls', '__return_false');
        add_filter('embed_oembed_discover', '__return_false');
        remove_action('rest_api_init', 'wp_oembed_register_route');
        remove_filter('rest_pre_serve_request', '_oembed_rest_pre_serve_request');
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        remove_action('embed_head', 'enqueue_embed_scripts', 1);
        remove_action('embed_head', 'print_emoji_detection_script');
        remove_action('embed_head', 'print_embed_styles');
        remove_action('embed_head', 'wp_print_head_scripts', 20);
        remove_action('embed_head', 'wp_print_styles', 20);
        remove_action('embed_head', 'wp_no_robots');
        remove_action('embed_head', 'rel_canonical');
        remove_action('embed_head', 'locale_stylesheet', 30);
        remove_action('embed_content_meta', 'print_embed_comments_button');
        remove_action('embed_content_meta', 'print_embed_sharing_button');
        remove_action('embed_footer', 'print_embed_sharing_dialog');
        remove_action('embed_footer', 'print_embed_scripts');
        remove_action('embed_footer', 'wp_print_footer_scripts', 20);
        remove_filter('excerpt_more', 'wp_embed_excerpt_more', 20);
        remove_filter('the_excerpt_embed', 'wptexturize');
        remove_filter('the_excerpt_embed', 'convert_chars');
        remove_filter('the_excerpt_embed', 'wpautop');
        remove_filter('the_excerpt_embed', 'shortcode_unautop');
        remove_filter('the_excerpt_embed', 'wp_embed_excerpt_attachment');
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result');
        remove_filter('oembed_response_data', 'get_oembed_response_data_rich');
        remove_filter('pre_oembed_result', 'wp_filter_pre_oembed_result');
        remove_filter('woocommerce_short_description', 'wc_do_oembeds');

        add_filter(
            'tiny_mce_plugins',
            function ($plugins) {
                return array_diff($plugins, ['wpembed', 'wpview']);
            }
        );

        add_filter(
            'rewrite_rules_array',
            function ($rules) {
                $results = [];
                foreach ($rules as $rule => $val) {
                    if (false !== ($pos = strpos($val, '?'))) {
                        $args = explode('&', substr($val, $pos + 1));
                        if (\in_array('embed=true', $args)) {
                            continue;
                        }
                    }
                    $results[$rule] = $val;
                }

                return $results;
            }
        );

        add_filter(
            'body_class',
            function ($classes, $class) {
                foreach ($classes as $num => $name) {
                    if ('wp-embed-responsive' === $name) {
                        unset($classes[$num]);
                    }
                }

                return $classes;
            },
            PHP_INT_MAX,
            2
        );

        add_action(
            'wp_footer',
            function () {
                wp_dequeue_script('wp-embed');
            },
            PHP_INT_MAX
        );
    }

    public function wpfeed()
    {
        add_action(
            'wp_loaded',
            function () {
                remove_action('wp_head', 'feed_links', 2);
                remove_action('wp_head', 'feed_links_extra', 3);
            }
        );

        add_action(
            'init',
            function () {
                if (isset($GLOBALS['wp_rewrite']) && \is_object($GLOBALS['wp_rewrite']) && isset($GLOBALS['wp_rewrite']->feeds)) {
                    $GLOBALS['wp_rewrite']->feeds = [];
                }
            }
        );

        foreach (['rdf', 'rss', 'rss2', 'atom', 'rss2_comments', 'atom_comments'] as $feed) {
            add_action(
                'do_feed_'.$feed,
                function () {
                    wp_redirect(home_url(), 302);
                    exit;
                },
                1
            );
        }
    }

    public function wplazyload()
    {
        add_action(
            'init',
            function () {
                add_filter('wp_lazy_loading_enabled', '__return_false');
            },
            PHP_INT_MAX
        );
    }

    public function wpsitemap()
    {
        add_action(
            'init',
            function () {
                add_filter('wp_sitemaps_enabled', '__return_false');
                remove_filter('robots_txt', ['WP_Sitemaps', 'add_robots']);
            },
            -PHP_INT_MAX
        );
    }

    public function wpapppassword()
    {
        add_filter('wp_is_application_passwords_available', '__return_false', PHP_INT_MAX);
    }
}
