<?php
/*
Plugin Name: Really Simple CSV Importer Ajax
Plugin URI: http://wordpress.org/plugins/really-simple-csv-importer/
Description: Import posts, categories, tags, custom fields from simple csv file.
Author: Takuro Hishikawa, Subh2020
Author URI: https://en.digitalcube.jp/
Text Domain: really-simple-csv-importer
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
Version: 1.3.1
*/

if ( ! defined( 'WP_LOAD_IMPORTERS' ) && ! defined( 'DOING_AJAX' ) )
	return;

/** Display verbose errors */
if ( ! defined( 'IMPORT_DEBUG' ) )
	define( 'IMPORT_DEBUG', true );

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}

// Load Helpers
require dirname( __FILE__ ) . '/class-rs_csv_helper.php';
require dirname( __FILE__ ) . '/class-rscsv_import_post_helper.php';

if ( ! class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require $class_wp_importer;
}

/**
 * CSV Importer
 *
 * @package WordPress
 * @subpackage Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
class RS_CSV_Importer_Ajax extends WP_Importer {
	
	var $id; // CSV attachment ID
	var $file; // CSV attachment file
    var $dataArray = false; //Data Array
    
    var $process_as_file = false;
	/** Sheet columns
	* @value array
	*/
	public $column_indexes = array();
	public $column_keys = array();

 	// User interface wrapper start
	function header() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>'.__('Import CSV', 'really-simple-csv-importer').'</h2>';

		$updates = get_plugin_updates();
		$basename = plugin_basename(__FILE__);
		if ( isset( $updates[$basename] ) ) {
			$update = $updates[$basename];
			echo '<div class="error"><p><strong>';
			printf( __( 'A new version of this importer is available. Please update to version %s to ensure compatibility with newer export files.', 'really-simple-csv-importer' ), $update->update->new_version );
			echo '</strong></p></div>';
		}
	}

	// User interface wrapper end
	function footer() {
		echo '</div>';
	}
	
	/**
	 * Display introductory text and file upload form
	 */
	function greet() 
	{
		?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"></div>
			<h1>CSV Import</h1>
		<?php
			//we check if the page is visited by click on the tabs or on the menu button.
			//then we get the active tab.
			$active_tab = "csv-import";
			if(isset($_GET["tab"]))
			{
				if($_GET["tab"] == "csv-import")
				{
					$active_tab = "csv-import";
				}
				else
				{
					$active_tab = "import-options";
				}
			}	
		?>

			<!-- wordpress provides the styling for tabs. -->
			<h2 class="nav-tab-wrapper">
				<!-- when tab buttons are clicked we jump back to the same page but with a new parameter that represents the clicked tab. accordingly we make it active -->
				<a href="?import=csv-ajax&tab=csv-import" class="nav-tab <?php if($active_tab == 'csv-import'){echo 'nav-tab-active';} ?> "><?php _e('CSV Import', 'really-simple-csv-importer'); ?></a>
				<a href="?import=csv-ajax&tab=import-options" class="nav-tab <?php if($active_tab == 'import-options'){echo 'nav-tab-active';} ?>"><?php _e('Import Settings', 'really-simple-csv-importer'); ?></a>
			</h2>
		<?php 
			if($active_tab == "import-options")
			{
				$options = get_option( 'csv_importer_settings' );
		?>
				<div class="wrap">
				<div id="really-simple-csv-importer-setting" style="display: block;">
					<h2><?php _e( 'Import Settings', 'really-simple-csv-importer' ); ?></h2>
					<form method="post" action="options.php">
						<?php wp_nonce_field('update-options') ?>
						<table>
							<tr>
								<td>
			<label>
										<?php _e( 'Delimiter', 'really-simple-csv-importer' ); ?>
			</label>
								</td>
								<td>
			<label>
										<input type="radio" id="delimiter" name="csv_importer_settings[delimiter]" value="0" 
										<?php echo $options['delimiter']==0?'checked':'';?> /><?php _e( "Comma", 'really-simple-csv-importer' ); ?>

			</label>
									<label>
										<input type="radio" id="delimiter" name="csv_importer_settings[delimiter]" value="1"
										<?php echo $options['delimiter']==1?'checked':'';?> /><?php _e( 'Semicolon', 'really-simple-csv-importer' ); ?>
									</label>
									<label>
										<input type="radio" id="delimiter" name="csv_importer_settings[delimiter]" value="2"
										<?php echo $options['delimiter']==2?'checked':'';?> /><?php _e( "Tab", 'really-simple-csv-importer' ); ?>
									</label>
									<label>
										<input type="radio" id="delimiter" name="csv_importer_settings[delimiter]" value="3"
										<?php echo $options['delimiter']==3?'checked':'';?> /><?php _e( 'Pipe', 'really-simple-csv-importer' ); ?>
									</label>
									<label>
										<input type="radio" id="delimiter" name="csv_importer_settings[delimiter]" value="4"
										<?php echo $options['delimiter']==4?'checked':'';?> /><?php _e( 'Colon', 'really-simple-csv-importer' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<td>
									<label>
										<?php _e( 'Enclouser', 'really-simple-csv-importer' ); ?>
									</label>
								</td>
								<td>
									<input type="text" id="enclosure" name="csv_importer_settings[enclosure]" value="<?php echo isset( $options['enclosure'] ) ?esc_attr( $options['enclosure']): '"';?>"  />
								</td>
							</tr>
							<tr>
								<td>
									<label>
										<?php _e( 'Max Lines Per Part', 'really-simple-csv-importer' ); ?>
									</label>
								</td>
								<td>
									<input type="text" id="maxlines_per_part" name="csv_importer_settings[maxlines_per_part]" value="<?php echo isset( $options['maxlines_per_part'] ) ? esc_attr( $options['maxlines_per_part']) : '500'; ?>"  />
								</td>
							</tr>
						</table>
						<p><input type="submit" name="Submit" value="Store Options" /></p>
						<input type="hidden" name="action" value="update" />
						<input type="hidden" name="page_options" value="csv_importer_settings" />
					</form>
				</div>
				</div>
			<?php
			}else if($active_tab == "csv-import") 
			{
				$this->rs_csv_importer_file_upload();
			}
		?>
		</div>
		<?php
	}

	function RS_CSV_Importer_Ajax() { /* nothing */ }

	/**
	 * Registered callback function for the WordPress Importer
	 *
	 * Manages the three separate stages of the WXR import process
	 */
	function dispatch() {
		$this->header();

		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];
		switch ( $step ) {
			case 0:
				$this->greet();
				break;
		}

		$this->footer();
	}

	/**
	 * Pre-config the class before using any of the other Ajax methods, called everytime!
	 * 
	 * Uses the $this->process_posts() method to keep the changes just on Ajax calls
	 */
	function ajax_import_start(){
        if($this->process_as_file)
        {
            $file = get_attached_file( $this->id );
            if ( ! is_file($file) ) {
                return new WP_Error( 'file-does-not-exists', __( 'The file does not exist, please try again.', 'really-simple-csv-importer' ), array( 'file' => $file, 'id' => $this->id ) );
            }
        }else
        {
            if(!$this->dataArray)
            {
                return new WP_Error( 'data-does-not-available', __( 'CSV data does not exist, please try again.', 'really-simple-csv-importer' ) );
            }
        }
		do_action( 'ajax_import_start' ); // DO NOT echo anything here or it will break!

		$check = ob_get_contents(); // Grab any echos that the $this->get_author_mapping() method might have printed!
		if( !empty($check) ){
			ob_end_clean(); ob_start(); // Clean for a ajax get a clean request
			return new WP_Error( 'could-not-map-users', $check, array( 'file' => $file, 'id' => $this->id ) );
		}

		wp_suspend_cache_invalidation( true );

		return true;
		}
		
	/**
	 * Perform the post-actions for the import Class when it's called in ajax!
	 * 
	 * Uses the $this->process_posts() method to keep the changes just on Ajax calls
	 */
	function ajax_import_end() {
        
        if($this->process_as_file)
        {
            wp_import_cleanup( $id );
        }
        $this->dataArray = false;
		
		wp_suspend_cache_invalidation( false );

		wp_cache_flush();

		do_action( 'ajax_import_end' ); // DO NOT echo anything here or it will break!

		return true;
	}

	function saveAsCSVFile($fileName, $dataArray)
	{
		global $wp_filesystem;
		if (!is_object($wp_filesystem)) 
		{
			WP_Filesystem();
		}
		
		if ($fileName && is_object($wp_filesystem)) 
		{
			$destination = wp_upload_dir();
			$filepath = $destination['path'] . '/' . $fileName;
			$fileIO = fopen($filepath, 'w+');
			foreach ($dataArray as $line) 
			{
				fwrite($fileIO, $line);
			}
			fclose($fileIO);
		}

		return $filepath;
	}
	public function attach_temp_csv_file($filepath)
	{
		$upload_dir     = wp_upload_dir();
		$url = $upload_dir['baseurl'] . '/' . _wp_relative_upload_path($filepath);

		// Construct the object array
		$object = array(
			'post_title' => basename( $filepath ),
			'post_content' => $url,
			'post_mime_type' => 'application/vnd.ms-excel',
			'guid' => $url,
			'context' => 'import',
			'post_status' => 'private'
		);
		// Save the data
		$id = wp_insert_attachment( $object, $filepath );
		
		/*
		 * Schedule a cleanup for one day from now in case of failed
		 * import or missing wp_import_cleanup() call.
		 */
		wp_schedule_single_event( time() + DAY_IN_SECONDS, 'importer_scheduled_cleanup', array( $id ) );
		
		return $id;
	}
	
	/**
	 * Ajax callback for handling the import of attachments
	 * 
	 * Uses the $this->process_posts() method to keep the changes just on Ajax calls
	 */
	function ajax_process_posts() {
		ob_get_clean(); ob_start(); // Personally I use this before all the Ajax Calls just so I can have a clean answer for my javascript

		check_ajax_referer( 'csv_importer_nonce', 'security' );
		
		if(!is_admin())
		{
			return;
		}

		$fileName  = $_POST['file_name'];
		$jsonData = $_POST['file_data'];
		$dataArray = json_decode(stripslashes($jsonData), true);
        $this->dataArray = $dataArray;
        if($this->process_as_file)
        {
            $filepath = $this->saveAsCSVFile($fileName, $dataArray);
            $this->id = (int) $this->attach_temp_csv_file($filepath);
		$this->file = get_attached_file($this->id);
			set_time_limit(0);
        }
		$res = new stdClass;
		$res->start = $this->ajax_import_start();
		
		if( is_wp_error($res->start) ) // Just die if there is an error in the start
			die(json_encode($res));
        
        if($this->process_as_file)
        {
            $res->process = $this->process_posts_file();
        }else
        {
            echo ">>>>>>>>>>>>>>>>>>>>>>>>>>".$this->process_as_file;
            print_r($this->dataArray);
            $res->process = $this->process_posts_data();
        }
        
		$check = ob_get_contents(); // Grab any echos that the $this->process_posts() method might have printed!

		if( !empty($check) ){
			ob_end_clean(); ob_start(); // Clean for a ajax get a clean request
			$res->error = $check;
		}

		$res->end = $this->ajax_import_end();

		die(json_encode($res->process));
	}
	
	/**
	* Insert post and postmeta using `RSCSV_Import_Post_Helper` class.
	*
	* @param array $post
	* @param array $meta
	* @param array $terms
	* @param string $thumbnail The uri or path of thumbnail image.
	* @param bool $is_update
	* @return RSCSV_Import_Post_Helper
	*/
	public function save_post($post,$meta,$terms,$thumbnail,$is_update) {
		
		// Separate the post tags from $post array
		if (isset($post['post_tags']) && !empty($post['post_tags'])) {
			$post_tags = $post['post_tags'];
			unset($post['post_tags']);
		}

		// Special handling of attachments
		if (!empty($thumbnail) && $post['post_type'] == 'attachment') {
			$post['media_file'] = $thumbnail;
			$thumbnail = null;
		}

		// Add or update the post
		if ($is_update) {
			$h = RSCSV_Import_Post_Helper::getByID($post['ID']);
			$h->update($post);
		} else {
			$h = RSCSV_Import_Post_Helper::add($post);
		}
		
		// Set post tags
		if (isset($post_tags)) {
			$h->setPostTags($post_tags);
		}
		
		// Set meta data
		$h->setMeta($meta);
		
		// Set terms
		foreach ($terms as $key => $value) {
			$h->setObjectTerms($key, $value);
		}
		
		// Add thumbnail
		if ($thumbnail) {
			$h->addThumbnail($thumbnail);
		}
		
		return $h;
	}

	// process parse csv ind insert posts
	function process_posts_file() {
		$result1 = array();
		$result1['status']  = true;
		$result1['message']  = "Success Test";
		$message = '';

		//return $result1;
		
		$h = new RS_CSV_Helper;

		$handle = $h->fopen($this->file, 'r');
		if ( $handle == false ) {
			$result1['message'] = '<p><strong>'.__( 'Failed to open file.', 'really-simple-csv-importer' ).'</strong></p>';
			$result1['status']  = false;
			wp_import_cleanup($this->id);
			return $result1;
		}
		
		$is_first = true;
		$post_statuses = get_post_stati();
		
		$message .= '<ol>';
		
		while (($data = $h->fgetcsv($handle)) !== FALSE) {
			if ($is_first) {
				$h->parse_columns( $this, $data );
				$is_first = false;
			} else {
				$message .= '<li>';
				
				$post = array();
				$is_update = false;
				$error = new WP_Error();
				
				// (string) (required) post type
				$post_type = $h->get_data($this,$data,'post_type');
				if ($post_type) {
					if (post_type_exists($post_type)) {
						$post['post_type'] = $post_type;
					} else {
						$error->add( 'post_type_exists', sprintf(__('Invalid post type "%s".', 'really-simple-csv-importer'), $post_type) );
					}
				} else {
					$message .= __('Note: Please include post_type value if that is possible.', 'really-simple-csv-importer').'<br>';
				}
				
				// (int) post id
				$post_id = $h->get_data($this,$data,'ID');
				$post_id = ($post_id) ? $post_id : $h->get_data($this,$data,'post_id');
				if ($post_id) {
					$post_exist = get_post($post_id);
					if ( is_null( $post_exist ) ) { // if the post id is not exists
						$post['import_id'] = $post_id;
					} else {
						if ( !$post_type || $post_exist->post_type == $post_type ) {
							$post['ID'] = $post_id;
							$is_update = true;
						} else {
							$error->add( 'post_type_check', sprintf(__('The post type value from your csv file does not match the existing data in your database. post_id: %d, post_type(csv): %s, post_type(db): %s', 'really-simple-csv-importer'), $post_id, $post_type, $post_exist->post_type) );
						}
					}
				}

				// (string) post title
				$post_title = $h->get_data($this,$data,'post_title');
				if ($post_title) {

					if ( ! $is_update && $_POST['replace-by-title'] == 1 ) {
						//try to update a post with the same title
						if ( ! $post_type ) {
							$post_type = 'post';
						}
						$post_id = get_page_by_title($post_title, OBJECT, $post_type);

						if ( ! is_null($post_id) ) {
							$post['ID'] = $post_id;
							$is_update = true;
						}
					}

					$post['post_title'] = $post_title;
				}

				// (string) post slug
				$post_name = $h->get_data($this,$data,'post_name');
				if ($post_name) {
					$post['post_name'] = $post_name;
				}
				
				// (login or ID) post_author
				$post_author = $h->get_data($this,$data,'post_author');
				if ($post_author) {
					if (is_numeric($post_author)) {
						$user = get_user_by('id',$post_author);
					} else {
						$user = get_user_by('login',$post_author);
					}
					if (isset($user) && is_object($user)) {
						$post['post_author'] = $user->ID;
						unset($user);
					}
				}

				// user_login to post_author
				$user_login = $h->get_data($this,$data,'post_author_login');
				if ($user_login) {
					$user = get_user_by('login',$user_login);
					if (isset($user) && is_object($user)) {
						$post['post_author'] = $user->ID;
						unset($user);
					}
				}
				
				// (string) publish date
				$post_date = $h->get_data($this,$data,'post_date');
				if ($post_date) {
					$post['post_date'] = date("Y-m-d H:i:s", strtotime($post_date));
				}
				$post_date_gmt = $h->get_data($this,$data,'post_date_gmt');
				if ($post_date_gmt) {
					$post['post_date_gmt'] = date("Y-m-d H:i:s", strtotime($post_date_gmt));
				}
				
				// (string) post status
				$post_status = $h->get_data($this,$data,'post_status');
				if ($post_status) {
    				if (in_array($post_status, $post_statuses)) {
    					$post['post_status'] = $post_status;
    				}
				}
				
				// (string) post password
				$post_password = $h->get_data($this,$data,'post_password');
				if ($post_password) {
    				$post['post_password'] = $post_password;
				}

				// (string) post content
				$post_content = $h->get_data($this,$data,'post_content');
				if ($post_content) {
					$post['post_content'] = $post_content;
				}
				
				// (string) post excerpt
				$post_excerpt = $h->get_data($this,$data,'post_excerpt');
				if ($post_excerpt) {
					$post['post_excerpt'] = $post_excerpt;
				}
				
				// (int) post parent
				$post_parent = $h->get_data($this,$data,'post_parent');
				if ($post_parent) {
					$post['post_parent'] = $post_parent;
				}
				
				// (int) menu order
				$menu_order = $h->get_data($this,$data,'menu_order');
				if ($menu_order) {
					$post['menu_order'] = $menu_order;
				}
				
				// (string) comment status
				$comment_status = $h->get_data($this,$data,'comment_status');
				if ($comment_status) {
					$post['comment_status'] = $comment_status;
				}
				
				// (string, comma separated) slug of post categories
				$post_category = $h->get_data($this,$data,'post_category');
				if ($post_category) {
					$categories = preg_split("/,+/", $post_category);
					if ($categories) {
						$post['post_category'] = wp_create_categories($categories);
					}
				}
				
				// (string, comma separated) name of post tags
				$post_tags = $h->get_data($this,$data,'post_tags');
				if ($post_tags) {
					$post['post_tags'] = $post_tags;
				}
				
				// (string) post thumbnail image uri
				$post_thumbnail = $h->get_data($this,$data,'post_thumbnail');
				
				$meta = array();
				$tax = array();

				// add any other data to post meta
				foreach ($data as $key => $value) {
					if ($value !== false && isset($this->column_keys[$key])) {
						// check if meta is custom taxonomy
						if (substr($this->column_keys[$key], 0, 4) == 'tax_') {
							// (string, comma divided) name of custom taxonomies 
							$customtaxes = preg_split("/,+/", $value);
							$taxname = substr($this->column_keys[$key], 4);
							$tax[$taxname] = array();
							foreach($customtaxes as $key => $value ) {
								$tax[$taxname][] = $value;
							}
						}
						else {
							$meta[$this->column_keys[$key]] = $value;
						}
					}
				}
				
				/**
				 * Filter post data.
				 *
				 * @param array $post (required)
				 * @param bool $is_update
				 */
				$post = apply_filters( 'really_simple_csv_importer_save_post', $post, $is_update );
				/**
				 * Filter meta data.
				 *
				 * @param array $meta (required)
				 * @param array $post
				 * @param bool $is_update
				 */
				$meta = apply_filters( 'really_simple_csv_importer_save_meta', $meta, $post, $is_update );
				/**
				 * Filter taxonomy data.
				 *
				 * @param array $tax (required)
				 * @param array $post
				 * @param bool $is_update
				 */
				$tax = apply_filters( 'really_simple_csv_importer_save_tax', $tax, $post, $is_update );
				/**
				 * Filter thumbnail URL or path.
				 *
				 * @since 1.3
				 *
				 * @param string $post_thumbnail (required)
				 * @param array $post
				 * @param bool $is_update
				 */
				$post_thumbnail = apply_filters( 'really_simple_csv_importer_save_thumbnail', $post_thumbnail, $post, $is_update );

				/**
				 * Option for dry run testing
				 *
				 * @since 0.5.7
				 *
				 * @param bool false
				 */
				$dry_run = apply_filters( 'really_simple_csv_importer_dry_run', false );
				
				if (!$error->get_error_codes() && $dry_run == false) {
					
					/**
					 * Get Alternative Importer Class name.
					 *
					 * @since 0.6
					 *
					 * @param string Class name to override Importer class. Default to null (do not override).
					 */
					$class = apply_filters( 'really_simple_csv_importer_class', null );
					
					// save post data
					if ($class && class_exists($class,false)) {
						$importer = new $class;
						$result = $importer->save_post($post,$meta,$tax,$post_thumbnail,$is_update);
					} else {
						$result = $this->save_post($post,$meta,$tax,$post_thumbnail,$is_update);
					}
					
					if ($result->isError()) {
						$error = $result->getError();
						$result1['status']  = false;
					} else {
						$post_object = $result->getPost();
						
						if (is_object($post_object)) {
							/**
							 * Fires adter the post imported.
							 *
							 * @since 1.0
							 *
							 * @param WP_Post $post_object
							 */
							do_action( 'really_simple_csv_importer_post_saved', $post_object );
						}
						
						$message .= esc_html(sprintf(__('Processing "%s" done.', 'really-simple-csv-importer'), $post_title));
					}
				}
				
				// show error messages
				foreach ($error->get_error_messages() as $message) {
					$message .= esc_html($message).'<br>';
				}
				
				$message .= '</li>';

				wp_cache_flush();
			}
		}
		
		$message .= '</ol>';

		$h->fclose($handle);
		
		//wp_import_cleanup($this->id);
		
		$message .= '<h3>'.__('Done.', 'really-simple-csv-importer').'</h3>';
		$result1['message'] = $message;
		return $result1;
	}

    	// process parse csv ind insert posts
	function process_posts_data() {
		$result1 = array();
		$result1['status']  = true;
		$result1['message']  = "Success Test";
		$message = '';

		//return $result1;
		
		$h = new RS_CSV_Helper;

		if ( $this->dataArray == false ) {
			$result1['message'] = '<p><strong>'.__( 'No CVS data to import.', 'really-simple-csv-importer' ).'</strong></p>';
			$result1['status']  = false;
		wp_import_cleanup($this->id);
			return $result1;
		}
		
		$is_first = true;
		$post_statuses = get_post_stati();
		
		$message .= '<ol>';
		$options = get_option( 'csv_importer_settings' );
        $delimiter = isset( $options['delimiter'] ) ?getSeparator($options['delimiter']): ',';
        $enclosure = isset( $options['enclosure'] ) ?$options['enclosure']: '"';
        $escape    = '\\';
		foreach ($this->dataArray as $line) {
            $data = str_getcsv($line, $delimiter, $enclosure, '\\');
            /*ob_start();
            var_dump($data);
            $data_str = ob_get_clean();
            $message .= $data_str;     
            */            
			if ($is_first) {
				$h->parse_columns( $this, $data );
				$is_first = false;
			} else {
				$message .= '<li>';
				
				$post = array();
				$is_update = false;
				$error = new WP_Error();
				
				// (string) (required) post type
				$post_type = $h->get_data($this,$data,'post_type');
				if ($post_type) {
					if (post_type_exists($post_type)) {
						$post['post_type'] = $post_type;
					} else {
						$error->add( 'post_type_exists', sprintf(__('Invalid post type "%s".', 'really-simple-csv-importer'), $post_type) );
					}
				} else {
					$message .= __('Note: Please include post_type value if that is possible.', 'really-simple-csv-importer').'<br>';
	}

				// (int) post id
				$post_id = $h->get_data($this,$data,'ID');
				$post_id = ($post_id) ? $post_id : $h->get_data($this,$data,'post_id');
				if ($post_id) {
					$post_exist = get_post($post_id);
					if ( is_null( $post_exist ) ) { // if the post id is not exists
						$post['import_id'] = $post_id;
					} else {
						if ( !$post_type || $post_exist->post_type == $post_type ) {
							$post['ID'] = $post_id;
							$is_update = true;
						} else {
							$error->add( 'post_type_check', sprintf(__('The post type value from your csv file does not match the existing data in your database. post_id: %d, post_type(csv): %s, post_type(db): %s', 'really-simple-csv-importer'), $post_id, $post_type, $post_exist->post_type) );
						}
					}
				}
		
				// (string) post title
				$post_title = $h->get_data($this,$data,'post_title');
				if ($post_title) {

					if ( ! $is_update && $_POST['replace-by-title'] == 1 ) {
						//try to update a post with the same title
						if ( ! $post_type ) {
							$post_type = 'post';
						}
						$post_id = get_page_by_title($post_title, OBJECT, $post_type);

						if ( ! is_null($post_id) ) {
							$post['ID'] = $post_id;
							$is_update = true;
						}
					}

					$post['post_title'] = $post_title;
				}

				// (string) post slug
				$post_name = $h->get_data($this,$data,'post_name');
				if ($post_name) {
					$post['post_name'] = $post_name;
				}
				
				// (login or ID) post_author
				$post_author = $h->get_data($this,$data,'post_author');
				if ($post_author) {
					if (is_numeric($post_author)) {
						$user = get_user_by('id',$post_author);
					} else {
						$user = get_user_by('login',$post_author);
					}
					if (isset($user) && is_object($user)) {
						$post['post_author'] = $user->ID;
						unset($user);
					}
				}

				// user_login to post_author
				$user_login = $h->get_data($this,$data,'post_author_login');
				if ($user_login) {
					$user = get_user_by('login',$user_login);
					if (isset($user) && is_object($user)) {
						$post['post_author'] = $user->ID;
						unset($user);
					}
				}
				
				// (string) publish date
				$post_date = $h->get_data($this,$data,'post_date');
				if ($post_date) {
					$post['post_date'] = date("Y-m-d H:i:s", strtotime($post_date));
				}
				$post_date_gmt = $h->get_data($this,$data,'post_date_gmt');
				if ($post_date_gmt) {
					$post['post_date_gmt'] = date("Y-m-d H:i:s", strtotime($post_date_gmt));
				}
				
				// (string) post status
				$post_status = $h->get_data($this,$data,'post_status');
				if ($post_status) {
    				if (in_array($post_status, $post_statuses)) {
    					$post['post_status'] = $post_status;
    				}
				}
				
				// (string) post password
				$post_password = $h->get_data($this,$data,'post_password');
				if ($post_password) {
    				$post['post_password'] = $post_password;
				}

				// (string) post content
				$post_content = $h->get_data($this,$data,'post_content');
				if ($post_content) {
					$post['post_content'] = $post_content;
				}
				
				// (string) post excerpt
				$post_excerpt = $h->get_data($this,$data,'post_excerpt');
				if ($post_excerpt) {
					$post['post_excerpt'] = $post_excerpt;
				}
				
				// (int) post parent
				$post_parent = $h->get_data($this,$data,'post_parent');
				if ($post_parent) {
					$post['post_parent'] = $post_parent;
				}
				
				// (int) menu order
				$menu_order = $h->get_data($this,$data,'menu_order');
				if ($menu_order) {
					$post['menu_order'] = $menu_order;
				}
				
				// (string) comment status
				$comment_status = $h->get_data($this,$data,'comment_status');
				if ($comment_status) {
					$post['comment_status'] = $comment_status;
				}
				
				// (string, comma separated) slug of post categories
				$post_category = $h->get_data($this,$data,'post_category');
				if ($post_category) {
					$categories = preg_split("/,+/", $post_category);
					if ($categories) {
						$post['post_category'] = wp_create_categories($categories);
					}
				}
				
				// (string, comma separated) name of post tags
				$post_tags = $h->get_data($this,$data,'post_tags');
				if ($post_tags) {
					$post['post_tags'] = $post_tags;
				}
				
				// (string) post thumbnail image uri
				$post_thumbnail = $h->get_data($this,$data,'post_thumbnail');
				
				$meta = array();
				$tax = array();

				// add any other data to post meta
				foreach ($data as $key => $value) {
					if ($value !== false && isset($this->column_keys[$key])) {
						// check if meta is custom taxonomy
						if (substr($this->column_keys[$key], 0, 4) == 'tax_') {
							// (string, comma divided) name of custom taxonomies 
							$customtaxes = preg_split("/,+/", $value);
							$taxname = substr($this->column_keys[$key], 4);
							$tax[$taxname] = array();
							foreach($customtaxes as $key => $value ) {
								$tax[$taxname][] = $value;
							}
						}
						else {
							$meta[$this->column_keys[$key]] = $value;
						}
					}
				}

				/**
				 * Filter post data.
				 *
				 * @param array $post (required)
				 * @param bool $is_update
				 */
				$post = apply_filters( 'really_simple_csv_importer_save_post', $post, $is_update );
				/**
				 * Filter meta data.
				 *
				 * @param array $meta (required)
				 * @param array $post
				 * @param bool $is_update
				 */
				$meta = apply_filters( 'really_simple_csv_importer_save_meta', $meta, $post, $is_update );
				/**
				 * Filter taxonomy data.
				 *
				 * @param array $tax (required)
				 * @param array $post
				 * @param bool $is_update
				 */
				$tax = apply_filters( 'really_simple_csv_importer_save_tax', $tax, $post, $is_update );
				/**
				 * Filter thumbnail URL or path.
				 *
				 * @since 1.3
				 *
				 * @param string $post_thumbnail (required)
				 * @param array $post
				 * @param bool $is_update
				 */
				$post_thumbnail = apply_filters( 'really_simple_csv_importer_save_thumbnail', $post_thumbnail, $post, $is_update );

				/**
				 * Option for dry run testing
				 *
				 * @since 0.5.7
				 *
				 * @param bool false
				 */
				$dry_run = apply_filters( 'really_simple_csv_importer_dry_run', false );
				
				if (!$error->get_error_codes() && $dry_run == false) {
					
					/**
					 * Get Alternative Importer Class name.
					 *
					 * @since 0.6
					 *
					 * @param string Class name to override Importer class. Default to null (do not override).
					 */
					$class = apply_filters( 'really_simple_csv_importer_class', null );
					
					// save post data
					if ($class && class_exists($class,false)) {
						$importer = new $class;
						$result = $importer->save_post($post,$meta,$tax,$post_thumbnail,$is_update);
					} else {
						$result = $this->save_post($post,$meta,$tax,$post_thumbnail,$is_update);
					}
					
					if ($result->isError()) {
						$error = $result->getError();
						$result1['status']  = false;
					} else {
						$post_object = $result->getPost();
						
						if (is_object($post_object)) {
							/**
							 * Fires adter the post imported.
							 *
							 * @since 1.0
							 *
							 * @param WP_Post $post_object
							 */
							do_action( 'really_simple_csv_importer_post_saved', $post_object );
						}
						
						$message .= esc_html(sprintf(__('Processing "%s" done.', 'really-simple-csv-importer'), $post_title));
					}
				}
				
				// show error messages
				foreach ($error->get_error_messages() as $message) {
					$message .= esc_html($message).'<br>';
				}
				
				$message .= '</li>';

				wp_cache_flush();
			}
		}
		
		$message .= '</ol>';

		$message .= '<h3>'.__('Done.', 'really-simple-csv-importer').'</h3>';
		$result1['message'] = $message;
		return $result1;
	}

	public function rs_csv_importer_file_upload() 
	{
		echo '<div class="wrap">
			  <div id="icon-options-general" class="icon32"></div>
			  <h1>CSV Import</h1>';
		echo '<p>'.__( 'Choose a CSV (.csv) file to upload, then click Upload file and import.', 'really-simple-csv-importer' ).'</p>';
		echo '<p>'.__( 'Excel-style CSV file is unconventional and not recommended. LibreOffice has enough export options and recommended for most users.', 'really-simple-csv-importer' ).'</p>';
		echo '<p>'.__( 'Requirements:', 'really-simple-csv-importer' ).'</p>';
		echo '<ol>';
		echo '<li>'.__( 'Select UTF-8 as charset.', 'really-simple-csv-importer' ).'</li>';
		echo '<li>'.__( 'Supported field delimiters are "Comma", "Tab", "Semicolon", "Colon", "Pipe"', 'really-simple-csv-importer' ).'</li>';
		echo '<li>'.__( 'You must quote all text cells.', 'really-simple-csv-importer' ).'</li>';
		echo '</ol>';
		echo '<p>'.__( 'Download example CSV files:', 'really-simple-csv-importer' );
		echo ' <a href="'.plugin_dir_url( __FILE__ ).'sample/sample.csv">'.__( 'csv', 'really-simple-csv-importer' ).'</a>,';
		echo ' <a href="'.plugin_dir_url( __FILE__ ).'sample/sample.ods">'.__( 'ods', 'really-simple-csv-importer' ).'</a>';
		echo ' '.__('(OpenDocument Spreadsheet file format for LibreOffice. Please export as csv before import)', 'really-simple-csv-importer' );
		echo '</p>';
		$loading_img = plugin_dir_url( __FILE__ ). '/assets/images/ajax-loader.gif'; 
		?>
		<div id="really-simple-csv-importer-form-options" style="display: block;">
			<h2><?php _e( 'Import Options', 'really-simple-csv-importer' ); ?></h2>
			<p><?php _e( 'Replace by post title', 'really-simple-csv-importer' ); ?></p>
			<label>
				<input type="radio" name="replace-by-title" value="0" checked="checked" /><?php _e( 'Disable', 'really-simple-csv-importer' ); ?>
			</label>
			<label>
				<input type="radio" name="replace-by-title" value="1" /><?php _e( 'Enable', 'really-simple-csv-importer' ); ?>
			</label>
		</div>
		<br/>
		<div id="inputs" class="clearfix">
			<input type="file" id="upload" name="files[]" multiple />
		</div>
		<hr />
		<output id="list-output"></output>
		<div id="loading" class="clearfix" style="display: none;">
			<img src="<?php echo $loading_img ?>" id="ajaxSpinnerImage" alt="Wait"/>
		</div>
		<div class="progress-wrapper html5-progress-bar">
			<div class="progress-bar-wrapper">
				<progress id="progressbar" value="0" max="100"></progress>
				<span class="progress-value">0%</span>
			</div>
		</div>
		<hr />
		<table id="contents" style="width:100%; height:400px;" border>
		</table>
<?php
	}
		
	/**
	 * Decide if the given meta key maps to information we will want to import
	 *
	 * @param string $key The meta key to check
	 * @return string|bool The key if we do want to import, false if not
	 */
	function is_valid_meta_key( $key ) {
		// skip attachment metadata since we'll regenerate it from scratch
		// skip _edit_lock as not relevant for import
		if ( in_array( $key, array( '_wp_attached_file', '_wp_attachment_metadata', '_edit_lock' ) ) )
			return false;
		return $key;
	}

	/**
	 * Decide whether or not the importer should attempt to download attachment files.
	 * Default is true, can be filtered via import_allow_fetch_attachments. The choice
	 * made at the import options screen must also be true, false here hides that checkbox.
	 *
	 * @return bool True if downloading attachments is allowed
	 */
	function allow_fetch_attachments() {
		return apply_filters( 'import_allow_fetch_attachments', true );
	}

	/**
	 * Decide what the maximum file size for downloaded attachments is.
	 * Default is 0 (unlimited), can be filtered via import_attachment_size_limit
	 *
	 * @return int Maximum attachment file size to import
	 */
	function max_attachment_size() {
		return apply_filters( 'import_attachment_size_limit', 0 );
	}

	/**
	 * Added to http_request_timeout filter to force timeout at 60 seconds during import
	 * @return int 60
	 */
	function bump_request_timeout($val) {
		return 60;
	}

	// return the difference in length between two strings
	function cmpr_strlen( $a, $b ) {
		return strlen($b) - strlen($a);
	}
}

} // class_exists( 'WP_Importer' )

function getSeparator($separator)
{
	$result_separator = ',';
	if(isset( $separator ))
	{
		switch($separator)
		{
			case 0 :
				$result_separator = ',';
				break;
			case 1 :
				$result_separator = ';';
				break;
			case 2:
				$result_separator = '\t';
				break;
			case 3:
				$result_separator = '|';
				break;
			case 4:
				$result_separator = ':';
				break;
			default:
				$result_separator = ',';
				break;
		}
	}
	return $result_separator;
}

function wordpress_ajax_importer_init() {
	load_plugin_textdomain( 'really-simple-csv-importer', false, dirname( plugin_basename(__FILE__) ) . '/languages' );
	
	/**
	 * WordPress Importer object for registering the import callback
	 * @global RS_CSV_Importer_Ajax $wp_import
	 */
	$GLOBALS['wp_import'] = new RS_CSV_Importer_Ajax();
	//add_action( 'wp_ajax_process_posts', array( $GLOBALS['wp_import'], 'ajax_process_posts' ) );
	add_action( 'wp_ajax_upload_csv_files', array( $GLOBALS['wp_import'], 'ajax_process_posts'));
	add_action( 'wp_ajax_nopriv_upload_csv_files', array( $GLOBALS['wp_import'], 'ajax_process_posts' ));


	register_importer( 'csv-ajax', __('CSV-Ajax', 'really-simple-csv-importer'), __('Import <strong>posts, pages, comments, custom fields, categories, and tags</strong> from simple csv file. Also supports Ajax and Spliting CSV files.', 'really-simple-csv-importer'), array( $GLOBALS['wp_import'], 'dispatch' ) );
}
add_action( 'admin_init', 'wordpress_ajax_importer_init' );

function wordpress_ajax_importer_enqueue($hook)	{
	if ( 'admin.php' != $hook ) {
		return;
	}
	// Register the script
	wp_register_script( 'jquery_min1', plugin_dir_url( __FILE__ ) . '/assets/js/jquery.min.js');
	wp_register_script( 'jquery_csv1', plugin_dir_url( __FILE__ ) . '/assets/js/jquery.csv.min.js');
	wp_register_script( 'modernizr1',  plugin_dir_url( __FILE__ ) . '/assets/js/modernizr.js' );
    wp_register_script( 'import_extn1',plugin_dir_url( __FILE__ ) . '/assets/js/import-extn.js' );

	//Set Your Nonce
	$ajax_nonce = wp_create_nonce( "really-simple-csv-importer" );
	
	$options = get_option( 'csv_importer_settings' );
	$params = array(
  					  'ajaxurl' => admin_url('admin-ajax.php', "http"),
					  //'ajaxurl' => admin_url('admin.php', "http"),
					  'ajax_nonce' => wp_create_nonce('csv_importer_nonce'),
					  'separator' => getSeparator($options['delimiter']),
					  'delimiter' => isset( $options['enclosure'] ) ?esc_attr( $options['enclosure']): '"',
					  'hasHeaders' => true,
					  'MaxlinesPerPart' => isset( $options['maxlines_per_part'] ) ?esc_attr( $options['maxlines_per_part']): 500,
					  'load_img_url' => plugin_dir_url( __FILE__ ). '/assets/images/ajax-loader.gif',
					);
	wp_localize_script('import_extn1', 'ajax_object', $params); //pass any php settings to javascript
	wp_enqueue_script( 'jquery_min1');
	wp_enqueue_script( 'jquery_csv1');
	wp_enqueue_script( 'modernizr1');
	wp_enqueue_script( 'import_extn1');
	wp_enqueue_style( 'csv-import-style', plugins_url( '/assets/css/style.css', __FILE__ ) );
}

add_action('admin_enqueue_scripts', 'wordpress_ajax_importer_enqueue');
