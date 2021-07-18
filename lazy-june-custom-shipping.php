<?php
 
/**
 * Plugin Name: Lazy June Custom Shipping
 * Plugin URI: https://www.lazy-june.com/
 * Description: Custom Shipping Method made for Shooker
 * Version: 1.0.0
 * Author: Pavel Gomon
 * Author URI: https://www.lazy-june.com/
 * License: GPL-3.0+
 * License URI: https://www.lazy-june.com/
 * Domain Path: /lang
 * Text Domain: lazyjune
 */
 
if ( ! defined( 'WPINC' ) ) {
 
    die;
 
}
 
/*
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
 
    function LazyJune_Shipping_Method() {
        if ( ! class_exists( 'LazyJune_Shipping_Method' ) ) {
            class LazyJune_Shipping_Method extends WC_Shipping_Method {
                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct() {
                    $this->id                 = 'lazyjune'; 
                    $this->method_title       = __( 'Lazy June Shipping', 'lazyjune' );  
                    $this->method_description = __( 'Custom Shipping Method made for Shooker', 'lazyjune' ); 
 
                    // Availability & Countries
                    $this->availability = 'including';
                    $this->countries = array(
                        'IL'
                    );
 
                    $this->init();
 
                    $this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
                    $this->title = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'TutsPlus Shipping', 'lazyjune' );
                }
 
                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init() {
                    // Load the settings API
                    $this->init_form_fields(); 
                    $this->init_settings(); 
 
                    // Save settings in admin if you have any defined
                    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                }
 
                /**
                 * Define settings field for this shipping
                 * @return void 
                 */
                function init_form_fields() { 
 
                    $this->form_fields = array(
 
                     'enabled' => array(
                          'title' => __( 'Enable', 'lazyjune' ),
                          'type' => 'checkbox',
                          'description' => __( 'Enable this shipping.', 'lazyjune' ),
                          'default' => 'yes'
                          ),
 
                     'title' => array(
                        'title' => __( 'Title', 'lazyjune' ),
                          'type' => 'text',
                          'description' => __( 'Title to be display on site', 'lazyjune' ),
                          'default' => __( '', 'lazyjune' )
                          ),
 
                     );
 
                }
 
                /**
                 * This function is used to calculate the shipping cost. 
                 * Within this function Pavel made some cool features
                 * and provided on of the best possible solutions for 
                 * client needs.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping( $package ) {
                    
                    
                    $category_translator = array(
                                                29 => "closet",
                                                30 => "matress",
                                                31 => "bedroom",
                                                32 => "bed",
                                                33 => "bed",
                                                36 => "dresser",
                                                45 => "comoda",
                                                );

                    $single_items_without_combo = array(// item
                                                          "matress" => array("price"=> 190),
                                                          "comoda" => array("price"=> 250),
                                                          "dresser" => array("price"=> 250),
                                                          "bed" => array("price"=> 300),
                                                          "closet" => array("price"=> 400),
                                                          "bedroom" => array("price"=> 450),
                                                      );

                    $duo_combo = array(
                                      // 2 item combinations
                                      "bedroom + matress" => array("price"=> 550, "items" => array("bedroom", "matress")),
                                      "closet + matress" => array("price"=> 500, "items" => array("closet", "matress")),
                                      "closet + comoda" => array("price"=> 500, "items" => array("closet", "comoda")),
                                      "closet + dresser" => array("price"=> 450, "items" => array("closet", "dresser")),
                                      "bedroom + matress" => array("price"=> 400, "items" => array("bed", "matress")),
                                      "bed + comoda" => array("price"=> 400, "items" => array("bed", "comoda")),
                                      "comoda + dresser" => array("price"=> 350, "items" => array("comoda", "dresser")),
                                      "closet + comoda" => array("price"=> 350, "items" => array("closet", "comoda")),
                                      "bed + dresser" => array("price"=> 350, "items" => array("bed", "dresser")),
                                      );

                    $triple_combo = array(
                                      // 3 item combinations
                                      "bedroom + closet + matress" => array("price"=> 950, "items" => array("bedroom", "closet", "matress")),
                                      "closet + bed + matress" => array("price"=> 800, "items" => array("closet", "bed", "matress")),
                                      "bed + comoda + matress" => array("price"=> 500, "items" => array("bed", "comoda", "matress")),
                                      "bed + dresser + matress" => array("price"=> 450, "items" => array("bed", "dresser", "matress")),
                                      );

                    $picked_combinations = array(); // for debug only
                    $shipping_cost = 0;
                    $items = array( "matress" => 0,
                                  "comoda" => 0,
                                  "dresser" => 0,
                                  "bed" => 0,
                                  "closet" => 0,
                                  "bedroom" => 0,
                                  "count"=> 0
                                ); 


                    // load items to correct format
                    foreach ( $package['contents'] as $item_id => $values ) 
                    { 
                        $product_id = $values['product_id'];
                        $category_id = (int)wp_get_post_terms($product_id,'product_cat',array('fields'=>'ids'))[0];
                        $category_name = $category_translator[$category_id];
                        $quantity = (int)$values['quantity'];
                        $items[$category_name] += $quantity;
                        $items['count'] += $quantity;

                        /*
                        echo "<h2>Inner JSON data:</h2><br>";
                        echo "Value from items:{$items[$category_name]}";
                        $json_string = json_encode($values, JSON_PRETTY_PRINT);
                        echo "<br>Values: {$json_string}";
                        echo "<br>item_id: {$item_id}";
                        echo "<br>product_id: {$product_id}";
                        echo "<br>cat_id: {$category_id}";
                        echo "<br>Quantity: {$quantity}<br>";
                        echo get_term_link ($category_id, 'product_cat');
                        echo "<br>cat_name: {$category_name}";
                        */
                    }
                    /*
                    echo "<h3>Main Items array before reduction</h3><br>";
                    $json_string = json_encode($items, JSON_PRETTY_PRINT);
                    echo "items: {$json_string}";
                    */

                    // Reduce bedroom items to "bedroom"
                    while($items["bed"]>=1 && $items["dresser"]>=2 && $items["comoda"]>=1){
                      $items["bed"] -= 1;
                      $items["dresser"] -= 2;
                      $items["comoda"] -= 1;
                      $items["bedroom"] += 1;
                      $items["count"] -= 3;
                    }

                    // Check for triple combinations
                    $found_triple_combo = true;
                    while($items["matress"]>=1 && 
                        ($items["bed"]>=1 || $items["closet"]>=1 || $items["dresser"]>=1 || $items["comoda"]>=1) &&
                        ($items["bed"]>=1 || $items["closet"]>=1 || $items["bedroom"]>=1) &&
                        $found_triple_combo && $items["count"]>=3){
                        $cycles_without_matching_combos = 0;
                        foreach($triple_combo as &$key) {
                          $json_string = json_encode($key, JSON_PRETTY_PRINT);
                          //echo "<br>key: {$json_string}";
                          $first = $key["items"][0];
                          $second = $key["items"][1];
                          $third = $key["items"][2];
                          if($items[$first]>=1 && $items[$second]>=1 && $items[$third]>=1) {
                            //echo "<br>Found triple combination";
                            $shipping_cost += $key["price"];
                            $items[$first] -= 1;
                            $items[$second] -= 1;
                            $items[$third] -= 1;
                            $items['count'] -= 3;
                            array_push($picked_combinations, $key);
                            } else {
                              $cycles_without_matching_combos += 1;
                            }
                      }
                      if($cycles_without_matching_combos === 4) {
                        $found_triple_combo = false;
                        }
                    }   
                    /*
                    echo "<h3>Main Items array after triple combinations</h3><br>";
                    $json_string = json_encode($items, JSON_PRETTY_PRINT);
                    echo "items: {$json_string}";
                    */

                    // Check for duo combinations
                    $found_duo_combo = true;
                    while(($items["comoda"]>=1 || $items["dresser"]>=1 || $items["matress"]>=1) &&
                        ($items["closet"]>=1 || $items["bedroom"]>=1 || $items["bed"]>=1 || $items["comoda"]>=1) &&
                        $found_duo_combo && $items["count"]>=2) {
                      //echo "<br>In duo combo while loop";
                      $cycles_without_matching_combos = 0;
                      foreach($duo_combo as &$key) {
                        $first = $key["items"][0];
                        $second = $key["items"][1];
                        //echo "<br>first: {$first}, second: {$second}";
                        if($items[$first]>=1 && $items[$second]>=1) {
                          //echo "found combination!";
                          $shipping_cost += $key["price"];
                          $items[$first] -= 1;
                          $items[$second] -= 1;
                          $items["count"] -= 2;
                          array_push($picked_combinations, $key);
                          } else {
                            //echo "didn't found combo, incrementing 'cycles_without_matching_combos'";
                            $cycles_without_matching_combos += 1;
                          }
                        
                      }
                      if($cycles_without_matching_combos === 4) {
                        $found_duo_combo = false;
                        }
                    }
                    /*
                    echo "<h3>Main Items array after duo combinations</h3><br>";
                    $json_string = json_encode($items, JSON_PRETTY_PRINT);
                    echo "items: {$json_string}";
                    */

                    // Check for single items
                    while($items["count"] > 0) {
                      foreach($items as $key => $value) {
                        if($value > 0 && $key != "count") {
                          //echo "<br>foreach key: {$key}, value: {$value}";
                          $shipping_cost += $single_items_without_combo[$key]["price"];
                          $items[$key] -= 1;
                          $items["count"] -= 1;
                          array_push($picked_combinations, $key);
                        }
                      }
                    }
                    /*
                    echo "<h3>Main Items array after single items</h3><br>";
                    $json_string = json_encode($items, JSON_PRETTY_PRINT);
                    echo "items: {$json_string}";
                    */
 
                    $shipping_cost += 0;
 
                    $rate = array(
                        'id' => $this->id,
                        'label' => $this->title,
                        'cost' => $shipping_cost,
                        'calc_tax' => 'per_order'
                    );
 
                    $this->add_rate( $rate );
                    
                }
            }
        }
    }
 
    add_action( 'woocommerce_shipping_init', 'LazyJune_Shipping_Method' );
 
    function add_LazyJune_Shipping_Method( $methods ) {
        $methods[] = 'LazyJune_Shipping_Method';
        return $methods;
    }
 
    add_filter( 'woocommerce_shipping_methods', 'add_LazyJune_Shipping_Method' );

 
    
}