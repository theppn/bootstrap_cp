<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;


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
     * @Route("/", name="index")
     */
    public function indexAction(Request $request): Response
    {
        return $this->render('default/index.html.twig');
    }

    /**
     * @Route("/facebook_logon_step1", name="facebook_logon_step1")
     */
    public function facebookLogonStep1Action(Request $request): RedirectResponse
    {
        $url = $this->getLoginService()->facebookGetLogonUrl($request);
        return new RedirectResponse($url);
    }

    /**
     * @Route("/facebook_logon_step2", name="facebook_logon_step2")
     */
    public function facebookLogonStep2Action(Request $request): Response
    {
        $access_token = $this->getLoginService()->facebookGetAccessToken($request);
        $body = $this->getLoginService()->facebookGetUserProfile($request);
        return new Response("Facebook access token value is " . $access_token . '<br>and response is ' . \serialize($body));
    }

    /**
     * @Route("/googleplus_logon_step1", name="googleplus_logon_step1")
     */
    public function googleplusLogonStep1Action(Request $request): RedirectResponse
    {
        $url = $this->getLoginService()->googleplusGetLogonUrl($request);
        return new RedirectResponse($url);
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
                return new Response("Google Plus access token value is " . $access_token['access_token'] . ', my id is '  . $me['id'] . 'and my name is ' . $me['displayName']);
            }
            throw new HttpException(403, 'Forbidden. No access token provided.');
        }
        throw new HttpException(403, 'Forbidden. No code provided.');
    }

}
