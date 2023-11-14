<?php

/**
 * Plugin Name: Listings import for GR
 * Description: Imports Listings from the given CSV file
 * Author: Hayk Balbabyan.
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
 
class GR_Listings_Import
{

    function __construct()
    {
        add_action( 'admin_menu', array($this, 'add_settings_page') );
        add_action( 'init', array($this, 'register_custom_post_type') );
        add_action( 'admin_head', array($this, 'add_inline_styles') );
        add_action( 'admin_import_listings_records_csv', array($this, 'import_listings_records_csv') );
        add_action( 'admin_delete_last_imported_listings', array($this, 'delete_last_imported_listings') );
    }

    public function add_settings_page()
    {
        add_submenu_page(
            'edit.php?post_type=csv_import',
            'Csv data', // Page title
            'import Csv Data',
            'manage_options',
            'import_csv',
            array($this, 'render_settings_page')
        );
    }

    public function register_custom_post_type()
    {
        register_post_type('csv_import', array(
            'labels'             => array(
                'name'               => 'Csv Import',
                'singular_name'      => 'csv_import',
                'add_new'            => 'Add Csv import',
                'add_new_item'       => 'add new item Csv import',
                'edit_item'          => 'edit item Csv import',
                'new_item'           => 'new item Csv import',
                'view_item'          => 'view item Csv import',
                'search_items'       => 'search items Csv import',
                'not_found'          => 'Csv import not found',
                'not_found_in_trash' => 'in trash Csv import not found',
                'parent_item_colon'  => '',
                'menu_name'          => 'Csv import'
            ),
            'menu_icon'          => 'dashicons-format-gallery',
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
        ) );
    }

    public function add_inline_styles()
    {
        ?>
        <style>
            .big_container{
                margin-top: 50px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: space-between;
            }
            .mt-1{
                margin-top:10px;
            }
            .mt-3{
                margin-top:30px;
            }
            .upload_form {
                display: flex;
                flex-direction: column;
                /*align-items: baseline;*/
                justify-content: space-between;
                gap: 20px;
            }
        </style>
        <?php
    }
    
    public function delete_last_imported_listings()
    {
        global $wpdb;
        
        $sql = "
            SELECT post_id, meta_value
            FROM $wpdb->postmeta
            WHERE meta_key = 'last_upload_time'
                AND meta_value = (
                    SELECT MAX(meta_value)
                    FROM $wpdb->postmeta
                    WHERE meta_key = 'last_upload_time'
                )
        ";
        
        $latest_uploads = $wpdb->get_results($sql);
        $data = '';
        if(!empty($latest_uploads)){
            foreach($latest_uploads as $post){
                $data = $post->meta_value;
                $post_id = $post->post_id;
                wp_delete_post( $post_id, true );
                delete_post_meta($post_id, '', '');
            }
            setcookie("deleted_upload_csv", $data, time() + (2000 * 30), "/");
        }else{
            setcookie("empty_upload_csv", '1', time() + (2000 * 30), "/");
        }
        wp_redirect($_SERVER['HTTP_REFERER'] . '&tab=auth');
        exit;
    }
    
    public function import_listings_records_csv()
    {

        if(!empty($_FILES['csv_file']) &&  $_FILES['csv_file']['error'] == UPLOAD_ERR_OK ){
       
            $file = $_FILES["csv_file"]["tmp_name"];
            $cat_id = '';
            if(!empty($_POST['cat_id'])){
                $cat_id = $_POST['cat_id'];
            }

            if(($handle = fopen($file, "r")) !== false){

                global $wpdb;

                $flag = 0;
                $time_set = time();
                while (($data = fgetcsv($handle, 1000, ",")) !== false){
                    $flag++;
                    if($flag <= 1){
                        continue;
                    }

                    if(!empty($data) && !empty($data[0])){
                        $business_name = $data[0]??'';
                        $GoogleAddress = $data[1]??'';
                        $ZipCode = $data[2]??'';
                        $AddressLine = $data[3]??'';
                        $Website = $data[4]??'';
                        $Facebook =  $data[5]??'';
                        $Phone = $data[6]??'';
                        $email = $data[7]??'';
                        
                        $zips_table = $wpdb->prefix . 'zip_data';
                        $sql = "select zip_id from $zips_table where  zip_name = '{$ZipCode}'";
                        $data_zip = $wpdb->get_row($sql);
    
                        $zip_id = !empty($data_zip) && !empty($data_zip->zip_id)? $data_zip->zip_id : '';
                        
                        $post_data = array(
                            'post_title' => $business_name,
                            'post_content' => '',
                            'post_type' => 'listing',
                            'tax_input' => array(
                                'listing-category' => [$cat_id],
    
                            ),
                            'post_status' =>  'pending' // 'publish'
                        );
    
                        $post_id = wp_insert_post($post_data);
    
    
                        listing_set_metabox('gAddress', $GoogleAddress, $post_id);
                        listing_set_metabox('facebook', $Facebook, $post_id);
                        listing_set_metabox('website', $Website, $post_id);
                        listing_set_metabox('phone', $Phone, $post_id);
                        listing_set_metabox('claimed_section', 'not_claimed', $post_id);
                        listing_set_metabox('email', $email, $post_id);
    
                        update_post_meta(  $post_id, 'zip', $zip_id);
                        update_post_meta(  $post_id, 'online_shop_url', $Website);
                        update_post_meta(  $post_id, 'product_cats', $cat_id);
                        update_post_meta(  $post_id, 'street_address_1', $AddressLine);
                        update_post_meta(  $post_id, 'last_upload_time', $time_set);
                        
                    }
                    
                }

                fclose($handle);
                setcookie("listings_uploaded_csv", '1', time() + (2000 * 30), "/");
                
            }
        }
        wp_redirect($_SERVER['HTTP_REFERER'] . '&tab=auth');
    }

    public function render_settings_page()
    {
        
        $main_cats = get_terms(
        [
            'taxonomy' => 'listing-category',
            'orderby'  => 'include',
            'hide_empty' => 0,
        ]);
        
        ?>
        
        <div class="big_container">
            <?php
            if(isset($_COOKIE['empty_upload_csv'])){ ?>
                <div class="container mt-1">
                    <p style="color:red">You dont have uploaded Listings</p>
                </div>
            <?php 
                setcookie("empty_upload_csv", '0', time() + (1 * 30), "/");
            }
            if(isset($_COOKIE['deleted_upload_csv'])){
                $data =  date('Y-m-d H:i:s', $_COOKIE['deleted_upload_csv']);?>
                <div class="container mt-1">
                    <p style="color:red">Deleted data on <?= $data?></p>
                </div>
            <?php 
                 setcookie("deleted_upload_csv", 0, time() , "/"); 
            }
            if(isset($_COOKIE['listings_uploaded_csv'])){?>
                <div class="container mt-1">
                    <p style="color:green">Listings are uploaded</p>
                </div>
            <?php 
                 setcookie("listings_uploaded_csv", 0, time() , "/"); 
            }
            ?>
            <div class="container mt-3">
                <form action="<?= admin_url('admin-post.php')?>" method="POST" enctype="multipart/form-data" class="upload_form"> 
                    <input type="hidden" name="action" value="import_company_records_csv"/>
                    
                      <select name="cat_id" >
                           <?php
                            if(!empty($main_cats)){
                                foreach($main_cats as  $result){ ?>
                                    <option value="<?=$result->term_id?>" selected><?=$result->name??'';?></option>
                                <?php
                                }
                            }
                        ?>
                    </select>
                    <input type="file" name="csv_file" id="" required>
                    <button type="submit" class="button">Importxx</button>
                </form>
            </div>
            
            <div class="container mt-3">
                <form action="<?= admin_url('admin-post.php')?>" method="POST" enctype="multipart/form-data"> 
                    <input type="hidden" name="action" value="delete_last_import_company"/>
                    <button type="submit" class="button" style=" color: red; border-color: red;">Delete Last</button>
                </form>
            </div>
        </div>
    
        <?php 
    }
}
if (class_exists('GR_Listings_Import')) {
    $casino = new GR_Listings_Import();
}

