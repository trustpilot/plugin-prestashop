<?php
/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

if (!defined('TP_PATH_ROOT')) {
    define('TP_PATH_ROOT', dirname(__FILE__));
}

include_once TP_PATH_ROOT . '/config.php';
include_once TP_PATH_ROOT . '/pastOrders.php';

class TrustpilotOrders
{
    public function __construct($context)
    {
        $this->context = $context;
        $this->orderKeyName = 'order';
        if (_PS_VERSION_ <  '1.7') {
            $this->orderKeyName = 'objOrder';
        }
    }

    public function getInvitation($params, $hook, $collectProductData = true)
    {
        $order = null;
        $orderStatusId = null;
        $orderStatusName = null;

        if (isset($params['id_order'])) {
            $order = new Order((int)$params['id_order']);
            if (isset($params['newOrderStatus'])) {
                $orderStatusId = $params['newOrderStatus']->id;
                $orderStatusName = $params['newOrderStatus']->name;
            }
        } else {
            $order = $params[$this->orderKeyName];
            $orderStatusId = $order->current_state;
            $orderStatusName = $order->payment;
        }

        $invitation = $this->prepareData(
            $order->reference,
            $orderStatusId,
            $orderStatusName,
            $hook
        );

        $invitation['recipientEmail'] = $this->getEmail($order);
        $invitation['recipientName'] = $this->getCustomerName($order);
        $invitation['templateParams'] =
            TrustpilotConfig::getInstance()->getIdsForConfigurationScope(
                $this->getGroupId(),
                $this->getShopId(),
                $this->getLanguageId()
            );

        if ($collectProductData) {
            $products = $this->getProducts($order);
            $invitation['productSkus'] = $this->getSkus($products);
            $invitation['products'] = $products;
        }
        return $invitation;
    }

    public function handleSingleResponse($response, $order)
    {
        try {
            $past_orders = new TrustpilotPastOrders($this->context);
            $synced_orders = (int)$past_orders->getTrustpilotField('past_orders');
            $failed_orders = json_decode($past_orders->getTrustpilotField('failed_orders'));

            if ($response['code'] == 201) {
                $synced_orders = (int)($synced_orders) + 1;
                $past_orders->setTrustpilotField('past_orders', $synced_orders);
                if (isset($failed_orders->{$order['referenceId']})) {
                    unset($failed_orders->{$order['referenceId']});
                    $past_orders->setTrustpilotField('failed_orders', json_encode($failed_orders));
                }
            } else {
                $failed_orders->{$order['referenceId']} = base64_encode('Automatic invitation sending failed');
                $past_orders->setTrustpilotField('failed_orders', json_encode($failed_orders));
            }
        } catch (\Throwable $e) {
            $message = 'Unable to update past orders';
            Logger::addLog($message . ' Error: ' . $e->getMessage(), 2);
            Module::getInstanceByName('trustpilot')->logError($e, $message);
        } catch (\Exception $e) {
            $message = 'Unable to update past orders';
            Logger::addLog($message . ' Error: ' . $e->getMessage(), 2);
            Module::getInstanceByName('trustpilot')->logError($e, $message);
        }
    }

    private function prepareData($orderReference, $orderStatusId, $orderStatusName, $hook)
    {
        return array(
            'referenceId' => $orderReference,
            'source' => 'PrestaShop-'._PS_VERSION_,
            'pluginVersion' => TrustpilotConfig::getInstance()->version,
            'orderStatusId' => $orderStatusId,
            'orderStatusName' => $orderStatusName,
            'hook' => $hook
        );
    }

    private function getProducts($order)
    {
        $products_array = array();
        $products = $order->getProducts();
        $id_lang = (int)$this->context->language->id;
        foreach ($products as $p) {
            $product = new Product((int)$p['id_product'], true, $id_lang);
            $combinations = (isset($p['product_attribute_id']))
                ? $product->getAttributeCombinationsById((int)$p['product_attribute_id'], $id_lang)
                : array();
            $combination = count($combinations) > 0 ? (object) $combinations[0] : null;
            $image = Image::getCover((int)$p['id_product']);
            $product_link = $product->getLink();
            $image_url = $this->context->link->getImageLink($product->link_rewrite, $image['id_image']);
            $currency = new CurrencyCore($order->id_currency);
            $description = !empty($product->description) ? $product->description : $product->description_short;
            $images = $this->getProductImages($product, $combination, $id_lang);
            $productId = isset($combination) ? $p['product_attribute_id'] : $p['id_product'];
            array_push(
                $products_array,
                array(
                    'productId' => $productId,
                    'productUrl' => $product_link,
                    'name' => $product->name,
                    'brand' => !empty($product->manufacturer_name) ? $product->manufacturer_name : '',
                    'sku' => $this->getAttribute($product, $combination, 'skuSelector', $id_lang),
                    'gtin' => $this->getAttribute($product, $combination, 'gtinSelector', $id_lang),
                    'mpn' => $this->getAttribute($product, $combination, 'mpnSelector', $id_lang),
                    'imageUrl' => $image_url,

                    'price' => number_format($product->getPrice(), 2),
                    'currency' => $currency->iso_code,
                    'categories' => $this->getProductCategories($product),
                    'description' => strip_tags($description),
                    'images' => $images,
                    'videos' => null,
                    'tags' => explode(',', $product->getTags($id_lang)),
                    'meta' => array(
                        'title' => !empty($product->meta_title) ? $product->meta_title : $product->name,
                        'description' => $product->meta_description,
                        'keywords' => $product->meta_keywords,
                    ),
                    'manufacturer' => $product->manufacturer_name ? (string)($product->manufacturer_name) : null,
                )
            );
        }

        return $products_array;
    }

    public function getAttribute($product, $combination, $attr, $id_lang, $useDbField = true)
    {
        $selector = $useDbField ? TrustpilotConfig::getInstance()->getFromMasterSettings($attr) : $attr;
        if ($selector == 'none' || $selector == '') {
            return '';
        }

        if (isset($combination) && !empty($combination->{$selector})) {
            return $combination->{$selector};
        }
        
        if (!empty($product->{$selector})) {
            return $product->{$selector};
        }

        $features = $product->getFrontFeatures($id_lang);
        foreach ($features as $feature) {
            if ($feature['name'] == $selector) {
                return $feature['value'];
            }
        }
        return '';
    }

    private function getEmail($order)
    {
        if (!empty($order->id_customer)) {
            $customer = new Customer((int)$order->id_customer);
            return $customer->email;
        } elseif (!empty($this->context->customer->email)) {
            return $this->context->customer->email;
        } elseif (!empty($this->context->cookie->email)) {
            return $this->context->cookie->email;
        } else {
            try {
                $id_order = (int)$order->id;
                $sql = '
                SELECT email
                FROM `'._DB_PREFIX_.'orders` as po
                left join  `'._DB_PREFIX_.'customer` as pc
                on po.id_customer = pc.id_customer
                WHERE `id_order` = \''.$id_order.'\'';
                $email = Db::getInstance()->getValue($sql);
                return $email;
            } catch (\Throwable $e) {
                $message = 'Failed to get customer email';
                Module::getInstanceByName('trustpilot')->logError($e, $message);
                return false;
            } catch (\Exception $e) {
                $message = 'Failed to get customer email';
                Module::getInstanceByName('trustpilot')->logError($e, $message);
                return false;
            }
        }
    }

    private function getGroupId()
    {
        if (!empty($this->context->shop) && !empty($this->context->shop->id_shop_group)) {
            return $this->context->shop->id_shop_group;
        } else {
            return $this->context->cookie->id_shop_group;
        }
    }

    private function getShopId()
    {
        if (!empty($this->context->shop) && !empty($this->context->shop->id)) {
            return $this->context->shop->id;
        } else {
            return $this->context->cookie->id_shop;
        }
    }

    private function getLanguageId()
    {
        if (!empty($this->context->language) && !empty($this->context->language->id)) {
            return $this->context->language->id;
        } else {
            return $this->context->cookie->id_lang;
        }
    }

    private function getCustomerName($order)
    {
        if (!empty($order->id_customer)) {
            $customer = new Customer((int)$order->id_customer);
            return $customer->firstname . ' ' . $customer->lastname;
        } elseif (!empty($this->context->customer->firstname) && !empty($this->context->customer->lastname)) {
            return $this->context->customer->firstname. ' '. $this->context->customer->lastname;
        } else {
            return $this->context->cookie->customer_firstname. ' '. $this->context->cookie->customer_lastname;
        }
    }

    private function getSkus($products)
    {
        $skus = array();
        foreach ($products as $product) {
            array_push($skus, $product['sku']);
        }
        return $skus;
    }

    private function getProductCategories($product)
    {
        $categories = array();
        $productCategoriesFull = $product->getProductCategoriesFull($product->id);
        foreach ($productCategoriesFull as $category) {
            array_push($categories, $category['name']);
        }
        return $categories;
    }


    // For backward compatibility with PS < 1.7.0.0
    // Ref: https://github.com/PrestaShop/PrestaShop/blob/develop/classes/ImageType.php#L174
    private function getFormattedName($name) {
        if (method_exists("ImageType", "getFormattedName")) {
            return ImageType::getFormattedName($name);
        } else {
            return ImageType::getFormatedName($name);
        }
    }

    private function getProductImages($product, $combination, $id_lang)
    {
        $images = array();
        $productImages = array();

        // Get link object with the protocol
        $protocol_link = (Configuration::get('PS_SSL_ENABLED') || Tools::usingSecureMode()) ? 'https://' : 'http://';
        $useSSL = ((isset($this->ssl) && $this->ssl && Configuration::get('PS_SSL_ENABLED')) || Tools::usingSecureMode()) ? true : false;
        $protocol_content = ($useSSL) ? 'https://' : 'http://';
        $link = new Link($protocol_link, $protocol_content);

        if ($combination) {
            $combinations = $product->getCombinationImages($id_lang);
            foreach ((array) $combinations as $combinationImages) {
                if ($combination->id_product_attribute == $combinationImages[0]['id_product_attribute']) {
                    $productImages = $combinationImages;
                    break;
                }
            }
        } else {
            $productImages = $product->getImages($id_lang);
        }

        foreach ($productImages as $image) {
            $imagePath = $link->getImageLink($product->link_rewrite, $image['id_image'], $this->getFormattedName('home'));
            array_push($images, $imagePath);
        }
        return $images;
    }
}
