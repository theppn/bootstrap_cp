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
    public function indexAction(Request $request)
    {
        return $this->render('default/index.html.twig');
    }

    /**
     * @Route("/facebook_logon_step1", name="facebook_logon_step1")
     */
    public function facebookLogonStep1Action(Request $request)
    {
        $url = $this->getLoginService()->facebookGetLogonUrl($request);
        return new RedirectResponse($url);
    }

    /**
     * @Route("/facebook_logon_step2", name="facebook_logon_step2")
     */
    public function facebookLogonStep2Action(Request $request)
    {
        $access_token = $this->getLoginService()->facebookGetAccessToken($request);
        $body = $this->getLoginService()->facebookGetUserProfile($request);
        return new Response("Facebook access token value is " . $access_token . '<br>and response is ' . \serialize($body));
    }

}
