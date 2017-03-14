<?php
/**
 * Created by PhpStorm.
 * User: anh
 * Date: 13/03/17
 * Time: 10:25
 */

namespace AppBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Facebook\Facebook;
use Facebook\FacebookResponse;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;

class LoginService {
    private $nhm_api_url;
    private $facebook_app_id;
    private $facebook_client_secret;
    private $google_client_id;
    private $google_client_secret;
    private $session;
    private $rest;
    private $logger;

    function __construct($nhm_api_url, $facebook_app_id, $facebook_client_secret, $google_client_id, $google_client_secret, $session, $rest, $logger) {
        $this->nhm_api_url = $nhm_api_url;
        $this->facebook_app_id = $facebook_app_id;
        $this->facebook_client_secret = $facebook_client_secret;
        $this->google_client_id = $google_client_id;
        $this->google_client_secret = $google_client_secret;
        $this->session = $session;
        $this->rest = $rest;
        $this->logger = $logger;
    }

    private function debug($msg) {
        return $this->logger->debug(debug_backtrace(01, 3)[1]['function'] . " ". $msg);
    }

    public function generateRandomString($length = 16) {
        $this->debug('start ' . serialize($request));
        $char_space = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $str = '';
        $max = mb_strlen($char_space, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $char_space[random_int(0, $max)];
        }
        return $str;
    }

    /* generic method for http request */
    private function api_call ($method, $url, $data = null) {
        $options = array(CURLOPT_HTTPHEADER => array('authorization:'. $this->session->get('token'), 'Content-Type: application/json'));
        $this->debug('method is ' . $method);
        $this->debug('url is ' . $this->nhm_api_url . $url);
        $this->debug('data is ' . \serialize($data));
        $this->debug('options is ' . \serialize($options));
        switch($method) {
            case 'POST':
                return $this->restClient->post($this->nhm_api_url . $url, ($data?json_encode($data):" "), $options);
            case 'GET':
            default:
                return $this->restClient->get($this->nhm_api_url . $url, $options);
        }
    }

    public function start($request) {
        $this->debug('start ' . serialize($request));
        return null;
    }

    public function facebookGetLogonUrl($request) {
        $this->debug('start ' . serialize($request));
        $protocol = (substr($_SERVER['SERVER_PROTOCOL'], 0, 5) === "HTTPS"?"https://":"http://");
        $redirect_url = $protocol . $request->getHost() . ":" . $request->getPort() . '/facebook_logon_step2';
        $fb = new Facebook([
            'app_id' => $this->facebook_app_id,
            'app_secret' => $this->facebook_client_secret,
            'default_graph_version' => 'v2.5',
        ]);
        $helper = $fb->getRedirectLoginHelper();
        $permissions = ['email', 'user_likes'];
        $loginUrl = $helper->getLoginUrl($redirect_url, $permissions);
        return $loginUrl;
    }

    public function facebookGetAccessToken($request) {
        $this->debug('start ' . serialize($request));
        $fb = new Facebook([
            'app_id' => $this->facebook_app_id,
            'app_secret' => $this->facebook_client_secret,
            'default_graph_version' => 'v2.5',
        ]);
        $helper = $fb->getRedirectLoginHelper();
        try {
            $accessToken = $helper->getAccessToken();
        } catch(FacebookResponseException $e) {
            // When Graph returns an error
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch(FacebookSDKException $e) {
            // When validation fails or other local issues
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }

        if (isset($accessToken)) {
            $this->session->set('facebook_access_token', (string) $accessToken);
            return (string) $accessToken;
        }
        else {
            throw new HttpException(400, 'Bad Request');
        }
    }

    public function facebookGetUserProfile($request) {
        $this->debug('start ' . serialize($request));
        $fb = new Facebook([
            'app_id' => $this->facebook_app_id,
            'app_secret' => $this->facebook_client_secret,
            'default_graph_version' => 'v2.5',
        ]);
        try {
            // Returns a `Facebook\FacebookResponse` object
            $result = $fb->get('/me?fields=id,name', $this->session->get('facebook_access_token'));
            return $result->getDecodedBody();
        } catch(FacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch(FacebookSDKException $e) {
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }
    }

    public function googleplusGetLogonUrl($request) {
        $this->debug('start ' . serialize($request));
        $client = new \Google_Client();
        $client->setClientId($this->google_client_id);
        $client->setClientSecret($this->google_client_secret);
        $client->addScope('https://www.googleapis.com/auth/plus.login');
        $protocol = (substr($_SERVER['SERVER_PROTOCOL'], 0, 5) === "HTTPS"?"https://":"http://");
        $redirect_url = $protocol . $request->getHost() . ":" . $request->getPort() . '/googleplus_logon_step2';
        $client->setRedirectUri($redirect_url);
        return $client->createAuthUrl();
    }

    public function googleplusGetAccessToken($request, $code) {
        $this->debug('start ' . serialize($request));
        $client = new \Google_Client();
        $client->addScope('https://www.googleapis.com/auth/plus.login');
        $client->setClientId($this->google_client_id);
        $client->setClientSecret($this->google_client_secret);
        $protocol = (substr($_SERVER['SERVER_PROTOCOL'], 0, 5) === "HTTPS"?"https://":"http://");
        $redirect_url = $protocol . $request->getHost() . ":" . $request->getPort() . '/googleplus_logon_step2';
        $client->setRedirectUri($redirect_url);
        $client->authenticate($code);
        $access_token = $client->getAccessToken();
        $access_token_as_string = json_encode($access_token);
        $this->session->set('googleplus_access_token', $access_token_as_string);
        return $access_token;
    }

    public function googleplusGetUserProfile($request) {
        $this->debug('start ' . serialize($request));
        $access_token = json_decode($this->session->get('googleplus_access_token'));
        $client = new \Google_Client();
        $client->addScope('https://www.googleapis.com/auth/plus.me');
        $client->setAccessToken((array)$access_token); // (object) -> (array) casting
        $plus = new \Google_Service_Plus($client);
        $result = $plus->people->get('me');
        return $result;
    }
}

?>