<?php
// src/AppBundle/EventListener/LocaleListener.php
namespace AppBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LocaleListener implements EventSubscriberInterface
{
    private $defaultLocale;
    private $logger;

    public function __construct($defaultLocale, $logger)
    {
        $this->defaultLocale = $defaultLocale;
        $this->logger = $logger;
    }

    private function debug($msg) {
        return $this->logger->debug(debug_backtrace(01, 3)[1]['function'] . " ". $msg);
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $this->debug('onKernelRequest');
        $request = $event->getRequest();
        if (!$request->hasPreviousSession()) {
            return;
        }
        // try to see if the locale has been set as a _locale routing parameter
        if ($locale = $request->attributes->get('_locale')) {
            $request->getSession()->set('_locale', $locale);
        } else {
            // if no explicit locale has been set on this request, use one from the session
            $request->setLocale($request->getSession()->get('_locale', $this->defaultLocale));
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            // must be registered after the default Locale listener
            KernelEvents::REQUEST => array(array('onKernelRequest', 15)),
        );
    }
}
?>
