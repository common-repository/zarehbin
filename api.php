<?php

if (!defined('ABSPATH')) exit;

class ZarehbinWooApi
{

    private $VERSION = '1';
    private $NAMESPACE = 'zarehbin';

    public function __construct()
    {
        add_action('rest_api_init', function () {
            $route = 'products';
            register_rest_route("$this->NAMESPACE/v$this->VERSION", "/$route", [
                'methods' => 'POST',
                'callback' => [
                    $this,
                    'getData'
                ],
                'permission_callback' => '__return_true',
                'args' => []
            ]);
        });

        add_action('http_api_curl', function ($handle) {
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 12);
            curl_setopt($handle, CURLOPT_TIMEOUT, 12);
        }, 9999, 1);

        add_filter('http_request_timeout', function ($timeout_value) {
            return 12;
        }, 9999);

        add_filter('http_request_args', function ($request) {
            $request['timeout'] = 12;
            return $request;
        }, 9999, 1);
    }

    public function getData($request)
    {

        $token = str_replace('Bearer ', '', $request->get_header('authorization'));

        $token_verify = self::verifyToken($token);

        if (!$token_verify->status)
            return new WP_Error('authorization_error', $token_verify->message, ['status' => 403]);

        $product_id = intval($request->get_param('product_id'));

        $query = new WP_Query(['post_type' => 'product', 'posts_per_page' => -1]);

        $count = $product_id != 0 ? 1 : $query->found_posts;
        $page_id = intval($request->get_param('page'));
        $posts_per_page = $request->get_param('count');

        if ($page_id == 0) $page_id = 1;
        if ($posts_per_page == 0) $posts_per_page = 50;

        $total_page = $product_id != 0 ? 1 : ceil($count / $posts_per_page);

        if ($product_id != 0) $products[] = self::getProduct($product_id);

        else {
            $posts = get_posts([
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => $posts_per_page,
                'offset' => ($page_id * $posts_per_page) - $posts_per_page
            ]);

            if (!empty($posts)) $products = self::getProducts($posts);
        }

        return [
            'code' => 'success',
            'message' => 'درخواست موفق بود',
            'data' => [
                'status' => 200,
                'count' => $count ?? 1,
                'current_page' => $page_id ?? 1,
                'total_page' => $total_page ?? 1,
                'products' => $products ?? []
            ]
        ];
    }

    private function getProducts($posts): array
    {
        $products = [];
        foreach ($posts as $post)
            $products[] = self::getProduct($post->ID);
        return $products;
    }

    private function getProduct($product_id): array
    {
        $product = wc_get_product($product_id);

        $product_price = self::getProductPrice($product);

        return [
            'id' => $product->get_id(),
            'sku' => $product->get_sku(),
            'title' => $product->get_title(),
            'stock' => $product_price['stock'],
            'regular_price' => $product_price['regular_price'],
            'sale_price' => $product_price['sale_price'],
            'categories' => explode(',', sanitize_text_field(wc_get_product_category_list($product->get_id()))),
            'images' => self::getImages($product),
            'url' => $product->get_permalink(),
            'attributes' => self::getProductAttributes($product)
        ];
    }

    private function getImages($product): array
    {
        $images = [];
        if (!empty($product->get_image_id()))
            $images[] = wp_get_attachment_url($product->get_image_id());
        foreach ($product->get_gallery_image_ids() as $gallery_image_id)
            $images[] = wp_get_attachment_url($gallery_image_id);
        return $images;
    }

    private function getProductPrice($product): array
    {
        if ($product->get_type() == 'variable') {
            $regular_price = 0;
            $sale_price = 0;
            $stock = 'outofstock';
            $prices = $product->get_variation_prices()['price'];
            foreach ($prices as $variation => $price) {
                $child_product = wc_get_product($variation);
                if ($child_product->get_stock_status() == 'instock') {
                    $save_sale_price = (empty($child_product->get_sale_price()) ? (int)$child_product->get_regular_price() : (int)$child_product->get_sale_price());
                    if ($save_sale_price < $sale_price or $sale_price == 0) {
                        $regular_price = (int)$child_product->get_regular_price();
                        $sale_price = (empty($child_product->get_sale_price()) ? (int)$child_product->get_regular_price() : (int)$child_product->get_sale_price());
                        $stock = $product->get_stock_status();
                    }
                }
            }
        } else {
            $regular_price = (int)$product->get_regular_price();
            $sale_price = (empty($product->get_sale_price()) ? (int)$product->get_regular_price() : (int)$product->get_sale_price());
            $stock = $product->get_stock_status();
        }

        return [
            'regular_price' => $regular_price,
            'sale_price' => $sale_price,
            'stock' => $stock
        ];
    }

    private function getProductAttributes($product): array
    {
        $formatted_attributes = array();
        $attributes = $product->get_attributes();
        foreach ($attributes as $attr => $attr_deets) {
            $attribute_label = wc_attribute_label($attr);
            if (isset($attr_deets) || isset($attributes['pa_' . $attr])) {
                $attribute = $attr_deets ?? $attributes['pa_' . $attr];
                if ($attribute['is_taxonomy']) {
                    $formatted_attributes[] = [
                        'title' => $attribute_label,
                        'value' => implode(', ', wc_get_product_terms($product->id, $attribute['name'], array('fields' => 'names')))];
                } else {
                    $formatted_attributes[] = [
                        'title' => $attribute_label,
                        'value' => $attribute['value']];
                }
            }
        }

        return $formatted_attributes;
    }

    private function verifyToken($token): object
    {
        $response = wp_remote_post('https://www.zarehbin.com/bots/api/auth',
            [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'token' => $token,
                    'domain' => str_replace(['https://', 'http://', 'www.'], '', site_url()),
                    'version' => ZAREHBIN_WOO_API_VERSION
                ]),
                'data_format' => 'body',
            ]);

        if (is_wp_error($response)) return (object)['status' => false, 'message' => 'خطایی در ارتباط با وب سرویس ذره بین رخ داد.'];
        $data = json_decode($response['body'] ?? '');
        if (!isset($data->status)) return (object)['status' => false, 'message' => 'وب سرویس ذره بین اطلاعات معتبری ارسال نمی کند.'];
        if ($data->status == 200 and $data->success) return (object)['status' => true, 'message' => 'توکن معتبر است.'];
        return (object)['status' => false, 'message' => is_null($data->error) ? 'درخواست غیرمجاز است.' : $data->error];
    }
}

new ZarehbinWooApi();

