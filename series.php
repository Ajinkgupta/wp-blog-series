<?php

/*
Plugin Name: WP Blog Series
Plugin URI: https://www.doubtly.in/
Description: This plugin is used to create a blog series.
Version: 1.0
Author: Ajink Gupta
*/

function wp_blog_series_custom_post_type()
{
    register_post_type("wp-blog-series", array(
        "labels" => array(
            "name" => __("Blog Series"),
            "singular_name" => __("Blog Series")
        ),
        "public" => true,
        "has_archive" => true,
        "rewrite" => array("slug" => "blog-series"),
        "supports" => array("editor", "title", "excerpt", "thumbnail", "comments"),
        "capability_type" => "post",
        "publicly_queryable" => true,
        "taxonomies" => array("category", "post_tag"),
    ));
}

add_action("init", "wp_blog_series_custom_post_type", 2);

/* Flush Rewrite Rules */

function wp_blog_series_activation()
{
    wp_blog_series_custom_post_type();
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, "wp_blog_series_activation");
register_deactivation_hook(__FILE__, "wp_blog_series_activation");

/* Add Custom Meta Boxes in WordPress Posts */

function wp_blog_series_meta_box_markup($object)
{
    wp_nonce_field(basename(__FILE__), "wp-blog-series");

    ?>
    <div>
        <label for="wp-blog-series-serial-number">Serial Number</label>
        <br>
        <input name="wp-blog-series-serial-number" type="text" value="<?php echo get_post_meta($object->ID, "wp-blog-series-serial-number", true); ?>">

        <br>

        <label for="wp-blog-series-id">Name</label>
        <br>
        <select name="wp-blog-series-id">
            <option value="">-</option>
            <?php
            $posts = get_posts(array("post_type" => "wp-blog-series"));
            $selected_series = get_post_meta($object->ID, "wp-blog-series-id", true);
            foreach ($posts as $post) {
                $id_post = $post->ID;
                if ($id_post == $selected_series) {
                    ?>
                    <option selected value="<?php echo $post->ID; ?>"><?php echo $post->post_title; ?></option>
                    <?php
                } else {
                    ?>
                    <option value="<?php echo $post->ID; ?>"><?php echo $post->post_title; ?></option>
                    <?php
                }
            }
            ?>
        </select>
    </div>
    <?php
}

function wp_blog_series_custom_meta_box()
{
    add_meta_box("wp-blog-series", "Blog Series", "wp_blog_series_meta_box_markup", "post", "side", "low", null);
}

add_action("add_meta_boxes", "wp_blog_series_custom_meta_box");

/* Callback to Save Meta Data */

function wp_blog_series_save_custom_meta_box($post_id, $post, $update)
{
    if (!isset($_POST["wp-blog-series"]) || !wp_verify_nonce($_POST["wp-blog-series"], basename(__FILE__)))
        return $post_id;

    if (!current_user_can("edit_post", $post_id))
        return $post_id;

    if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE)
        return $post_id;

    $slug = "post";
    if ($slug != $post->post_type)
        return;

    $serial_number = isset($_POST["wp-blog-series-serial-number"]) ? $_POST["wp-blog-series-serial-number"] : "";
    update_post_meta($post_id, "wp-blog-series-serial-number", $serial_number);

    $series_id = isset($_POST["wp-blog-series-id"]) ? $_POST["wp-blog-series-id"] : "";
    $previous_series_id = get_post_meta($post_id, "wp-blog-series-id", true);

    update_post_meta($post_id, "wp-blog-series-id", $series_id);

    // No series, removing series, adding new series or changing series
    if ($previous_series_id == "" && $series_id == "") {
        wp_blog_series_save_settings($series_id, $serial_number, $post_id);
    } else if ($previous_series_id != "" && $series_id == "") {
        wp_blog_series_save_settings($previous_series_id, "", $post_id);
    } else if ($previous_series_id == "" && $series_id != "") {
        wp_blog_series_save_settings($series_id, $serial_number, $post_id);
    } else if ($previous_series_id != "" && $series_id != "") {
        wp_blog_series_save_settings($previous_series_id, "", $post_id);
        wp_blog_series_save_settings($series_id, $serial_number, $post_id);
    }
}

add_action("save_post", "wp_blog_series_save_custom_meta_box", 10, 3);

/* Store WordPress posts and Blog Series CTY relations as WordPress Settings. */

function wp_blog_series_save_settings($series_id, $serial_number, $post_id)
{
    if ($series_id != "" && $serial_number != "") {
        $post_series_list = get_option("post_series_" . $series_id . "_ids", "");

        if ($post_series_list == "") {
            $post_series_list_array = array($post_id);
            $post_series_list = implode(", ", $post_series_list_array);

            update_option("post_series_" . $series_id . "_ids", $post_series_list);
        } else {
            $post_series_list_array = explode(',', $post_series_list);

            if (!in_array($post_id, $post_series_list_array)) {
                $post_series_list_array[] = $post_id;
                $post_series_list = implode(", ", $post_series_list_array);
                update_option("post_series_" . $series_id . "_ids", $post_series_list);
            }
        }
    } else if ($series_id == "" || $serial_number == "") {
        $post_series_list = get_option("post_series_" . $series_id . "_ids", "");

        if ($post_series_list != "") {
            $post_series_list_array = explode(',', $post_series_list);

            if (($key = array_search($post_id, $post_series_list_array)) !== false) {
                unset($post_series_list_array[$key]);
            }
            $post_series_list = implode(", ", $post_series_list_array);
            update_option("post_series_" . $series_id . "_ids", $post_series_list);
        }
    }
}

/* Displaying Custom Post Types on Index Page */

function wp_blog_series_pre_posts($q)
{
    if (is_admin() || !$q->is_main_query() || is_page())
        return;

    $q->set("post_type", array("post", "wp-blog-series"));
}

add_action("pre_get_posts", "wp_blog_series_pre_posts");

function wp_blog_series_content_filter($content)
{
    if ("wp-blog-series" != get_post_type())
        return $content;

    $post_series_list = get_option("post_series_" . get_the_ID() . "_ids", "");
    $post_series_list_array = explode(',', $post_series_list);

    $post_series_serial_number = array ();

    foreach ($post_series_list_array as $value) {
        $serial_number = get_post_meta($value, "wp-blog-series-serial-number", true);
        $post_series_serial_number[$value] = $serial_number;
    }

    asort($post_series_serial_number);

    $html = "<ul class='wp-blog-series'>";

    foreach ($post_series_serial_number as $key => $value) {
        $post = get_post($key);
        $title = $post->post_title;
        $excerpt = $post->post_content;
        $shortcode_pattern = get_shortcode_regex();
        $excerpt = preg_replace('/' . $shortcode_pattern . '/', '', $excerpt);
        $excerpt = strip_tags($excerpt);
        $excerpt = esc_attr(substr($excerpt, 0, 150));

        $img = "";
        if (has_post_thumbnail($key)) {
            $temp = wp_get_attachment_image_src(get_post_thumbnail_id($key), array(150, 150));
            $img = $temp[0];
        } else {
            $img = "https://lorempixel.com/150/150/abstract";
        }

        $html .= "<li><h3><a href='" . get_permalink($key) . "'>" . $title . "</a></h3><div><div class='wp-blog-series-box1'><img src='" . $img . "' /></div><div class='wp-blog-series-box2'><p>" . $excerpt . " ...</p></div></div><div class='clear'></div></li>";
    }

    $html .= "</ul>";

    return $content . $html;
}

add_filter("the_content", "wp_blog_series_content_filter");

/* Adding Content to WordPress Posts which belong to a Series */

function wp_blog_series_post_content_filter($content)
{
    if ("post" != get_post_type())
        return $content;

    $serial_number = get_post_meta(get_the_ID(), "wp-blog-series-serial-number", true);
    $series_id = get_post_meta(get_the_ID(), "wp-blog-series-id", true);

    if (get_post_status($series_id) == "publish") {
        $html = "";

        if ($series_id != "" || $serial_number != "") {
            $html = "<div class='wp-blog-series-post-content'><div>This post is part " . $serial_number . " of <a href='" . get_permalink($series_id) . "'>" . get_the_title($series_id) . "</a> post series.</div></div>";
        }

        $content = $html . $content;
    }

    if ($serial_number != "" && $series_id != "") {
        $post_series_list = get_option("post_series_" . $series_id . "_ids", "");
        $post_series_list_array = explode(',', $post_series_list);

        $post_series_serial_number = array();

        foreach ($post_series_list_array as $value) {
            $serial_number = get_post_meta($value, "wp-blog-series-serial-number", true);
            $post_series_serial_number[$value] = $serial_number;
        }

        asort($post_series_serial_number);

        $post_series_serial_number_reverse = array();
        $iii = 1;

        foreach ($post_series_serial_number as $key => $value) {
            $post_series_serial_number_reverse[$iii] = $key;
            $iii++;
        }

        $index = array_search(get_the_ID(), $post_series_serial_number_reverse);

        if ($index == 1) {
            $html = "<div class='wp-blog-series-post-content'><div>This post is part of <a href='" . get_permalink($series_id) . "'>" . get_the_title($series_id) . "</a> post series.</div><div>&#9112; Next: <a href='" . get_permalink($post_series_serial_number_reverse[$index + 1]) . "'>" . get_the_title($post_series_serial_number_reverse[$index + 1]) . "</a></div></div>";
            $content = $html . $content;
        } else if ($index > 1 && $index < sizeof($post_series_serial_number_reverse)) {
            $html = "<div class='wp-blog-series-post-content'><div>This post is part of <a href='" . get_permalink($series_id) . "'>" . get_the_title($series_id) . "</a> post series.</div><div>&#9112; Next post in the series is <a href='" . get_permalink($post_series_serial_number_reverse[$index + 1]) . "'>" . get_the_title($post_series_serial_number_reverse[$index + 1]) . "</a></div><div>&#9111; Previous post in the series is <a href='" . get_permalink($post_series_serial_number_reverse[$index - 1]) . "'>" . get_the_title($post_series_serial_number_reverse[$index - 1]) . "</a></div></div>";
            $content = $html . $content;
        } else if ($index == sizeof($post_series_serial_number_reverse)) {
            $html = "<div class='wp-blog-series-post-content'><div>This post is part of <a href='" . get_permalink($series_id) . "'>" . get_the_title($series_id) . "</a> post series.</div><div>&#9111; Previous: <a href='" . get_permalink($post_series_serial_number_reverse[$index - 1]) . "'>" . get_the_title($post_series_serial_number_reverse[$index - 1]) . "</a></div></div>";
            $content = $html . $content;
        }
    }

    return $content;
}

add_filter("the_content", "wp_blog_series_post_content_filter");

function wp_blog_series_enqueue_styles() {
    wp_enqueue_style('wp-blog-series', plugin_dir_url(__FILE__) . 'series.css');
}

add_action('wp_enqueue_scripts', 'wp_blog_series_enqueue_styles');

/* Sorting Categories Page in Ascending Order */

function wp_blog_series_category_posts_order($query) {
    if ($query->is_category()) {
        $query->set('order', 'ASC');
    }
}
add_action('pre_get_posts', 'wp_blog_series_category_posts_order');

/* Widget to Display Blog Series Posts in a Category */

class WP_Blog_Series_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'wp_blog_series_widget',
            __('Blog Series Widget', 'text_domain'),
            array(
                'description' => __('Display all articles of the current post series or category in ascending order.', 'text_domain'),
            )
        );
    }

    public function widget($args, $instance) {
        global $post;

        $title = apply_filters('widget_title', $instance['title']);
        $category_id = $instance['category_id'];

        echo $args['before_widget'];
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }

        // Get current post's series ID
        $series_id = get_post_meta($post->ID, "wp-blog-series-id", true);

        // If the current post has a series ID, display posts from that series
        if (!empty($series_id)) {
            $post_series_list = get_option("post_series_" . $series_id . "_ids", "");
            $post_series_list_array = explode(',', $post_series_list);

            $html = '<ul>';
            foreach ($post_series_list_array as $post_id) {
                $html .= '<li><a href="' . get_permalink($post_id) . '">' . get_the_title($post_id) . '</a></li>';
            }
            $html .= '</ul>';

            echo $html;
        } elseif (!empty($category_id)) { // If the current post doesn't belong to a series, display posts from the specified category
            $args = array(
                'category' => $category_id,
                'orderby' => 'title',
                'order' => 'ASC',
            );
            $posts_query = new WP_Query($args);

            if ($posts_query->have_posts()) {
                $html = '<ul>';
                while ($posts_query->have_posts()) {
                    $posts_query->the_post();
                    $html .= '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
                }
                $html .= '</ul>';

                echo $html;
            }

            wp_reset_postdata();
        }

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = isset($instance['title']) ? $instance['title'] : '';
        $category_id = isset($instance['category_id']) ? $instance['category_id'] : '';

        // Widget Title
        echo '<p>';
        echo '<label for="' . $this->get_field_id('title') . '">' . __('Title:') . '</label>';
        echo '<input class="widefat" id="' . $this->get_field_id('title') . '" name="' . $this->get_field_name('title') . '" type="text" value="' . esc_attr($title) . '">';
        echo '</p>';

        // Category
        echo '<p>';
        echo '<label for="' . $this->get_field_id('category_id') . '">' . __('Category ID:') . '</label>';
        echo '<input class="widefat" id="' . $this->get_field_id('category_id') . '" name="' . $this->get_field_name('category_id') . '" type="text" value="' . esc_attr($category_id) . '">';
        echo '</p>';
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['category_id'] = (!empty($new_instance['category_id'])) ? strip_tags($new_instance['category_id']) : '';
        return $instance;
    }
}

function wp_blog_series_register_widget() {
    register_widget('WP_Blog_Series_Widget');
}
add_action('widgets_init', 'wp_blog_series_register_widget');
 

?>


