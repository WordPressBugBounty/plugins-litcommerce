<?php
/*
Plugin Name: LitCommerce
Description: Helps you easily integrate your WooCommerce store with LitCommerce.
Version: 1.2.5
Author: LitCommerce
Author URI: https://litcommerce.com
License: GPL2
Text Domain: litcommerce
Requires Plugins: woocommerce
*/

class LitCommercePlugin {
	/** @var LitCommerce_Automation[] */
	public $steps = [];

    public function registerPluginHooks() {
        if($this->is_multy_app()){
            add_menu_page('Litcommerce',
                'Litcommerce',
                'manage_options',
                'litcommerce-master',
                [$this, 'litcommerce_menu_redirect'],
                plugins_url('images/logo.png', __FILE__),
            );
            add_submenu_page(
                'litcommerce-master',
                'LitCommerce: Marketplace Integration',
                'Marketplace Integration',
                'manage_options',
                'litcommerce-integration',
                [$this, 'renderPage']
            );
        }else{
            add_menu_page(
                'Litcommerce',
                'Litcommerce',
                'manage_options',
                'litcommerce-integration',
                [$this, 'renderPage'],
                plugins_url('images/logo.png', __FILE__),

            );
        }


        remove_submenu_page('litcommerce-master', 'litcommerce-master');

        add_action('admin_action_litcommerce_integrate', [$this, 'integrate']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    function is_multy_app(){
        if (is_plugin_active('litcommerce-feed/litcommerce-feed.php')) {
            return true;
        }
        return false;
    }

	function integrate() {
		$stepIndex = isset($_POST['step']) ? intval($_POST['step']) : -1;
		$result = $this->runStep($stepIndex);

		echo json_encode($result);
		exit();
	}

	/**
	 * @param int $stepIndex
	 *
	 * @return LitCommerce_Result_Object
	 */
	function runStep( $stepIndex ) {
		if ($stepIndex < 0 || $stepIndex >= count($this->steps)) {
			return new LitCommerce_Result_Object(
				false,
				__('Invalid integration step received. Please contact our support.', 'litcommerce')
			);
		}

		return $this->steps[$stepIndex]->runStep();
	}

	function enqueueScripts() {
		wp_enqueue_script(
			'litcommerce-js',
			plugin_dir_url(__FILE__) . 'js/litcommerce.js',
			array('jquery'),
			'0.1'
		);

		wp_enqueue_style(
			'litcommerce-css',
			plugin_dir_url(__FILE__) . 'css/styles.css',
			array(),
			'0.1'
		);
	}

	function renderPage() {
		echo '<h1>LitCommerce: Marketplace Integration</h1>';
		$is_reconnect = get_litc_params('reconnect') == 1;
		if (!empty(get_option('woocommerce_litcommerce_consumer_key'))) {
			$is_connected = true;
			if ($is_reconnect) {

				$buttonLabel = __('Re-connect to LitCommerce', 'litcommerce');
			} else {
				$buttonLabel = __('Go to LitCommerce', 'litcommerce');

			}
		} else {
			$is_connected = false;
			$buttonLabel = __('Connect to LitCommerce', 'litcommerce');
		}

		?>
		<?php if (!$is_connected || $is_reconnect) { ?>
            <script>
                var litcommerceBaseUrl = <?php echo json_encode(admin_url('admin.php')); ?>;
                var litcommerceStoreUrl = <?php echo json_encode(home_url()); ?>;
                var integrationStepCount = <?php echo json_encode(count($this->steps)); ?>;
                var defaultIntegrationError = <?php echo json_encode(__('Could not connect to the website to complete the integration step. Please, try again.', 'litcommerce')) ?>;
                var successfulIntegrationMessage = <?php echo json_encode(__('Successfully prepared to integrate with Litcommerce!', 'litcommerce')) ?>;
            </script>
            <div id="litcommerce-description">
                <p>Easily activate Litcommerce Integration with WooCommerce. Connect Litcommerce and WooCommerce on your
                    website
                    with a single click of the button below.</p>
                <p>By clicking the button below, you are acknowledging that Litcommerce can make the following
                    changes:</p>
                <ul style="list-style: circle inside;">
					<?php foreach ($this->steps as $index => $step) { ?>
                        <li><?php echo $step->getName(); ?></li>
					<?php } ?>
                </ul>
                <form method="post" action="<?php echo admin_url('admin.php'); ?>" novalidate="novalidate">
                    <p class="submit">
                        <input type="hidden" name="action" value="litcommerce_integrate"/>
                        <input type="hidden" name="step" value="0"/>
                        <input type="submit" value="<?php echo esc_attr($buttonLabel); ?>" class="button button-primary"
                               id="btn-submit">
                    </p>
                </form>
            </div>
            <div id="litcommerce-progress" style="display: none">
                Integration progress:
                <ol>
					<?php foreach ($this->steps as $index => $step) { ?>
                        <li id="litcommerce-step-<?php echo $index; ?>">
							<?php echo $step->getName(); ?>
                        </li>
					<?php } ?>
                </ol>
                <p id="litcommerce-result">
                </p>
            </div>
			<?php if (get_litc_params('reconnect') == 1) { ?>
                <script>
                    var link = document.getElementById('btn-submit');
                    link.click()
                </script>
			<?php } ?>
		<?php } else { ?>
            <a type="submit" href="https://app.litcommerce.com" target="_blank" class="button button-primary"
               id="btn-submit"><?php echo esc_attr($buttonLabel); ?></a>
			<?php
			$url = site_url() . '/wp-admin/admin.php?page=litcommerce-integration&reconnect=1'
			?>
            <p style="font-style: italic">If your site is not yet connected to LitCommerce, please <a
                        href="<?php echo $url; ?>">click here</a> to reconnect</p>
		<?php } ?>
        <p style="font-style: italic"> If you are using the Cloudflare Web Application Firewall, please follow <a
                    href="https://help.litcommerce.com/en/article/solution-when-your-websites-firewall-blocks-litcommerce-i2ub8p/"
                    target="_blank">these instructions</a> to establish a connection.</p>

		<?php
	}
}

include_once('LitCommerceResultObject.php');
include_once('steps/LitCommerce_Automation.php');
include_once('steps/EnsureWooCommercePlugin.php');
include_once('steps/EnsureWooCommerceActive.php');
include_once('steps/EnableWooCommerceAPI.php');
include_once('steps/PermalinkSettings.php');
include_once('steps/GenerateWooCommerceKeys.php');
include_once('steps/SendWooCommerceKeys.php');

$litcommercePlugin = new LitCommercePlugin();
$litcommercePlugin->steps[] = new LitCommerce_EnsureWooCommercePlugin();
$litcommercePlugin->steps[] = new LitCommerce_EnsureWooCommerceActive();
$litcommercePlugin->steps[] = new LitCommerce_EnableWooCommerceAPI();
$litcommercePlugin->steps[] = new LitCommerce_PermalinkSettings();
$litcommercePlugin->steps[] = new LitCommerce_GenerateWooCommerceKeys();
$litcommercePlugin->steps[] = new LitCommerce_SendWooCommerceKeysStep();

add_action('admin_menu', [$litcommercePlugin, 'registerPluginHooks']);
add_filter('woocommerce_rest_product_object_query', function ( array $args, \WP_REST_Request $request ) {
	$modified_after = get_litc_params('modified_after');

	if (!$modified_after) {
		return $args;
	}
	$args['date_query'][] = [
		"column" => "post_modified",
		"after" => $modified_after,
	];
	$fields = [
		'order' => 'litcommerce',
		'orderby' => 'litcommerceby',
		'offset' => 'litcommerceoff',
		'paged' => 'litcommercepag',
	];
	foreach ($fields as $field => $param) {
		if (get_litc_params($param)) {
			$args[$field] = get_litc_params($param);
		}
	}
	if ('date' === $args['orderby']) {
		$args['orderby'] = 'date ID';
	}
	return $args;

}, 10, 2);
add_filter('woocommerce_rest_shop_order_object_query', function ( array $args, \WP_REST_Request $request ) {
	$modified_after = get_litc_params('modified_after');

	if (!$modified_after) {
		return $args;
	}
	$args['date_query'][] = [
		"column" => "post_modified",
		"after" => $modified_after,
	];
	$fields = [
		'order' => 'litcommerce',
		'orderby' => 'litcommerceby',
		'offset' => 'litcommerceoff',
		'paged' => 'litcommercepage',
	];
	foreach ($fields as $field => $param) {
		if (get_litc_params($param)) {
			$args[$field] = get_litc_params($param);
		}
	}
	return $args;

}, 10, 2);
// ADDING 2 NEW COLUMNS WITH THEIR TITLES (keeping "Total" and "Actions" columns at the end)
add_filter('manage_edit-shop_order_columns', 'litc_custom_shop_order_column', 20);
function litc_custom_shop_order_column( $columns ) {
	$reordered_columns = array();

	// Inserting columns to a specific location
	foreach ($columns as $key => $column) {
		$reordered_columns[$key] = $column;
		if ($key == 'order_status') {
			// Inserting after "Status" column
			$reordered_columns['_litc_order_from'] = __('Source', 'theme_domain');
			$reordered_columns['_litc_order_number'] = __('LitC Order Number', 'theme_domain');
		}
	}
	return $reordered_columns;
}

// Adding custom fields meta data for each new column (example)
add_action('manage_shop_order_posts_custom_column', 'litc_custom_orders_list_column_content', 20, 2);
function litc_custom_orders_list_column_content( $column, $post_id ) {
	switch ($column) {
		case '_litc_order_from' :

			// Get custom post meta data
			$column_data = get_post_meta($post_id, $column, true);
			if (!empty($column_data))
				echo $column_data;

			// Testing (to be removed) - Empty value case
			else
				echo '';

			break;
		case '_litc_order_number' :
			$column_data = get_post_meta($post_id, $column, true);
			if ($column_data) {
				$litc_order_id = get_post_meta($post_id, '_litc_order_id', true);
				if ($litc_order_id) {
					echo "<a href='https://app.litcommerce.com/orders/{$litc_order_id}' target='_blank'>{$column_data}</a>";
				} else {
					echo $column_data;
				}
			} else {
				echo '';
			}

	}
}

function litc_filter_woocommerce_customer_email_recipient( $recipient, $order, $email ) {
	if (!$order || !is_a($order, 'WC_Order'))
		return $recipient;

	// Has order status
	$column_data = get_post_meta($order->get_id(), '_litc_allow_send_email', true);
	if ($column_data && $column_data != 1) {
		return '';
	}

	return $recipient;
}

function litc_filter_woocommerce_owner_email_recipient( $recipient, $order, $email ) {
	if (!$order || !is_a($order, 'WC_Order'))
		return $recipient;

	// Has order status
	$column_data = get_post_meta($order->get_id(), '_litc_allow_send_email_owner', true);
	if ($column_data && $column_data != 1) {
		return '';
	}

	return $recipient;
}

add_filter('woocommerce_email_recipient_customer_refunded_order', 'litc_filter_woocommerce_customer_email_recipient', 10, 3);
add_filter('woocommerce_email_recipient_customer_on_hold_order', 'litc_filter_woocommerce_customer_email_recipient', 10, 3);
add_filter('woocommerce_email_recipient_customer_processing_order', 'litc_filter_woocommerce_customer_email_recipient', 10, 3);
add_filter('woocommerce_email_recipient_new_order', 'litc_filter_woocommerce_owner_email_recipient', 10, 3);
add_filter('woocommerce_email_recipient_customer_cancelled_order', 'litc_filter_woocommerce_customer_email_recipient', 10, 3);
add_filter('woocommerce_email_recipient_failed_order', 'litc_filter_woocommerce_owner_email_recipient', 10, 3);
add_filter('woocommerce_email_recipient_customer_completed_order', 'litc_filter_woocommerce_customer_email_recipient', 10, 3);
function litc_change_woocommerce_order_number( $order_id, $order ) {
	$meta_data = $order->get_meta_data();
	$order_number = $order_id;
	$order_number_prefix = '';
	$order_number_suffix = '';
    $custom_order_number = '';
    $keep_woo_order_number = false;
	foreach ($meta_data as $item) {
        if($keep_woo_order_number){
            return $order_id;
        }
		switch ($item->get_data()['key']) {
            case '_litc_keep_woo_order_number':
				$keep_woo_order_number = true;
				break;
			case '_litc_order_number':
				$order_number = $item->get_data()['value'];
				break;
            case '_order_number':
				$custom_order_number = $item->get_data()['value'];
				break;
			case '_litc_order_number_prefix':
				$order_number_prefix = $item->get_data()['value'];
				break;
			case '_litc_order_number_suffix':
				$order_number_suffix = $item->get_data()['value'];
				break;

		}
	}
	return $custom_order_number? $custom_order_number: $order_number_prefix . $order_number . $order_number_suffix;
}

add_filter('woocommerce_order_number', 'litc_change_woocommerce_order_number', PHP_INT_MAX, 2);
function litc_shop_order_meta_search_fields( $meta_keys ) {
	$meta_keys[] = '_litc_order_number';
	return $meta_keys;
}

add_filter('woocommerce_shop_order_search_fields', 'litc_shop_order_meta_search_fields', 10, 1);
function litc_woocommerce_rest_prepare_product_object( $response, $object, $request ) {
	if (get_litc_params("custom_currency") == 1) {
		$meta = get_post_meta($object->get_id());
		foreach ($meta as $key => $value) {
			if (in_array($key, ['_price', '_regular_price', '_sale_price'])) {
				$response->data['litc' . $key] = $value[0];
			}
		}
	}

	if (get_litc_params("get_terms")) {
		$terms = explode(',', get_litc_params("get_terms"));
		foreach ($terms as $term) {
			$terms_data = wp_get_post_terms($object->get_id(), $term);
			$res = [];
			if ($terms_data) {
				$res[] = $terms_data[0]->name;
			}
			if ($res) {
				$response->data['litc_' . $term] = implode(',', $res);
			}
		}
	}
    if(@$response->data['meta_data']){
        foreach ($response->data['meta_data'] as $meta_data) {
		if ($meta_data->key == '_yoast_wpseo_primary_yith_product_brand') {
			$brand_id = $meta_data->value;
			$terms_data = get_term_by('id', $brand_id, 'yith_product_brand');
			try {
				if ($terms_data) {
					$response->data['litc_product_brand'] = $terms_data->name;
					break;
				}
			} catch (Exception $e) {

			}


		}
	}
    }

	return $response;
}

add_filter('woocommerce_rest_prepare_product_object', 'litc_woocommerce_rest_prepare_product_object', 10, 3);
add_filter('woocommerce_rest_prepare_product_variation_object', 'litc_woocommerce_rest_prepare_product_object', 10, 3);
add_action('woocommerce_admin_order_item_headers', 'litc_admin_order_items_headers', 10, 1);
function litc_admin_order_items_headers( $order ) {
	if (is_object($order) && method_exists($order, 'get_meta') && $order->get_meta('_litc_has_tax')) {

		echo '<th class="line_litc_tax_line sortable" data-sort="float">
            Tax
        </th>';
	}


}

add_action('woocommerce_admin_order_item_values', 'litc_admin_order_item_values', 10, 3);
function litc_admin_order_item_values( $_product, $item, $item_id = null ) {

	// get the post meta value from the associated product
	$value = $item->get_meta('_litc_item_tax');
	$order = $item->get_order();
	if (is_object($order) && method_exists($order, 'get_meta') && $order->get_meta('_litc_has_tax')) {
		$currency = $order->get_currency();
		$currency_symbol = get_woocommerce_currency_symbol($currency);
		if ($value) {
			echo '<td class="item_cost" width="1%" data-sort-value="float">
		<div class="view">
			<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">' . $currency_symbol . '</span>' . $value . '</span>		</div>
	</td>';
		} else {
			echo '<td></td>';
		}
	}

//
	// display the value

}
function get_litc_params($key)
{
    $value = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
    if(!$value){
        return null;
    }
    return $value;
}

function litc_woocommerce_hidden_order_itemmeta( $arr ) {
	$arr[] = '_litc_item_tax';
	$arr[] = '_litc_order_id';
	return $arr;
}

add_filter('woocommerce_hidden_order_itemmeta', 'litc_woocommerce_hidden_order_itemmeta', 10, 1);
function litc_woocommerce_find_rates( $matched_tax_rates ) {
	if (get_litc_params('from_litc') == 1 && get_litc_params('litc_custom_tax_rate')) {
		return [
			0 => [
				'rate' => $_GET['litc_custom_tax_rate'],
				'label' => get_litc_params('litc_custom_tax_label') ? get_litc_params('litc_custom_tax_label') : 'Tax',
				'shipping' => get_litc_params('litc_custom_shipping_tax') == 1 ? 'yes' : 'no',
				'compound' => 'no'
			]
		];
	}
	return $matched_tax_rates;


}





add_filter('woocommerce_find_rates', 'litc_woocommerce_find_rates', 10, 3);
function litc_woocommerce_rate_label( $rate_name ) {
	if ($litc_custom_tax_label = get_litc_params('litc_custom_tax_label')) {
		return $litc_custom_tax_label;
	}
	return $rate_name;


}

add_filter('woocommerce_rate_label', 'litc_woocommerce_rate_label', 10, 3);
function litc_woocommerce_rest_pre_insert_shop_order_object( $order ) {
    if(get_litc_params('from_litc') == 1 && class_exists('WC_Seq_Order_Number')){
        global $wpdb;
		$using_hpos = class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        $order_meta_table = $using_hpos ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta;
        $query = "SELECT  IF( MAX( CAST( meta_value as UNSIGNED ) ) IS NULL, 1, MAX( CAST( meta_value as UNSIGNED ) ) + 1 ) as 'max_order_number'
							FROM {$order_meta_table}
							WHERE meta_key='_order_number'";
        $max_order_number = $wpdb->get_row($query, ARRAY_A);
        if($max_order_number){
            $order->update_meta_data( '_order_number', $max_order_number['max_order_number']);

        }
    }

	return $order;


}

add_filter('woocommerce_rest_pre_insert_shop_order_object', 'litc_woocommerce_rest_pre_insert_shop_order_object', 10);


add_action('rest_api_init', function () {
    register_rest_route('wc/v3/litc', '/products/(?P<id>\d+)/images', array(
        'methods' => 'POST',
        'callback' => 'litc_add_product_images',
        'permission_callback' => function () {
            return current_user_can('edit_products');
        },
    ));
    register_rest_route('wc/v3/litc', '/products/(?P<product_id>\d+)/images/(?P<image_id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'litc_delete_product_image',
        'permission_callback' => function () {
            return current_user_can('edit_products');
        },
    ));
});

function litc_add_product_images($request) {
    $product_id = $request['id'];
    $images = $request->get_param('images');

    if (!is_array($images) || empty($images)) {
        return new WP_Error('invalid_images', 'Invalid images array', array('status' => 400));
    }

    if (!get_post($product_id) || get_post_type($product_id) !== 'product') {
        return new WP_Error('invalid_product', 'Product not found', array('status' => 404));
    }

    $uploaded_images = [];

    foreach ($images as $image_url) {
        $image_id = litc_upload_image_from_url($image_url);

        if (is_wp_error($image_id)) {
            return new WP_Error('image_upload_failed', 'Failed to upload image: ' . $image_url, array('status' => 500));
        }

        $uploaded_images[] = $image_id;
    }

    $product = wc_get_product($product_id);
    $existing_gallery = $product->get_gallery_image_ids();
    $updated_gallery = array_merge($existing_gallery, $uploaded_images);
    $product->set_gallery_image_ids($updated_gallery);
    $product->save();

    return rest_ensure_response([
        'success' => true,
        'uploaded_images' => $uploaded_images,
    ]);
}

function litc_upload_image_from_url($image_url) {
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);

    if (!$image_data) {
        return new WP_Error('image_fetch_failed', 'Could not fetch image from URL.');
    }

    $filename = basename($image_url);
    $filename = explode('?', $filename)[0];
    $file_path = $upload_dir['path'] . '/' . $filename;

    file_put_contents($file_path, $image_data);

    $filetype = wp_check_filetype($filename, null);

    if (!$filetype['type']) {
        return new WP_Error('invalid_image_type', 'Invalid image type.');
    }

    $attachment_id = wp_insert_attachment(
        [
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
        ],
        $file_path
    );

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
    wp_update_attachment_metadata($attachment_id, $attach_data);

    return $attachment_id;
}


function litc_delete_product_image($request) {
    $product_id = $request['product_id'];
    $image_id = $request['image_id'];
    $force = $request->get_param('force');
    if (!get_post($product_id) || get_post_type($product_id) !== 'product') {
        return new WP_Error('invalid_product', 'Product not found', array('status' => 404));
    }

    if (!get_post($image_id)) {
        return new WP_Error('invalid_image', 'Image not found', array('status' => 404));
    }

    $product = wc_get_product($product_id);
    $gallery = $product->get_gallery_image_ids();

    if (!in_array($image_id, $gallery)) {
        return new WP_Error('image_not_in_product', 'Image is not associated with this product.', array('status' => 400));
    }

    $updated_gallery = array_diff($gallery, [$image_id]);
    $product->set_gallery_image_ids($updated_gallery);
    $product->save();

    if ($force === true || $force === 'true') {
        wp_delete_attachment($image_id, true);
    }


    return rest_ensure_response([
        'success' => true,
        'message' => 'Image removed from product successfully.',
        'remaining_images' => $updated_gallery,
    ]);
}