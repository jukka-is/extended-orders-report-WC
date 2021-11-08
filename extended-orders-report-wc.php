<?php
/**
Plugin Name: extended-orders-report-WC
 
Plugin URI:        https://github.com/aaxxiiss/extended-orders-report-WC
Description:       Extend WooCommerce analytics orders report by adding features: filter by shipping zone; display shipping country and shipping cost
Version:           0.1.1
Author:            Jukka Isokoski
Author URI:        https://dev.jukkaisokoski.fi/
License:           GPL v2 or later
 * 
 * @package WooCommerce\Admin
 */

/**
 * Register the JS.
 */
function add_extension_register_script() {
/* 	if ( ! class_exists( 'Automattic\WooCommerce\Admin\Loader' ) || ! \Automattic\WooCommerce\Admin\Loader::is_admin_or_embed_page() ) {
		return;
	} */
	
	$script_path       = '/build/index.js';
	$script_asset_path = dirname( __FILE__ ) . '/build/index.asset.php';
	$script_asset      = file_exists( $script_asset_path )
		? require( $script_asset_path )
		: array( 'dependencies' => array(), 'version' => filemtime( $script_path ) );
	$script_url = plugins_url( $script_path, __FILE__ );

	wp_register_script(
		'extended-orders-report-wc',
		$script_url,
		$script_asset['dependencies'],
		$script_asset['version'],
		true
	);


	wp_register_style(
		'extended-orders-report-wc',
		plugins_url( '/build/index.css', __FILE__ ),
		// Add any dependencies styles may have, such as wp-components.
		array(),
		filemtime( dirname( __FILE__ ) . '/build/index.css' )
	);

	wp_enqueue_script( 'extended-orders-report-wc' );
	wp_enqueue_style( 'extended-orders-report-wc' );

}

add_action( 'admin_enqueue_scripts', 'add_extension_register_script' );
 

// Add shipping zone to WC admin asset data registry

function add_shipping_zone_settings() {

    $shipping_zones = array(
		array(
            'label' => __( 'All zones', 'dev-blog-example' ),
            'value' => '-1',
        ),
	
    );

	$defined_zones = WC_Shipping_Zones::get_zones();

	foreach($defined_zones as $zone) {
		$zone_info = array(
            'label' => __( $zone['zone_name'], 'dev-blog-example' ),
            'value' =>  strval($zone['id']),
        );
		array_push( $shipping_zones, $zone_info);
	}

	array_push( $shipping_zones, array(
			'label' => __( 'Rest of the world', 'dev-blog-example' ),
			'value' => '0',
		) );
	
 
    $data_registry = Automattic\WooCommerce\Blocks\Package::container()->get(
        Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry::class
    );
 
    $data_registry->add( 'shippingZones', $shipping_zones );

	

}

 
add_action( 'init', 'add_shipping_zone_settings' );


// Add shipping zone ID parameter as a query argument to the Orders Data Store and Orders Stats Data Store.
// Those data stores use query arguments for caching purposes.
// By adding the parameter, new database query will be performed when the parameter changes.

function apply_shipping_zone_arg( $args ) {

    $zone_id = '-1';
 
    if ( isset( $_GET['zone_id'] ) ) {
        $zone_id = sanitize_text_field( wp_unslash( $_GET['zone_id'] ) );
    }
 
    $args['zone_id'] = $zone_id;

    return $args;
}
 
add_filter( 'woocommerce_analytics_orders_query_args', 'apply_shipping_zone_arg' );
add_filter( 'woocommerce_analytics_orders_stats_query_args', 'apply_shipping_zone_arg' );

//
// SQL statements to the queries for gathering data
//

function add_join_subquery( $clauses ) {
    global $wpdb;
 
	// add shipping country information
    $clauses[] = "JOIN {$wpdb->postmeta} shipping_country_postmeta ON {$wpdb->prefix}wc_order_stats.order_id = shipping_country_postmeta.post_id";

	// add shipping cost information
	$clauses[] = "JOIN {$wpdb->postmeta} shipping_cost_postmeta ON {$wpdb->prefix}wc_order_stats.order_id = shipping_cost_postmeta.post_id";

    return $clauses;
}
 
add_filter( 'woocommerce_analytics_clauses_join_orders_subquery', 'add_join_subquery' );
add_filter( 'woocommerce_analytics_clauses_join_orders_stats_total', 'add_join_subquery' );
add_filter( 'woocommerce_analytics_clauses_join_orders_stats_interval', 'add_join_subquery' );

// WHERE

function add_where_subquery( $clauses ) {
    $zone_id = '-1';
 
    if ( isset( $_GET['zone_id'] ) ) {
         $zone_id  = sanitize_text_field( wp_unslash( $_GET['zone_id'] ) );
    }

	// add shiping country when all zones are selected
 	if ($zone_id === '-1') {
    	$clauses[] = "AND shipping_country_postmeta.meta_key = '_shipping_country'";
	}

	// add shiping countries when Rest of the world is selected
	else if ($zone_id === '0') {

		$zone_ids = array();
		$defined_zones = WC_Shipping_Zones::get_zones();
		foreach($defined_zones as $zone) {
			$zone_ids[] = strval($zone['id']);
		}

		$country_list = get_country_list($zone_ids);
		
		$country_query = get_country_query($country_list);

		$clauses[] = "AND shipping_country_postmeta.meta_key = '_shipping_country' AND shipping_country_postmeta.meta_value NOT IN {$country_query}";
	}

	// add shiping countries when one of the zones is selected
	else if ($zone_id !== '0') {
		$country_list = get_country_list([$zone_id]);
		
		$country_query = get_country_query($country_list);

		$clauses[] = "AND shipping_country_postmeta.meta_key = '_shipping_country' AND shipping_country_postmeta.meta_value IN {$country_query}";
	}

	// add shipping cost
	$clauses[] = "AND shipping_cost_postmeta.meta_key = '_order_shipping'";

	return $clauses;

}

add_filter( 'woocommerce_analytics_clauses_where_orders_subquery', 'add_where_subquery' );
add_filter( 'woocommerce_analytics_clauses_where_orders_stats_total', 'add_where_subquery' );
add_filter( 'woocommerce_analytics_clauses_where_orders_stats_interval', 'add_where_subquery' );

// SELECT

function add_select_subquery( $clauses ) {

	// add shipping country
    $clauses[] = ', shipping_country_postmeta.meta_value AS shipping_country';

	// add shipping cost
	$clauses[] = ', shipping_cost_postmeta.meta_value AS shipping_cost';
 
    return $clauses;
}
 
add_filter( 'woocommerce_analytics_clauses_select_orders_subquery', 'add_select_subquery' );
add_filter( 'woocommerce_analytics_clauses_select_orders_stats_total', 'add_select_subquery' );
add_filter( 'woocommerce_analytics_clauses_select_orders_stats_interval', 'add_select_subquery' );



// Receives an array of shipping zone ids 
// and returns an array of country codes that are included in the zones

function get_country_list($zone_ids) {

	$countries = array();

	foreach($zone_ids as $zone_id) {

		$delivery_zones = WC_Shipping_Zones::get_zones();
		$zone = $delivery_zones[$zone_id];
		

		foreach( $zone["zone_locations"] as $location ) {
			$countries[] = $location->code;
		}

	}

	return $countries;

}

// Receives an array of country codes, and 
// returns a formatted string with country code informarion which, e.g. "('FI', EN, 'SE')"
function get_country_query( $country_list ) {

	$output = '(';

	for ( $i = 0; $i < count( $country_list ); $i++ ) {

		$output .= "'" . $country_list[$i] . "'";

		if ( $i < count( $country_list ) -1 ) {
			$output .= ', ';
		}
	}

	$output .= ')';

	return $output;
}

