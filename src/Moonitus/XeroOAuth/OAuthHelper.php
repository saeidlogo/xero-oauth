<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moontius\XeroOAuth;

use Moontius\XeroOAuth\Lib\XeroOAuth;
use Illuminate\Http\Request;
use Exception;

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

    public function authenticate(Request $request): string {
        $session = $request->session();
        $params = array(
            'oauth_callback' => $this->callback
        );
        $this->xeroOAuth->request('GET', $this->xeroOAuth->url('RequestToken', ''), $params);
        if ($this->xeroOAuth->response ['code'] == 200) {
            return $this->authorize($session);
        } else {
            return $this->xeroOAuth->response['response'];
        }
    }

    public function authorize($session): string {
        //            print_r($this->xeroOAuth->extract_params($this->xeroOAuth->response ['response']));
        $scope = '';
        $response = $this->xeroOAuth->extract_params($this->xeroOAuth->response ['response']);
        $session->put('oauth', $response);
        $authurl = $this->xeroOAuth->url("Authorize", '') . "?oauth_token={$response['oauth_token']}&scope=" . $scope;
        return $authurl;
    }

    function callback(Request $request): string {
        $session = $request->session();
        $oauth = $session->get('oauth');
        $this->xeroOAuth->config ['access_token'] = $oauth['oauth_token'];
        $this->xeroOAuth->config ['access_token_secret'] = $oauth['oauth_token_secret'];

        $this->xeroOAuth->request('GET', $this->xeroOAuth->url('AccessToken', ''), array(
            'oauth_verifier' => $request->input('oauth_verifier'),
            'oauth_token' => $request->input('oauth_token')
        ));

        if ($this->xeroOAuth->response ['code'] == 200) {
            $response = $this->xeroOAuth->extract_params($this->xeroOAuth->response ['response']);
            if (isset($response)) {
                $session->put('oauth_token', $response['oauth_token']);
                $session->put('oauth_token_secret', $response['oauth_token_secret']);
                if (isset($response['oauth_session_handle']))
                    $session->put('session_handle', $response['oauth_session_handle']);
            } else {
                return false;
            }
            $session->remove('oauth');
//            unset($_SESSION ['oauth']);
            return 'success';
        }

        return $this->xeroOAuth->response['response'];
    }

    function refresh(Request $request) {
        $session = $request->session();
        $response = $this->xeroOAuth->refreshToken($session->get('oauth_token'), $session->get('oauth_session_handle'));
        if ($this->xeroOAuth->response['code'] == 200) {
            $session = persistSession($response);
            $oauthSession = retrieveSession();
        } else {
            if ($this->xeroOAuth->response['helper'] == "TokenExpired")
                $this->xeroOAuth->refreshToken($oauthSession['oauth_token'], $session->get('session_handle'));
        }
    }

    function logout(Request $request) {
        $session = $request->session();
        $session->remove('oauth_token');
        $session->remove('xero');
    }

    public function all_organisations(Request $request) {
        if ($this->xeroOAuth($request)) {
            $xml = '';
            $this->xeroOAuth->request('GET', $this->xeroOAuth->url('Organisation', 'core'), array(), $xml, 'json');
            if ($this->xeroOAuth->response['code'] == 200) {
                $organisation = $this->xeroOAuth->parseResponse($this->xeroOAuth->response['response'], $this->xeroOAuth->response['format']);
                $json = json_decode(json_encode($organisation), true);
                return $json;
            }
            return $this->xeroOAuth->response['response'];
        }
        return null;
    }

    public function xeroOAuth(Request $request): bool {
        $session = $request->session();
        if (null !== $session->get('oauth_token')) {
            $this->xeroOAuth->config['access_token'] = $session->get('oauth_token');
            $this->xeroOAuth->config['access_token_secret'] = $session->get('oauth_token_secret');
            $this->xeroOAuth->config['session_handle'] = $session->get('oauth_session_handle');
            return true;
        }
        return false;
    }

    public function bank_accounts(Request $request) {
        $this->xeroOAuth->request('GET', $this->xeroOAuth->url('Accounts', 'core'), array('Where' => 'Type=="BANK"'), '', 'json');
        if ($this->xeroOAuth->response['code'] == 200) {
            $accounts = $this->xeroOAuth->parseResponse($this->xeroOAuth->response['response'], $this->xeroOAuth->response['format']);
            return $accounts->Accounts;
        }
        return $this->xeroOAuth->response['response'];
    }

}

class XeroException extends \Exception {
    
}
