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
use Abraham\TwitterOAuth\TwitterOAuth;
use Facebook\Facebook;
use Facebook\FacebookResponse;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;

class LoginService {
    private $nhm_api_url;
    private $sendin_blue_api_key;
    private $sendin_blue_api_timeout;
    private $sendin_blue_api_from_mail;
    private $sendin_blue_api_from_name;
    private $sendin_blue_api_tag;
    private $send_email_on_account_creation;
    private $twitter_consumer_key;
    private $twitter_consumer_secret;
    private $facebook_app_id;
    private $facebook_client_secret;
    private $google_client_id;
    private $google_client_secret;
    private $linkedin_client_id;
    private $linkedin_client_secret;
    private $instagram_client_id;
    private $instagram_client_secret;
    private $session;
    private $rest;
    private $logger;

    function __construct($nhm_api_url, $sendin_blue_api_key, $sendin_blue_api_timeout, $sendin_blue_api_from_mail, $sendin_blue_api_from_name, $sendin_blue_api_tag, $send_email_on_account_creation, $twitter_consumer_key, $twitter_consumer_secret, $facebook_app_id, $facebook_client_secret, $google_client_id, $google_client_secret, $linkedin_client_id, $linkedin_client_secret, $instagram_client_id, $instagram_client_secret, $session, $rest, $logger) {
        $this->nhm_api_url = $nhm_api_url;
        $this->sendin_blue_api_key = $sendin_blue_api_key;
        $this->sendin_blue_api_timeout = $sendin_blue_api_timeout;
        $this->sendin_blue_api_from_mail = $sendin_blue_api_from_mail;
        $this->sendin_blue_api_from_name = $sendin_blue_api_from_name;
        $this->sendin_blue_api_tag = $sendin_blue_api_tag;
        $this->send_email_on_account_creation = $send_email_on_account_creation;
        $this->twitter_consumer_key = $twitter_consumer_key;
        $this->twitter_consumer_secret = $twitter_consumer_secret;
        $this->facebook_app_id = $facebook_app_id;
        $this->facebook_client_secret = $facebook_client_secret;
        $this->google_client_id = $google_client_id;
        $this->google_client_secret = $google_client_secret;
        $this->linkedin_client_id = $linkedin_client_id;
        $this->linkedin_client_secret = $linkedin_client_secret;
        $this->instagram_client_id = $instagram_client_id;
        $this->instagram_client_secret = $instagram_client_secret;
        $this->session = $session;
        $this->rest = $rest;
        $this->logger = $logger;
    }

    private function debug($msg) {
        return $this->logger->debug(debug_backtrace(01, 3)[1]['function'] . " ". $msg);
    }

    public function generateRandomString($length = 16) {
        $this->debug('start w/ length ' . $length);
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
                return $this->rest->post($this->nhm_api_url . $url, ($data?json_encode($data):" "), $options);
            case 'GET':
            default:
                return $this->rest->get($this->nhm_api_url . $url, $options);
        }
    }

    public function freeLogon($request) {
        $this->debug('start ' . serialize($request));
        $url = 'free-logon';
        $result = $this->api_call('POST', $url);
        return $result;
    }

    public function twitterGetLogonUrl($request) {
        $this->debug('start ' . serialize($request));
        try {
            $protocol = $request->getScheme() . '://';
            $redirect_url = $protocol . $request->getHost() . ":" . $request->getPort() . '/twitter_logon_step2';
            $connection = new TwitterOAuth($this->twitter_consumer_key, $this->twitter_consumer_secret);
            $request_token = $connection->oauth('oauth/request_token', array('oauth_callback' => $redirect_url));
            $url = $connection->url('oauth/authorize', array('oauth_token' => $request_token['oauth_token']));
            return $url;
        }
        catch(\Exception $e) {
            $this->debug("ERROR: cannot initiliaze TwitterOAuth, stuck at step 'request token'. Details: " . $e->getMessage());
            throw new HttpException(503, 'Service unavailable. Twitter may have an issue.');
        }
    }

    public function twitterGetAccessToken($request) {
        $this->debug('start ' . serialize($request));
        try {
            $request_token = [];
            $request_token['oauth_token'] = $_GET['oauth_token'];
            $request_token['oauth_verifier'] = $_GET['oauth_verifier'];
            // using request token to get access token
            $connection = new TwitterOAuth($this->twitter_consumer_key, $this->twitter_consumer_secret, $request_token['oauth_token'], $request_token['oauth_verifier']);
            $access_token = $connection->oauth("oauth/access_token", ["oauth_verifier" => $_GET['oauth_verifier']]);
            $this->session->set('twitter_access_token', json_encode($access_token));
            return $access_token;
        }
        catch(\Exception $e) {
            $this->debug("ERROR: cannot initiliaze TwitterOAuth, stuck at step 'access token'. Details: " . $e->getMessage());
            throw new HttpException(503, 'Service unavailable. Twitter may have an issue.');
        }
    }

    public function twitterGetUserProfile($request) {
        $this->debug('start ' . serialize($request));
        try {
            $access_token = json_decode($this->session->get('twitter_access_token'), true);
            $connection = new TwitterOAuth($this->twitter_consumer_key, $this->twitter_consumer_secret, $access_token['oauth_token'], $access_token['oauth_token_secret']);
            // lazy casting stdClass into JSON string then array
            $user_data = json_decode(json_encode($connection->get("account/verify_credentials", ['include_entities' => 'false',  'skip_status' => 'true',  'include_email' => 'true'])), true);
            return $user_data;
        }
        catch(\Exception $e) {
            $this->debug("ERROR: cannot initiliaze TwitterOAuth, stuck at step 'verify credentials'. Details: " . $e->getMessage());
            throw new HttpException(503, 'Service unavailable. Twitter may have an issue.');
        }
    }

    public function twitterSubmitToNHM($user_data) {
        $nhm_token = $this->session->get('token');
        if (empty($nhm_token)) {
            throw new HttpException(401, 'No token was provided by NHM.');
        }
        $wanted_fields = array(
            'twitterCreatedAt' => 'created_at',
            'lang' => 'lang',
            'location' => 'location',
            'name' => 'name',
            'screenName' => 'screen_name',
            'email' => 'email'
        );
        $data = [];
        foreach ($wanted_fields as $key => $value) {
            $data[$key] = '';
            if (isset($user_data[$value])) {
                $data[$key] = $user_data[$value];
            }
        }
        $result = $this->api_call('POST', 'twitter-logon', $data, array(
            'authorization: ' . $this->session->get('token')
        ));
        $this->debug(\serialize($result));
        return $result;
    }


    public function facebookGetLogonUrl($request) {
        $this->debug('start ' . serialize($request));
        $protocol = $request->getScheme() . '://';
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
            $result = $fb->get('/me?fields=last_name,first_name,age_range,birthday,email,gender,locale', $this->session->get('facebook_access_token'));
            return $result->getDecodedBody();
        } catch(FacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch(FacebookSDKException $e) {
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }
    }

    public function facebookSubmitToNHM($user_data) {
        $nhm_token = $this->session->get('token');
        if (empty($nhm_token)) {
            throw new HttpException(401, 'No token was provided by NHM.');
        }
        $wanted_fields = array(
            'lastName' => 'last_name',
            'firstName' => 'first_name',
            'ageRange' => 'age_range',
            'birthday' => 'birthday',
            'email' => 'email',
            'gender' => 'gender',
            'locale' => 'locale'
        );
        $data = [];
        foreach ($wanted_fields as $key => $value) {
            $data[$key] = '';
            if (isset($user_data[$value])) {
                if($value === 'age_range') {
                    $minBoundary = '0';
                    $maxBoundary = '?';
                    if (isset($user_data[$value]['min'])) {
                        $minBoundary = $user_data[$value]['min'];
                    }
                    if (isset($user_data[$value]['max'])) {
                        $maxBoundary = $user_data[$value]['max'];
                    }
                    $data[$key] = $minBoundary . '-' . $maxBoundary;
                }
                else {
                    $data[$key] = $user_data[$value];
                }
            }
        }
        $result = $this->api_call('POST', 'facebook-logon', $data, array(
            'authorization: ' . $this->session->get('token')
        ));
        $this->debug(\serialize($result));
        return $result;
    }

    public function googleplusGetLogonUrl($request) {
        $this->debug('start ' . serialize($request));
        $client = new \Google_Client();
        $client->setClientId($this->google_client_id);
        $client->setClientSecret($this->google_client_secret);
        $client->addScope('https://www.googleapis.com/auth/plus.profile.emails.read');
        $protocol = $request->getScheme() . '://';
        $redirect_url = $protocol . $request->getHost() . ":" . $request->getPort() . '/googleplus_logon_step2';
        $client->setRedirectUri($redirect_url);
        return $client->createAuthUrl();
    }

    public function googleplusGetAccessToken($request, $code) {
        $this->debug('start ' . serialize($request));
        if (empty($code)) {
            throw new HttpException(401, 'No code provided.');
        }
        $client = new \Google_Client();
        $client->addScope('https://www.googleapis.com/auth/plus.profile.emails.read');
        $client->setClientId($this->google_client_id);
        $client->setClientSecret($this->google_client_secret);
        $protocol = $request->getScheme() . '://';
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
        $access_token = json_decode($this->session->get('googleplus_access_token'), true);
        $client = new \Google_Client();
        $client->addScope('https://www.googleapis.com/auth/plus.profile.emails.read');
        $client->setAccessToken($access_token);
        $plus = new \Google_Service_Plus($client);
        $result = $plus->people->get('me')->getEmails();
        return $result;
    }

    public function googleplusSubmitToNHM($user_data) {
        $nhm_token = $this->session->get('token');
        if (empty($nhm_token)) {
            throw new HttpException(401, 'No token was provided by NHM.');
        }
        $result = $this->api_call('POST', 'googleplus-logon', array(
            'email' => $user_data['email'] //only email for now
        ), array(
            'authorization: ' . $this->session->get('token')
        ));
        $this->debug(\serialize($result));
        return $result;
    }

    public function linkedinGetLogonUrl($request) {
        $this->debug('start ' . serialize($request));
        $state = $this->generateRandomString();
        $this->session->set('linkedin_random_state', $state);
        $protocol = $request->getScheme() . '://';
        $redirect_url = urlencode($protocol . $request->getHost() . ":" . $request->getPort() . '/linkedin_logon_step2');
        $result = "https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id=" . $this->linkedin_client_id . "&redirect_uri=" . $redirect_url . "&state=" . $state . "&scope=r_basicprofile%20r_emailaddress";
        return $result;
    }

    public function linkedinGetAccessToken($request, $code, $state) {
        $this->debug('start ' . serialize($request));
        $current_state = $this->session->get('linkedin_random_state');
        if (empty($code)) {
            throw new HttpException(401, 'No code provided.');
        }
        if (empty($current_state)) {
            throw new HttpException(401, 'No state previously generated. Forgery detected.');
        }
        if ($state != $current_state) {
            throw new HttpException(401, 'State is different. Forgery detected.');
        }
        $protocol = $request->getScheme() . '://';
        $url = 'https://www.linkedin.com/oauth/v2/accessToken';
        $redirect_url = $protocol . $request->getHost() . ":" . $request->getPort() . '/linkedin_logon_step2';
        /* /!\ Contrary to what the LinkedIn doc reads, POST parameters are not supported, provide them as url query parameters /!\ */
        /*$data = 'grant_type=authorization_code&code=' . $code . '&redirect_uri=' . $redirect_url . '&client_id=' . $this->linkedin_client_id . '&client_secret=' . $this->linkedin_client_secret;*/
        $data = '?grant_type=authorization_code&code=' . $code . '&redirect_uri=' . $redirect_url . '&client_id=' . $this->linkedin_client_id . '&client_secret=' . $this->linkedin_client_secret;
        $options = array(CURLOPT_HTTPHEADER => array('Content-Type: x-www-form-urlencoded'));
        $access_token_request = $this->rest->post($url . $data, "", $options);
        $access_token_request_status_code = $access_token_request->getStatusCode();
        $access_token_request_body = json_decode($access_token_request->getContent(), true);
        switch ($access_token_request_status_code) {
            case '200':
                $access_token = $access_token_request_body['access_token'];
                $this->session->set('linkedin_access_token', $access_token);
                return $access_token;
            case '400':
                throw new HttpException(400, 'Bad request to LinkedIn API.');
            default:
                throw new HttpException(500, 'Unknown response status code from LinkedIn API.');
        }
    }

    public function linkedinGetUserProfile($request) {
        $this->debug('start ' . serialize($request));
        $access_token = $this->session->get('linkedin_access_token');
        /* /!\ Contrary to what the LinkedIn doc reads, header parameters are not supported, provide them as url query parameters /!\ */
        $url = 'https://api.linkedin.com/v1/people/~:(id,first-name,last-name,public-profile-url,email-address)?format=json&oauth2_access_token=' . $access_token;
        //$options = array(CURLOPT_HTTPHEADER => array('Authorization: Bearer '. $access_token, 'x-li-format: json'));
        //$result = $this->rest->get($url, $options);
        $result = $this->rest->get($url);
        return $result;
    }

    public function linkedinSubmitToNHM($user_data) {
        $this->debug('start ' . serialize($user_data));
        $nhm_token = $this->session->get('token');
        if (empty($nhm_token)) {
            throw new HttpException(401, 'No token was provided by NHM.');
        }
        $email = "";
        if (isset($user_data['emailAddress'])) {
            $email = $user_data['emailAddress'];
        }
        $firstName = "";
        if (isset($user_data['firstName'])) {
            $firstName = $user_data['firstName'];
        }
        $lastName = "";
        if (isset($user_data['lastName'])) {
            $lastName = $user_data['lastName'];
        }
        $username = "";
        if (isset($user_data['publicProfileUrl'])) {
            $target = explode('/', $user_data['publicProfileUrl']);
            $username = $target[count($target) - 1];
            if (!$username) { // just in case, url ends by "/"
                $username = "";
            }
        }
        $result = $this->api_call('POST', 'linkedin-logon', array(
            'email' => $email,
            'username' => $username,
            'firstName' => $firstName,
            'lastName' => $lastName
        ), array(
            'authorization: ' . $this->session->get('token')
        ));
        $this->debug(\serialize($result));
        return $result;
    }

    public function instagramGetLogonUrl($request) {
        $this->debug('start ' . serialize($request));
        $state = $this->generateRandomString();
        $this->session->set('instagram_random_state', $state);
        $protocol = $request->getScheme() . '://';
        $redirect_url = urlencode($protocol . $request->getHost() . ":" . $request->getPort() . '/instagram_logon_step2');
        $result = "https://api.instagram.com/oauth/authorize/?client_id=" . $this->instagram_client_id . "&redirect_uri=" . $redirect_url . "&response_type=code&scope=public_content&state=" . $state;
        return $result;
    }

    public function instagramGetAccessToken($request, $code, $state) {
        $this->debug('start ' . serialize($request));
        $current_state = $this->session->get('instagram_random_state');
        if (empty($code)) {
            throw new HttpException(401, 'No code provided.');
        }
        if (empty($current_state)) {
            throw new HttpException(401, 'No state previously generated. Forgery detected.');
        }
        if ($state != $current_state) {
            throw new HttpException(401, 'State is different. Forgery detected.');
        }
        $protocol = $request->getScheme() . '://';
        $url = 'https://api.instagram.com/oauth/access_token';
        $redirect_url = $protocol . $request->getHost() . ":" . $request->getPort() . '/instagram_logon_step2';
        /*$data = '?client_id=' . $this->instagram_client_id . '&client_secret=' . $this->instagram_client_secret . '&grant_type=authorization_code&redirect_uri=' . $redirect_url . '&code=' . $code;
        $access_token_request = $this->rest->post($url . $data, $data);*/
        $data = array(
            'client_id' => $this->instagram_client_id,
            'client_secret' => $this->instagram_client_secret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirect_url,
            'code' => $code,
        );
        $options = array(CURLOPT_HTTPHEADER => array('Content-type: application/x-www-form-urlencoded'));
        $access_token_request = $this->rest->post($url, http_build_query($data), $options);
        $access_token_request_status_code = $access_token_request->getStatusCode();
        $access_token_request_body = json_decode($access_token_request->getContent(), true);
        switch ($access_token_request_status_code) {
            case '200':
                $access_token = $access_token_request_body['access_token'];
                $this->session->set('instagram_access_token', $access_token);
                return $access_token;
            default:
                $this->debug('Instagram -> Code ' . $access_token_request_status_code . ', ' . $access_token_request_body['error_type'] . ": " . $access_token_request_body['error_message']);
                throw new HttpException(intval($access_token_request_status_code), 'Error of type ' . $access_token_request_body['error_type'] . '. returned by Instagram API.');
        }
    }

    public function instagramGetUserProfile($request) {
        $this->debug('start ' . serialize($request));
        $access_token = $this->session->get('instagram_access_token');
        $url = 'https://api.instagram.com/v1/users/self/?access_token=' . $access_token;
        $result = $this->rest->get($url);
        return $result;
    }

    public function instagramSubmitToNHM($user_data) {
        $this->debug('start ' . serialize($user_data));
        $nhm_token = $this->session->get('token');
        if (empty($nhm_token)) {
            throw new HttpException(401, 'No token was provided by NHM.');
        }
        $fullName = "";
        if (isset($user_data['full_name'])) {
            $fullName = $user_data['full_name'];
        }
        $result = $this->api_call('POST', 'instagram-logon', array(
            'username' => $user_data['username'],
            'fullName' => $fullName
        ), array(
            'authorization: ' . $this->session->get('token')
        ));
        $this->debug(\serialize($result));
        return $result;
    }
}

?>