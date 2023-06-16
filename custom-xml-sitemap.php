<?php
/*
Plugin Name: Custom XML Sitemap
Description: A simple plugin to modify the XML sitemap file with new links and properties.
Version: 1.1
Author: Evan Grissino
Requires at least: 6.2
Requires PHP: 7.4
*/

// WordPress Recommended Settings
// Create a function called "wporg_init" if it doesn't already exist
if ( ! function_exists( 'wporg_init' ) ) {
    function wporg_init() {
        register_setting( 'wporg_settings', 'wporg_option_foo' );
    }
}

// Create a function called "wporg_get_foo" if it doesn't already exist
if ( ! function_exists( 'wporg_get_foo' ) ) {
    function wporg_get_foo() {
        return get_option( 'wporg_option_foo' );
    }
}

// Add a new menu item in the WordPress admin panel
add_action('admin_menu', 'custom_xml_sitemap_menu');
function custom_xml_sitemap_menu() {
  add_menu_page(
    'Custom XML Sitemap',
    'XML Sitemap',
    'manage_options',
    'custom-xml-sitemap',
    'custom_xml_sitemap_page'
  );
}

function connect_fs($url, $method, $context, $fields = null)
{
  global $wp_filesystem;
  if(false === ($credentials = request_filesystem_credentials($url, $method, false, $context, $fields)))
  {
    return false;
  }

  //check if credentials are correct or not.
  if(!WP_Filesystem($credentials))
  {
    request_filesystem_credentials($url, $method, true, $context);
    return false;
  }

  return true;
}

function write_sitemap_file($text)
{
  global $wp_filesystem;
  $url = wp_nonce_url("custom-xml-sitemap.php", "nonce");
  if(connect_fs($url, "", ABSPATH . "/"))
  {

    $dom = new DOMDocument("1.0");
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($text);

    $dir = $wp_filesystem->find_folder(ABSPATH);
    $file = trailingslashit($dir) . "sitemap.xml";
    $wp_filesystem->put_contents($file, $dom->saveXML(), FS_CHMOD_FILE);

    return $text;
  }
  else
  {
    return new WP_Error("filesystem_error", "Cannot initialize filesystem");
  }
}

function debug_to_console($data) {
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log('Debug Objects: $output');</script>";
}

// Display the XML sitemap page in the WordPress admin panel
function custom_xml_sitemap_page() {
  // Check if the user is allowed to manage options
  if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
  }

  // Check if the form has been submitted
  if (isset($_POST['submit'])) {
    // Get the sitemap file path
    $sitemap_file = ABSPATH . 'sitemap.xml';

    // Get the current contents of the sitemap file
    $sitemap_contents = file_get_contents($sitemap_file);

    // Parse the sitemap file as XML
    $sitemap_xml = simplexml_load_string($sitemap_contents);

    // Get Operation from form
    $oper = $_POST['submit'];

    // Switch operation
    switch ($oper) {
    case 'Delete URL':
      // Delete the specified URL from the sitemap
      $url_index = (int)$_POST['delete'];

      $k = 0;
      $toDelete = array();

      foreach ($sitemap_xml->url as $xml_item) {
        if ($url_index == $k) {
          $toDelete[] = $xml_item;
          break;
        }
        $k=$k+1;
      }

      foreach ($toDelete as $item) {
        $dom = dom_import_simplexml($item);
        $dom->parentNode->removeChild($dom);
      }

      // Save the updated sitemap file
      write_sitemap_file($sitemap_xml->asXML());

      // Display a success message
      //if ( $url_index > 0 ) {
      //debug_to_console('$url_index');
      echo '<div class="updated"><p>URL removed from the XML sitemap.</p></div>';
      //} else {
        //echo '<div class="error"><p>Failed to remove URL from the XML sitemap.</p></div>';
      //}
      break;

    case 'Add URL':
      // Normalize the URL entered by the user
      $url = $_POST['url'];
      if ($url != "") {
        $offset = 0;
        if (!preg_match('/^https://', $url)) { // Starts with https://?
          $offset+=8;
	}
        if (!preg_match('/^evangrissino.com#', $url)) {
          $offset+=16;
        }

        $url = get_site_url() . '/' . ltrim(substr($url, $offset), '/') . '/';

        // Add a new URL to the sitemap
        $new_url = $sitemap_xml->addChild('url');
        $new_url->addChild('loc', $url);
        $new_url->addChild('changefreq', $_POST['changefreq']);
        $new_url->addChild('priority', $_POST['priority']);

        // Save the updated sitemap file
        write_sitemap_file($sitemap_xml->asXML());

        // Display a success message
        echo '<div class="updated"><p>New URL added to the XML sitemap.</p></div>';
      }
      break;

    default:
      echo '<div class="updated"><p>Failed to delete link from the XML sitemap.</p></div>';
      break;

    } // switch operations

    // Update Google sitemaps
    file_get_contents("https://www.google.com/ping?sitemap=".get_site_url()."/sitemap.xml");

  } // isset('submit')

  // Get the sitemap file path
  $sitemap_file = ABSPATH . 'sitemap.xml';

  // Get the current contents of the sitemap file
  $sitemap_contents = file_get_contents($sitemap_file);

  // Parse the sitemap file as XML
  $sitemap_xml = simplexml_load_string($sitemap_contents);

  // Display the form to add a new URL to the sitemap
  ?>
  <div class="wrap widefat">
    <h1>Add URL to XML Sitemap</h1>
    <form method="post" action="">
      <table class="form-table" role="presentation">
        <tbody>
          <tr>
            <th scope="row">
              <label for="url">URL:</label><br>
            </th>
            <td>
              <input type="text" name="url" id="url" size="50"><br><br>
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label for="changefreq">Change Frequency:</label><br>
            </th>
            <td>
              <select name="changefreq" id="changefreq">
                <option value="always">Always</option>
                <option value="hourly">Hourly</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
                <option value="never">Never</option>
              </select><br><br>
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label for="priority">Priority:</label><br>
            </th>
            <td>
              <input type="text" name="priority" id="priority" size="10"><br><br>
            </td>
          </tr>
        </tbody>
      </table>
      <input type="submit" name="submit" value="Add URL">
    </form>

    <h2>Current URLs in XML Sitemap</h2>
    <table class="wp-list-table widefat striped">
      <thead>
        <tr>
          <th>ID</th>
          <th>URL</th>
          <th>Change Frequency</th>
          <th>Priority</th>
        </tr>
      </thead>
      <tbody>
        <?php $url_index = 0; ?>
        <?php foreach ($sitemap_xml->url as $url): ?>
          <tr>
            <td><?php echo $url_index; ?></td>
            <td><?php echo $url->loc; ?></td>
            <td><?php echo $url->changefreq; ?></td>
            <td><?php echo $url->priority; ?></td>
            <td>
              <form method="post" action="">
                <input type="hidden" name="submit" value="Delete URL">
                <input type="hidden" name="delete" value="<?= $url_index ?>">
                <button type="submit" class="button-link" onclick="confirm('Are you sure you want to delete this URL?');">Delete</button>
              </form>
            </td>
          </tr>
        <?php $url_index=$url_index+1; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
}

