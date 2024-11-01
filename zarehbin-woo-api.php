<?php

if (!defined('ABSPATH')) exit;

/**
 * Plugin Name:       ذره بین
 * Plugin URI:        https://www.zarehbin.com/
 * Description:       این افزونه محصولات وبسایت شما را برای موتور جستجوی ذره بین ارسال می نماید
 * Version:           1.0.0
 * Author:            ذره بین
 * Author URI:        https://www.zarehbin.com/
 * Requires PHP:      7.2
 * Requires at least: 4.7
 * Tested up to: 6.1
 * Stable tag: 1.0.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Contributors: (zarehbin)
 * Donate link: https://zarehbin.com/
 * Tags: موتور جستجوی ذره بین, افزونه موتور جستجوی ذره بین
 */

const ZAREHBIN_WOO_API_VERSION = '1.0.0';

define('ZAREHBIN_WOO_API_DIR', trailingslashit(plugin_dir_path(__FILE__)));

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) require_once ZAREHBIN_WOO_API_DIR . 'api.php';
