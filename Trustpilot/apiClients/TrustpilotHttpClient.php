<?php

/**
 * Trustpilot Module
 *
 *  @author    Trustpilot
 *  @copyright Trustpilot
 *  @license   https://opensource.org/licenses/OSL-3.0
 */

class TrustpilotHttpClient
{
    const HTTP_REQUEST_TIMEOUT = 3;

    public function __construct($apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    public function post($url, $data)
    {
        $httpRequest = "POST";
        return $this->request(
            $url,
            $httpRequest,
            $data
        );
    }

    public function buildUrl($key, $endpoint)
    {
        return $this->apiUrl . $key . $endpoint;
    }

    public function postInvitation($key, $data = array())
    {
        return $this->post($this->buildUrl($key, '/invitation'), $data);
    }

    public function postBatchInvitations($key, $data = array())
    {
        return $this->post($this->buildUrl($key, '/batchinvitations'), $data);
    }

    public function postSettings($key, $data = array())
    {
        return $this->post($this->buildUrl($key, '/settings'), $data);
    }

    public function postLog($data)
    {
        try {
            return $this->post($this->apiUrl . 'log', $data);
        } catch (Exception $e) {
            return false;
        }
    }

    public function request($url, $httpRequest, $data = null, $params = array(), $timeout = self::HTTP_REQUEST_TIMEOUT)
    {
        $ch = curl_init();
        $this->setCurlOptions($ch, $httpRequest, $data, $timeout);
        $url = $this->buildParams($url, $params);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($ch);
        $responseData = $this->jsonDecoder($content);
        $responseInfo = curl_getinfo($ch);
        $responseCode = $responseInfo['http_code'];
        curl_close($ch);
        $response = array();
        $response['code'] = $responseCode;
        if (is_object($responseData) || is_array($responseData)) {
                $response['data'] = $responseData;
        }
        return $response;
    }

    private function jsonEncoder($data)
    {
        if (function_exists('json_encode')) {
            return json_encode($data);
        } elseif (method_exists('Tools', 'jsonEncode')) {
            return Tools::jsonEncode($data);
        }
    }

    private function jsonDecoder($data)
    {
        if (function_exists('json_decode')) {
            return json_decode($data);
        } elseif (method_exists('Tools', 'jsonDecode')) {
            return Tools::jsonDecode($data);
        }
    }

    private function setCurlOptions($ch, $httpRequest, $data, $timeout)
    {
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($httpRequest == 'POST') {
            $encoded_data = $this->jsonEncoder($data);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'content-type: application/json',
                'Content-Length: ' . Tools::strlen($encoded_data),
                'Origin: ' . _PS_BASE_URL_,
            ));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded_data);
        } elseif ($httpRequest == 'GET') {
            curl_setopt($ch, CURLOPT_POST, false);
        }
    }

    private function buildParams($url, $params = array())
    {
        if (!empty($params) && is_array($params)) {
            $url .= '?'.http_build_query($params);
        }
        return $url;
    }
}