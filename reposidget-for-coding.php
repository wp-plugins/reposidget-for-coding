<?php
/*
Plugin Name: Reposidget For Coding
Plugin URI: http://coding.net/pcdotfan/reposidget-for-coding
Description: Insert Coding repository widget into you posts/pages. Be greatful to myst729 (http://github.com/myst729/wp-reposidget). 在 WordPress 文章/页面中插入 Coding 项目挂件。
Version: 1.0.0
Author: PCDotFan
Author URI: http://www.mywpku.com/
License: GPLv2 or later
*/
define(CODING_HOMEPAGE,  "http://www.mywpku.com");
define(CODING_CODING,  "https://coding.net");
define(CODING_USERAGENT, "WP Coding Reposidget/1.0.0 (WordPress 3.9.0+) PCDotFan/729");

function coding_i18n() {
  load_plugin_textdomain("repo", false, plugin_basename(__DIR__) . "/langs/");
}

function coding_style() {
  wp_enqueue_style("reposidget_style", plugins_url("coding.css", __FILE__));
}

function coding_fetch($url) {
  $ch = curl_init($url);

  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_USERAGENT, CODING_USERAGENT);
  $response = curl_exec($ch);
  curl_close($ch);

  return json_decode($response, true);
}

function coding_render($template, $pattern, $data) {
  $handle = fopen($template, "r");
  $string = fread($handle, filesize($template));
  fclose($handle);
  $replacer = function($matches) use ($data) { return $data[$matches[1]]; };
  return preg_replace_callback($pattern, $replacer, $string);
}

function coding($atts) {
  if(array_key_exists("path", $atts)) {
    $atts_path = explode("/", $atts["path"]);
    $atts_owner = $atts_path[1];
    $atts_name = $atts_path[3];
  } else {
    $atts_owner = $atts["owner"];
    $atts_name = $atts["name"];
  }

  if($atts_owner == null || $atts_name == null) {
    return "";
  }
  $giturl = "https://coding.net/api/user/" . $atts_owner . '/project/' . $atts_name . '/git';
  $url = "https://coding.net/api/user/" . $atts_owner . '/project/' . $atts_name ;
  $repo = coding_fetch($giturl);
  $main_repo = coding_fetch($url);

    $description_empty = ($main_repo["data"]["description"] == "");
    $homepage_empty = ($data["html_url"] == "" && $repo["owner_url"] == null);
    $data = array(
      "owner"              => $repo["data"]["depot"]["owner"]["name"],
      "owner_url"          => CODING_CODING.$repo["data"]["depot"]["owner"]["path"],
      "name"               => $repo["data"]["depot"]["name"],
      "html_url"           => CODING_CODING.$repo["data"]["depot"]["depot_path"],
      "default_branch"     => $repo["data"]["depot"]["default_branch"],
      "owner_avatar"     => $repo["data"]["depot"]["owner"]["avatar"],
      "description"        => ($description_empty && $homepage_empty) ? __("This repository doesn't have description or homepage.", "repo") : $main_repo["data"]["description"],
      "toggle_description" => ($description_empty && !$homepage_empty) ? "hidden" : "",
      "homepage"           => $homepage_empty ? $data["html_url"] : $repo["owner_url"],
      "toggle_homepage"    => $homepage_empty ? "hidden" : "",
      "stargazers_count"   => number_format($main_repo["data"]["star_count"]),
      "forks_count"        => number_format($main_repo["data"]["fork_count"]),
      "toggle_download"    => "",
      "plugin_tip"         => __("Coding Reposidget for WordPress", "repo"),
      "plugin_url"         => CODING_HOMEPAGE
    );


  $template = plugin_dir_path( __FILE__ ) . "coding.html";
  $pattern = '/{{([a-z_]+)}}/';

  return coding_render($template, $pattern, $data);
}

function coding_editor_style() {
  wp_enqueue_style("reposidget_html", plugins_url("coding-editor.css", __FILE__));
}

function coding_editor() {
  if(wp_script_is("quicktags")) {
    $template = plugin_dir_path( __FILE__ ) . "coding-dialog.html";
    $pattern = '/{{([a-z_]+)}}/';
    $data = array(
      "title"   => __('Add Coding Reposidget', 'repo'),
      "message" => __('Please fill the owner and name of the repo:', 'repo'),
      "owner"   => __('Repo Owner', 'repo'),
      "name"    => __('Repo Name', 'repo'),
      "add"     => __('Add Repo', 'repo'),
      "cancel"  => __('Cancel', 'repo')
    );

    echo coding_render($template, $pattern, $data);
?>
    <script type="text/javascript" src="<?php echo plugins_url("coding-dialog.js", __FILE__); ?>"></script>
    <script type="text/javascript">
      void function() {
        function addWpReposidget(button, editor, qtags) {
          window.wpReposidgetDialog.open(qtags.id, false);
        }
        QTags.addButton("reposidget_html", "<?php _e('coding Repo', 'repo'); ?>", addWpReposidget, undefined, undefined, "<?php _e('Add coding Reposidget', 'repo'); ?>");
      }();
    </script>
<?php
  }
}

function coding_mce_plugin($plugin_array) {
  $plugin_array["reposidget_mce"] = plugins_url("coding-mce.js", __FILE__);
  return $plugin_array;
}

function coding_mce_button($buttons) {
  array_push($buttons, "reposidget_mce");
  return $buttons;
}

function coding_editor_init() {
  if(current_user_can("edit_posts") || current_user_can("edit_pages")) {
    add_filter("admin_enqueue_scripts", "coding_editor_style");
    add_filter("admin_print_footer_scripts", "coding_editor");

    if(get_user_option("rich_editing") == "true") {
      add_filter("mce_external_plugins", "coding_mce_plugin");
      add_filter("mce_buttons", "coding_mce_button");
    }
  }
}

add_filter("plugins_loaded", "coding_i18n");
add_filter("wp_enqueue_scripts", "coding_style");
add_filter("admin_init", "coding_editor_init");
add_filter('plugin_action_links_' . plugin_basename(plugin_dir_path(__FILE__) . 'coding.php'), 'coding_options_link');
add_shortcode("repo", "coding");

?>