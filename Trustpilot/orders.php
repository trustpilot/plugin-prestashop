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

class Trustpilot_Orders
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
            $past_orders = new Trustpilot_PastOrders($this->context);
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
        } catch (Exception $e) {
            $message = 'Unable to update past orders. Error: ' . $e->getMessage();
            Logger::addLog($message, 2);
        }
    }

    private function prepareData($orderReference, $orderStatusId, $orderStatusName, $hook)
    {
        return array(
            'referenceId' => $orderReference,
            'source' => 'PrestaShop-'._PS_VERSION_,
            'pluginVersion' => Trustpilot_Config::getInstance()->version,
            'orderStatusId' => $orderStatusId,
            'orderStatusName' => $orderStatusName,
            'hook' => $hook
        );
    }

    private function getProducts($order)
    {
        $config = Trustpilot_Config::getInstance();
        $products_array = array();
        $products = $order->getProducts();
        $id_lang = $this->context->language->id;
        foreach ($products as $p) {
            $skuSelector = $config->getFromMasterSettings('skuSelector');
            $gtinSelector = $config->getFromMasterSettings('gtinSelector');
            $mpnSelector = $config->getFromMasterSettings('mpnSelector');
            $sku = $skuSelector != 'none' && $skuSelector != '' ? $p[$skuSelector] : '';
            $gtin =  $gtinSelector != 'none' && $gtinSelector != '' && !empty($p[$gtinSelector]) ? $p[$gtinSelector] : '';
            $mpn = $mpnSelector != 'none' && $mpnSelector != '' && !empty($p[$mpnSelector]) ? $p[$mpnSelector] : '';
            $product = new Product((int)$p['id_product'], true, (int)$id_lang);
            $image = Image::getCover((int)$p['id_product']);
            $product_link = $product->getLink();
            $image_url = $this->context->link->getImageLink($product->link_rewrite, $image['id_image']);
            array_push(
                $products_array,
                array(
                    'productUrl' => $product_link,
                    'name' => $product->name,
                    'brand' => !empty($product->manufacturer_name) ? $product->manufacturer_name : '',
                    'sku' => $sku,
                    'gtin' => $gtin,
                    'mpn' => $mpn,
                    'imageUrl' => $image_url,
                )
            );
        }

        return $products_array;
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
            } catch (Exception $e) {
                return false;
            }
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
}