<?php

namespace AppBundle\Controller;

use AppBundle\AppBundle;
use AppBundle\Entity\Device;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use AppBundle\Form\AddDeviceType;
use AppBundle\Form\PushNotificationType;
use sngrl\PhpFirebaseCloudMessaging\Client;
use sngrl\PhpFirebaseCloudMessaging\Message;
use sngrl\PhpFirebaseCloudMessaging\Recipient\Device as FireBaseDevice;
use sngrl\PhpFirebaseCloudMessaging\Notification;
use Twig\Tests\EnvironmentTest\Extension;


class DefaultController extends Controller
{
    use Services, Helper;

    /**
     * @Route("/setlocale/{language}", name="setlocale")
     */
    public function setLocaleAction(Request $request, $language = null): RedirectResponse
    {
        if ($language != null) {
            $this->get('session')->set('_locale', $language);
            $request->setlocale($language);
        }
        $url = $this->container->get('request')->headers->get('referer');
        if (empty($url)) {
            $url = $this->container->get('router')->generate('index');
        }
        return new RedirectResponse($url);
    }

    /**
     * @Route("/proxy", name="proxy")
     */
    public function proxyAction(Request $request): Response
    {
        $url = urldecode($request->query->get('url'));
        $result = $this->get('proxy_service')->proxyGet($url);
        return new Response($result['content']);
    }

    /**
     * @Route("/", name="index")
     */
    public function indexAction(Request $request): Response
    {
        return $this->render('default/index.html.twig');
    }

    /**
     * @Route("/free_logon", name="free_logon")
     */
    public function freeLogonAction(Request $request): Response
    {
        $nhm_request = $this->getLoginService()->freeLogon($request);
        $nhm_request_status_code = $nhm_request->getStatusCode();
        $nhm_request_body = json_decode($nhm_request->getContent(), true);
        if ($nhm_request_status_code == "200" && isset($nhm_request_body['logonUrl'])) {
            return $this->render('default/ss.html.twig', array(
                'url' => $nhm_request_body['logonUrl']
            ));
        }
        else {
            throw new HttpException(400, 'Bad response, submission to NHM failed');
        }
    }

    /**
     * @Route("/twitter_logon_step1", name="twitter_logon_step1")!
     */
    public function twitterLogonStep1Action(Request $request): Response
    {
        $url = $this->getLoginService()->twitterGetLogonUrl($request);
        return $this->render('default/ss.html.twig', array(
            'url' => $url
        ));
    }

    /**
     * @Route("/twitter_logon_step2", name="twitter_logon_step2")
     */
    public function twitterLogonStep2Action(Request $request): Response
    {
        $access_token = $this->getLoginService()->twitterGetAccessToken($request);
        if ($access_token) {
            $body = $this->getLoginService()->twitterGetUserProfile($request);
            $nhm_request = $this->getLoginService()->twitterSubmitToNHM($body);
            $nhm_request_status_code = $nhm_request->getStatusCode();
            $nhm_request_body = json_decode($nhm_request->getContent(), true);
            if ($nhm_request_status_code == "200" && isset($nhm_request_body['logonUrl'])) {
                return $this->render('default/ss.html.twig', array(
                    'url' => $nhm_request_body['logonUrl']
                ));
            }
            else {
                throw new HttpException(400, 'Bad response, submission to NHM failed');
            }
        }
        else {
            throw new HttpException(403, 'Forbidden. No access token provided.');
        }
    }

    /**
     * @Route("/facebook_logon_step1", name="facebook_logon_step1")
     */
    public function facebookLogonStep1Action(Request $request): Response
    {
        $url = $this->getLoginService()->facebookGetLogonUrl($request);
        return $this->render('default/ss.html.twig', array(
            'url' => $url
        ));
    }

    /**
     * @Route("/facebook_logon_step2", name="facebook_logon_step2")
     */
    public function facebookLogonStep2Action(Request $request): Response
    {
        $access_token = $this->getLoginService()->facebookGetAccessToken($request);
        if ($access_token) {
            $body = $this->getLoginService()->facebookGetUserProfile($request);
            $nhm_request = $this->getLoginService()->facebookSubmitToNHM($body);
            $nhm_request_status_code = $nhm_request->getStatusCode();
            $nhm_request_body = json_decode($nhm_request->getContent(), true);
            if ($nhm_request_status_code == "200" && isset($nhm_request_body['logonUrl'])) {
                return $this->render('default/ss.html.twig', array(
                    'url' => $nhm_request_body['logonUrl']
                ));
            }
            else {
                throw new HttpException(400, 'Bad response, submission to NHM failed');
            }
        }
        else {
            throw new HttpException(403, 'Forbidden. No access token provided.');
        }
    }

    /**
     * @Route("/googleplus_logon_step1", name="googleplus_logon_step1")
     */
    public function googleplusLogonStep1Action(Request $request): Response
    {
        $url = $this->getLoginService()->googleplusGetLogonUrl($request);
        return $this->render('default/ss.html.twig', array(
            'url' => $url
        ));
    }

    /**
     * @Route("/googleplus_logon_step2", name="googleplus_logon_step2")
     */
    public function googleplusLogonStep2Action(Request $request): Response
    {
        if ($request->query->get('error') == 'access_denied') {
            throw new HttpException(403, 'Forbidden. Access denied.');
        }
        if ($request->query->get('code')) {
            $access_token = $this->getLoginService()->googleplusGetAccessToken($request, $request->query->get('code'));
            if ($access_token) {
                $me = $this->getLoginService()->googleplusGetUserProfile($request);
                $email = $me[0]['value'];
                $user_data = array(
                    'email' => $email,
                );
                $nhm_request = $this->getLoginService()->googleplusSubmitToNHM($user_data);
                $nhm_request_status_code = $nhm_request->getStatusCode();
                $nhm_request_body = json_decode($nhm_request->getContent(), true);
                if ($nhm_request_status_code == "200" && isset($nhm_request_body['logonUrl'])) {
                    return $this->render('default/ss.html.twig', array(
                        'url' => $nhm_request_body['logonUrl']
                    ));
                }
                else {
                    throw new HttpException(400, 'Bad response, submission to NHM failed');
                }
            }
            throw new HttpException(403, 'Forbidden. No access token provided.');
        }
        throw new HttpException(403, 'Forbidden. No code provided.');
    }

    /**
     * @Route("/linkedin_logon_step1", name="linkedin_logon_step1")
     */
    public function linkedinLogonStep1Action(Request $request): Response
    {
        $url = $this->getLoginService()->linkedinGetLogonUrl($request);
        return $this->render('default/ss.html.twig', array(
            'url' => $url
        ));
    }

    /**
     * @Route("/linkedin_logon_step2", name="linkedin_logon_step2")
     */
    public function linkedInLogonStep2(Request $request): Response
    {
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        $error = $request->query->get('error');
        switch($error) {
            case "user_cancelled_login":
                throw new HttpException(401, 'User refused to login.');
            case "user_cancelled_authorize":
                throw new HttpException(401, 'User refused permission request.');
            default:
                $access_token = $this->getLoginService()->linkedinGetAccessToken($request, $code, $state);
                if ($access_token) {
                    $me = $this->getLoginService()->linkedinGetUserProfile($request);
                    $me_status_code = $me->getStatusCode();
                    $me_body = json_decode($me->getContent(), true);
                    if ($me_status_code === '401') {
                        throw new HttpException(403, 'Invalid or expired access token.');
                    }
                    else {
                        $nhm_request = $this->getLoginService()->linkedinSubmitToNHM($me_body);
                        $nhm_request_status_code = $nhm_request->getStatusCode();
                        $nhm_request_body = json_decode($nhm_request->getContent(), true);
                        if ($nhm_request_status_code == "200" && isset($nhm_request_body['logonUrl'])) {
                            return $this->render('default/ss.html.twig', array(
                                'url' => $nhm_request_body['logonUrl']
                            ));
                        }
                        else {
                            throw new HttpException(400, 'Bad response, submission to NHM failed');
                        }
                    }
                }
                else {
                    throw new HttpException(403, 'Forbidden. No access token provided.');
                }
        }
    }

    /**
     * @Route("/instagram_logon_step1", name="instagram_logon_step1")
     */
    public function instagramLogonStep1Action(Request $request): Response
    {
        $url = $this->getLoginService()->instagramGetLogonUrl($request);
        return $this->render('default/ss.html.twig', array(
            'url' => $url
        ));
    }

    /**
     * @Route("/instagram_logon_step2", name="instagram_logon_step2")
     */
    public function instagramLogonStep2(Request $request): Response
    {
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        $error = $request->query->get('error');
        switch($error) {
            case "access_denied":
                throw new HttpException(401, 'User refused permission request.');
            default:
                $access_token = $this->getLoginService()->instagramGetAccessToken($request, $code, $state);
                if ($access_token) {
                    $me = $this->getLoginService()->instagramGetUserProfile($request);
                    $body = json_decode($me->getContent(), true);
                    $meta = $body['meta'];
                    $data = $body['data'];
                    /* unused but here as a reminder it exists */
                    //$pagination = $body['pagination'];
                    if ($meta['code'] === 200) {
                        $nhm_request = $this->getLoginService()->instagramSubmitToNHM($data);
                        $nhm_request_status_code = $nhm_request->getStatusCode();
                        $nhm_request_body = json_decode($nhm_request->getContent(), true);
                        if ($nhm_request_status_code == "200" && isset($nhm_request_body['logonUrl'])) {
                            return $this->render('default/ss.html.twig', array(
                                'url' => $nhm_request_body['logonUrl']
                            ));
                        }
                        else {
                            throw new HttpException(400, 'Bad response, submission to NHM failed');
                        }
                    }
                    else {
                        // trust Instagram API with the right status
                        $this->debug('Instagram -> Code ' . $meta['code'] . ', ' . $meta['error_type'] . ": " . $meta['error_message']);
                        throw new HttpException($meta['code'], $meta['error_type']);
                    }
                }
                else {
                    throw new HttpException(403, 'Forbidden. No access token provided.');
                }
        }
    }

    /**
     * @Route("/webnotifications", name="webnotifications")
     */
    public function webNotificationsAction(Request $request): Response
    {
        return $this->render('default/webnotifications.html.twig');
    }

    /**
     * @Route("/pushapi", name="pushapi")
     */
    public function pushApiAction(Request $request): Response
    {
        // get device tokens list
        $deviceList = $this->getDoctrine()->getRepository('AppBundle:Device')->findAll();
        // prepare form to push notification
        $pushNotificationForm = $this->createForm(PushNotificationType::class);
        $pushNotificationForm->handleRequest($request);
        if ($pushNotificationForm->isSubmitted() && $pushNotificationForm->isValid()) {
            $formData = $pushNotificationForm->getData();
            $title = $formData['title'];
            $msg = $formData['message'];
            $icon = $formData['icon'];
            $url = $formData['url'];
            $timeout = $formData['timeout'];
            $server_key = $this->getParameter('firebase_cloud_key');
            $client = new Client();
            $client->setApiKey($server_key);
            $message = new Message();
            $message->setPriority('high');
            foreach($deviceList as $key => $value) {
                $message->addRecipient(new FireBaseDevice($value->getToken()));
            }
            $message
                //->setNotification(new Notification($title, $msg))
                ->setData([
                    'title' => $title,
                    'body' => $msg,
                    'icon' => $icon,
                    'url' => $url,
                    'timeout' => $timeout
                ])
            ;
            $response = $client->send($message);
            $this->debug('PUSH API responded ' . \serialize($response));
            if ($response->getStatusCode() != "200") {
                throw new HttpException(intval($response->getStatusCode()), strval($response->getBody()->getContents()));
            }
        }
        return $this->render('default/pushapi.html.twig', [
            'deviceList' => $deviceList,
            'pushNotificationForm' => $pushNotificationForm->createView()
        ]);
    }

    /**
     * @Route("/pushapiadddevice/{token}", name="pushapiadddevice")
     */
    public function pushApiAddDeviceAction(string $token): RedirectResponse {
        $dtoken = urldecode($token);
        $em = $this->getDoctrine()->getManager();
        $device = $em->getRepository('AppBundle:Device')->findOneBy(['token' => $dtoken]);
        // device is already known
        if ($device) {
            $url = $this->generateUrl('pushapi');
            return new RedirectResponse($url);
        }
        $device = new Device();
        $device->setToken($dtoken);
        $em->persist($device);
        $em->flush();
        $url = $this->generateUrl('pushapi');
        return new RedirectResponse($url);
    }

    /**
     * @Route("/pushapiremovedevice/{token}", name="pushapiremovedevice")
     */
    public function pushApiRemoveDeviceAction(string $token): RedirectResponse {
        $dtoken = urldecode($token);
        $em = $this->getDoctrine()->getManager();
        $device = $em->getRepository('AppBundle:Device')->findOneBy(['token' => $dtoken]);
        if (!$device) {
            $url = $this->generateUrl('pushapi');
            return new RedirectResponse($url);
        }
        $em->remove($device);
        $em->flush();
        $url = $this->generateUrl('pushapi');
        return new RedirectResponse($url);
    }

    /**
     * @Route("/pushapiremovealldevices", name="pushapiremovealldevices")
     */
    public function pushApiRemoveAllDevicesAction(): RedirectResponse {
        $em = $this->getDoctrine()->getManager();
        $deviceList = $em->getRepository('AppBundle:Device')->findAll();
        foreach($deviceList as $device) {
            $em->remove($device);
        }
        $em->flush();
        $url = $this->generateUrl('pushapi');
        return new RedirectResponse($url);
    }

    /**
     * @Route("/geolocation", name="geolocation")
     */
    public function geoLocationAction(Request $request): Response
    {
        return $this->render('default/geolocation.html.twig');
    }

    /**
     * @Route("/yahooweather", name="yahooweather")
     */
    public function yahooWeatherAction(Request $request): Response
    {
        return $this->render('default/yahooweather.html.twig');
    }

    /**
     * @Route("/aerodomschedulefetch", name="aerodomschedulefetch")
     */
    public function aerodomScheduleFetchAction(Request $request): Response
    {
        return $this->render('default/aerodomschedulefetch.html.twig');
    }

    /**
     * @Route("/advideumtest", name="advideumtest")
     */
    public function advideumTestAction(Request $request): Response
    {
        return $this->render('default/advideumtest.html.twig');
    }
}
