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

define('WITH_PRODUCT_DATA', 'WITH_PRODUCT_DATA');
define('WITHOUT_PRODUCT_DATA', 'WITHOUT_PRODUCT_DATA');

include_once TP_PATH_ROOT . '/config.php';
include_once TP_PATH_ROOT . '/orders.php';
include_once TP_PATH_ROOT . '/apiClients/TrustpilotHttpClient.php';

class TrustpilotPastOrders
{
    public function __construct($context, $context_scope = null)
    {
        $this->orders = new TrustpilotOrders($context);
        $this->trustpilot_api = new TrustpilotHttpClient(TrustpilotConfig::getInstance()->apiUrl);
        $this->context_scope = $context_scope;
    }

    public function sync($period_in_days)
    {
        $this->setTrustpilotField('sync_in_progress', 'true');
        $this->setTrustpilotField('show_past_orders_initial', 'false');
        try {
            $key = TrustpilotConfig::getInstance()->getFromMasterSettings('general')->key;
            $collect_product_data = WITHOUT_PRODUCT_DATA;
            if (!is_null($key)) {
                $this->setTrustpilotField('past_orders', 0);
                $pageId = 1;
                $post_batch = $this->getInvitationsForPeriod($period_in_days, $collect_product_data, $pageId);
                while ($post_batch) {
                    set_time_limit(30);
                    $batch = null;
                    if (!is_null($post_batch)) {
                        $batch['invitations'] = $post_batch;
                        $batch['type'] = $collect_product_data;
                        $response = $this->trustpilot_api->postBatchInvitations($key, $batch);
                        $code = $this->handleTrustpilotResponse($response, $batch);
                        if ($code == 202) {
                            $collect_product_data = WITH_PRODUCT_DATA;
                            $batch['invitations'] = $this->getInvitationsForPeriod($period_in_days, $collect_product_data, $pageId);
                            $batch['type'] = $collect_product_data;
                            $response = $this->trustpilot_api->postBatchInvitations($key, $batch);
                            $code = $this->handleTrustpilotResponse($response, $batch);
                        }
                        if ($code < 200 || $code > 202) {
                            $this->setTrustpilotField('show_past_orders_initial', 'true');
                            $this->setTrustpilotField('sync_in_progress', 'false');
                            $this->setTrustpilotField('past_orders', 0);
                            $this->setTrustpilotField('failed_orders', '{}');
                            return;
                        }
                    }
                    $pageId = $pageId + 1;
                    $post_batch = $this->getInvitationsForPeriod($period_in_days, $collect_product_data, $pageId);
                }
            }
        } catch (\Throwable $e) {
            $message = 'Failed to sync past orders';
            Logger::addLog($message . ' Error: ' . $e->getMessage(), 2);
            Module::getInstanceByName('trustpilot')->logError($e, $message);
        } catch (\Exception $e) {
            $message = 'Failed to sync past orders';
            Logger::addLog($message . ' Error: ' . $e->getMessage(), 2);
            Module::getInstanceByName('trustpilot')->logError($e, $message);
        }
        $this->setTrustpilotField('sync_in_progress', 'false');
    }

    public function resync()
    {
        $this->setTrustpilotField('sync_in_progress', 'true');
        try {
            $key = TrustpilotConfig::getInstance()->getFromMasterSettings('general')->key;
            $collect_product_data = WITHOUT_PRODUCT_DATA;
            $failed_orders_object = (array) json_decode($this->getTrustpilotField('failed_orders'));
            if (!is_null($key)) {
                $failed_orders_array = array();
                foreach (array_keys($failed_orders_object) as $id) {
                    array_push($failed_orders_array, $id);
                }

                $chunked_failed_orders = array_chunk($failed_orders_array, 20, true);
                foreach ($chunked_failed_orders as $failed_orders_chunk) {
                    set_time_limit(30);
                    $post_batch = $this->getInvitationsByRefs($collect_product_data, $failed_orders_chunk);

                    $batch = null;
                    $batch['invitations'] = $post_batch;
                    $batch['type'] = $collect_product_data;
                    $response = $this->trustpilot_api->postBatchInvitations($key, $batch);
                    $code = $this->handleTrustpilotResponse($response, $batch);
                    if ($code == 202) {
                        $collect_product_data = WITH_PRODUCT_DATA;
                        $batch['invitations'] = $this->getInvitationsByRefs($collect_product_data, $failed_orders_chunk);
                        $batch['type'] = $collect_product_data;
                        $response = $this->trustpilot_api->postBatchInvitations($key, $batch);
                        $code = $this->handleTrustpilotResponse($response, $batch);
                    }
                    if ($code < 200 || $code > 202) {
                        $this->setTrustpilotField('sync_in_progress', 'false');
                        return;
                    }
                }
            }
        } catch (\Throwable $e) {
            $message = 'Failed to sync past orders';
            Logger::addLog($message . 'Error: ' . $e->getMessage(), 2);
            Module::getInstanceByName('trustpilot')->logError($e, $message);
        } catch (\Exception $e) {
            $message = 'Failed to sync past orders';
            Logger::addLog($message . 'Error: ' . $e->getMessage(), 2);
            Module::getInstanceByName('trustpilot')->logError($e, $message);
        }
        $this->setTrustpilotField('sync_in_progress', 'false');
    }

    public function getPastOrdersInfo()
    {
        $syncInProgress = $this->getTrustpilotField('sync_in_progress');
        $showInitial = $this->getTrustpilotField('show_past_orders_initial');
        if ($syncInProgress === 'false') {
            $synced_orders = (int)$this->getTrustpilotField('past_orders');
            $failed_orders = json_decode($this->getTrustpilotField('failed_orders'));

            $failed_orders_result = array();
            foreach ($failed_orders as $key => $value) {
                $item = array(
                    'referenceId' => $key,
                    'error' => $value
                );
                array_push($failed_orders_result, $item);
            }

            return array(
                'pastOrders' => array(
                    'synced' => $synced_orders,
                    'unsynced' => count($failed_orders_result),
                    'failed' => $failed_orders_result,
                    'syncInProgress' => $syncInProgress === 'true',
                    'showInitial' => $showInitial === 'true',
                )
            );
        } else {
            return array(
                'pastOrders' => array(
                    'syncInProgress' => $syncInProgress === 'true',
                    'showInitial' => $showInitial === 'true',
                )
            );
        }
    }

    private function getInvitationsForPeriod($period_in_days, $collect_product_data, $pageId)
    {
        $date = new DateTime();
        $args = array(
            'date_created' => "> '" . ($date->setTimestamp(time() - (86400 * $period_in_days))->format('Y-m-d H:i:s') ) . "'",
            'limit' => 20,
            'paged' => $pageId,
            'past_order_statuses' => TrustpilotConfig::getInstance()->getFromMasterSettings('pastOrderStatuses')
        );
        $order_ids = $this->getPastOrdersIds($args);
        return $this->getInvitationsByOrderIds($collect_product_data, $order_ids);
    }

    private function getInvitationsByOrderIds($collect_product_data, $order_ids)
    {
        $invitations = array();

        foreach ($order_ids as $order_id) {
            $order = $this->orders->getInvitation($order_id, 'past-orders', $collect_product_data == WITH_PRODUCT_DATA);
            array_push($invitations, $order);
        }

        return $invitations;
    }

    private function getInvitationsByRefs($collect_product_data, $order_refs)
    {
        $db_prefix = _DB_PREFIX_;
        $query = "SELECT o.id_order FROM {$db_prefix}orders o
          WHERE o.reference IN (\"".implode('","', $order_refs)."\")
        ";
        $order_ids = Db::getInstance()->ExecuteS($query);
        return $this->getInvitationsByOrderIds($collect_product_data, $order_ids);
    }

    public function setTrustpilotField($field, $value)
    {
        TrustpilotConfig::getInstance()->setConfigValues($field, $value, $this->context_scope);
    }

    public function getTrustpilotField($field)
    {
        return TrustpilotConfig::getInstance()->getConfigValues($field, true, $this->context_scope);
    }

    private function getPastOrdersIds($args)
    {
        $orders = array();
        $limit = $args['limit'];
        $offset = ($args['limit'] * $args['paged']) - $args['limit'];
        $date_created = $args['date_created'];
        $statuses = implode(',', $args['past_order_statuses']);
        $db_prefix = _DB_PREFIX_;
        $query = "SELECT o.id_order FROM {$db_prefix}orders o
            WHERE o.current_state IN ({$statuses}) AND o.date_add {$date_created}
            ORDER BY o.id_order
            LIMIT {$offset}, {$limit}
        ";

        $orders = Db::getInstance()->ExecuteS($query);
        return $orders;
    }

    private function handleTrustpilotResponse($response, $post_batch)
    {
        $synced_orders = (int)$this->getTrustpilotField('past_orders');
        $failed_orders = json_decode($this->getTrustpilotField('failed_orders'));

        $data = array();
        if (isset($response['data'])) {
            $data = $response['data'];
        }

        // all succeeded
        if ($response['code'] == 201 && count($data) == 0) {
            $this->saveSyncedOrders($synced_orders, $post_batch['invitations']);
            $this->saveFailedOrders($failed_orders, $post_batch['invitations']);
        }
        // all/some failed
        if ($response['code'] == 201 && count($data) > 0) {
            $failed_order_ids = $this->selectColumn($data, 'referenceId');
            $succeeded_orders = array_filter($post_batch['invitations'], function ($invitation) use ($failed_order_ids) {
                return !(in_array($invitation['referenceId'], $failed_order_ids));
            });

            $this->saveSyncedOrders($synced_orders, $succeeded_orders);
            $this->saveFailedOrders($failed_orders, $succeeded_orders, $data);
        }
        return $response['code'];
    }

    private function selectColumn($array, $column)
    {
        if (version_compare(phpversion(), '7.2.10', '<')) {
            $newarr = array();
            foreach ($array as $row) {
                array_push($newarr, $row->{$column});
            }
            return $newarr;
        } else {
            return array_column($array, $column);
        }
    }

    private function saveSyncedOrders($synced_orders, $new_orders)
    {
        if (count($new_orders) > 0) {
            $synced_orders = (int)($synced_orders + count($new_orders));
            $this->setTrustpilotField('past_orders', $synced_orders);
        }
    }

    private function saveFailedOrders($failed_orders, $succeeded_orders, $new_failed_orders = array())
    {
        $update_needed = false;
        if (count($succeeded_orders) > 0) {
            $update_needed = true;
            foreach ($succeeded_orders as $order) {
                if (isset($failed_orders->{$order['referenceId']})) {
                    unset($failed_orders->{$order['referenceId']});
                }
            }
        }

        if (count($new_failed_orders) > 0) {
            $update_needed = true;
            foreach ($new_failed_orders as $failed_order) {
                $failed_orders->{$failed_order->referenceId} = base64_encode($failed_order->error);
            }
        }

        if ($update_needed) {
            $this->setTrustpilotField('failed_orders', json_encode($failed_orders));
        }
    }
}
