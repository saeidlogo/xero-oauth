<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moontius\XeroOAuth;

use Moontius\XeroOAuth\Lib\XeroOAuth;
use Moontius\XeroOAuth\XeroException;

/**
 * Description of OAuthHelper
 *
 * @author saeidlogo
 */
class OAuthHelper {

    private $key;
    private $secret;
    private $type;
    private $callback;
    public $xeroOAuth;

    public function __construct($config) {
        $useragent = "syncbanking_api";
        $this->key = $config['key'];
        $this->secret = $config['secret'];
        $this->type = $config['type'];
        $this->callback = isset($config['callback']) ? $config['callback'] : '';
        if (!($this->key) || !($this->secret) || !($this->callback)) {
            error_log('Stuff missing ');
            return false;
        }

        $signatures = array(
            'consumer_key' => $this->key,
            'shared_secret' => $this->secret,
            // API versions
            'core_version' => '2.0',
            'payroll_version' => '1.0',
            'file_version' => '1.0'
        );

        $this->xeroOAuth = new XeroOAuth(array_merge(array(
                    'application_type' => $this->type,
                    'oauth_callback' => $this->callback,
                    'user_agent' => $useragent
                        ), $signatures));

        $initialCheck = $this->xeroOAuth->diagnostics();
        $checkErrors = count($initialCheck);
        if ($checkErrors > 0) {
            // you could handle any config errors here, or keep on truckin if you like to live dangerously
            foreach ($initialCheck as $check) {
                echo 'Error: ' . $check . PHP_EOL;
            }
        }
    }

    public function authenticate(): string {
        $params = array(
            'oauth_callback' => $this->callback
        );
        $this->xeroOAuth->request('GET', $this->xeroOAuth->url('RequestToken', ''), $params);
        if ($this->xeroOAuth->response ['code'] == 200) {
            return $this->authorize();
        } else {
            return $this->xeroOAuth->response['response'];
        }
    }

    public function authorize(): string {
        $scope = '';
        $response = $this->xeroOAuth->extract_params($this->xeroOAuth->response ['response']);
        $this->xero_session_put('oauth', $response);
        $authurl = $this->xeroOAuth->url("Authorize", '') . "?oauth_token={$response['oauth_token']}&scope=" . $scope;
        return $authurl;
    }

    function callback($params): string {
        $oauth = $this->xero_session_get('oauth');
        $this->xeroOAuth->config ['access_token'] = $oauth['oauth_token'];
        $this->xeroOAuth->config ['access_token_secret'] = $oauth['oauth_token_secret'];

        $this->xeroOAuth->request('GET', $this->xeroOAuth->url('AccessToken', ''), array(
            'oauth_verifier' => $params['oauth_verifier'],
            'oauth_token' => $params['oauth_token']
        ));

        if ($this->xeroOAuth->response ['code'] == 200) {
            $response = $this->xeroOAuth->extract_params($this->xeroOAuth->response ['response']);
            if (isset($response)) {
                $this->xero_session_put('oauth_token', $response['oauth_token']);
                $this->xero_session_put('oauth_token_secret', $response['oauth_token_secret']);
                if (isset($response['oauth_session_handle']))
                    $this->xero_session_put('session_handle', $response['oauth_session_handle']);
            } else {
                return false;
            }
            $this->xero_session_destroy('oauth');
            return 'success';
        }

        return $this->xeroOAuth->response['response'];
    }

    function refresh() {
        $response = $this->xeroOAuth->refreshToken($this->xero_session_destroy('oauth_token'), $this->xero_session_destroy('oauth_session_handle'));
        if ($this->xeroOAuth->response['code'] == 200) {
            $session = persistSession($response);
            $oauthSession = retrieveSession();
        } else {
            if ($this->xeroOAuth->response['helper'] == "TokenExpired")
                $this->xeroOAuth->refreshToken($oauthSession['oauth_token'], $session->get('session_handle'));
        }
    }

    function logout() {
        $this->xero_session_destroy('oauth_token');
        $this->xero_session_destroy('xero');
    }

    public function all_organisations() {
        if ($this->xero_auth()) {
            $xml = '';
            $this->xeroOAuth->request('GET', $this->xeroOAuth->url('Organisations', 'core'), array(), $xml, 'json');
            if ($this->xeroOAuth->response['code'] == 200) {
                $organisation = $this->xeroOAuth->parseResponse($this->xeroOAuth->response['response'], $this->xeroOAuth->response['format']);
                $json = json_decode(json_encode($organisation), true);
                return $json;
            }
            $json = $this->xeroOAuth->response['response'];
            if (!is_null($json)) {
                //if $json is not array maybe there is a error
                if (!is_array($json)) {
                    //convert query string to json format
                    $error = $oauth->query_string_to_json($json);
                    if (isset($error['oauth_problem'])) {
                        switch ($error['oauth_problem']) {
                            case 'token_expired'://if xero token is expired we need to get fresh token and client should be redirect to auth page
                                throw new XeroException('expired token xero account', 501);
                            default;
                                throw new XeroException($error['oauth_problem'], 501);
                        }
                    }
                    throw new XeroException($error, 501);
                }
                if (isset($json['Organisations'])) {
                    return $json['Organisations'];
                }
                throw new XeroException('invalid json file', 501);
            }
            throw new XeroException('invalid response from xero account organisation list', 501);
        }
        throw new XeroException('xero authentication error', 501);
    }

    public function xero_auth(): bool {
        $token = $this->xero_session_get('oauth_token');
        if (null !== $token) {
            $this->xeroOAuth->config['access_token'] = $token;
            $this->xeroOAuth->config['access_token_secret'] = $this->xero_session_get('oauth_token_secret');
            $this->xeroOAuth->config['session_handle'] = $this->xero_session_get('oauth_session_handle');
            return true;
        }
        return false;
    }

    public function bank_accounts() {
        if ($this->xero_auth()) {
            $this->xeroOAuth->request('GET', $this->xeroOAuth->url('Accounts', 'core'), array('Where' => 'Type=="BANK"'), '', 'json');
            if ($this->xeroOAuth->response['code'] == 200) {
                $accounts = $this->xeroOAuth->parseResponse($this->xeroOAuth->response['response'], $this->xeroOAuth->response['format']);
                return $accounts->Accounts;
            }
            return $this->xeroOAuth->response['response'];
        }
        return null;
    }

    public function xero_session_get($key) {
        if (isset($_SESSION['XERO'][$key])) {
            return $_SESSION['XERO'][$key];
        }
        return null;
    }

    public function xero_session_put($key, $value) {
        $_SESSION['XERO'][$key] = $value;
    }

    public function xero_session_destroy($key) {
        if (isset($_SESSION['XERO'][$key])) {
            unset($_SESSION['XERO'][$key]);
        }
    }

    public function query_string_to_json($string) {
        $keys = preg_split("/[\s,=,&]+/", $string);
        $arr = array();
        for ($i = 0; $i < sizeof($keys); $i++) {
            $arr[$keys[$i]] = $keys[++$i];
        }
        $result = (object) $arr;
        return json_decode(json_encode($result), true);
    }

}
