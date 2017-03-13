<?php
/**
 * Created by PhpStorm.
 * User: anh
 * Date: 01/03/17
 * Time: 17:22
 */

namespace AppBundle\Controller;


use AppBundle\Service\LoginService;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\HttpFoundation\Session\Session;
//use Symfony\Bundle\FrameworkBundle\Translation\Translator as Translator;
use Symfony\Component\Translation\DataCollectorTranslator as Translator;

trait Services
{
    private function getSession() : Session
    {
        return $this->get('session');
    }

    private function getLogger() : Logger
    {
        return $this->get('logger');
    }

    private function getTranslator(): Translator
    {
        return $this->get('translator');
    }

    private function getLoginService(): LoginService
    {
        return $this->get('login_service');
    }
}