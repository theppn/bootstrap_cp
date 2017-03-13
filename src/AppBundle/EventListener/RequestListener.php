<?php
// src/AppBundle/EventListener/RequestListener.php
namespace AppBundle\EventListener;

use AppBundle\Controller\DefaultController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class RequestListener
{
    private $session;
    private $translator;
    private $logger;

    public function __construct($session, $translator, $logger)
    {
        $this->logger = $logger;
        $this->session = $session;
        $this->translator = $translator;
    }

    private function debug($msg) {
        return $this->logger->debug(debug_backtrace(01, 3)[1]['function'] . " ". $msg);
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $this->debug('start');
        $controller = $event->getController();

        if (!is_array($controller)) {
            $this->debug('!is_array');
            return;
        }
        $this->debug(get_class($controller[0]));
        if ($controller[0] instanceof DefaultController && strpos($event->getRequest()->getRequestUri(), 'facebook') === false ) {
            $request = $event->getRequest();
            $queryStatus = $request->query->get('status');
            $queryToken = $request->query->get('token');
            $querySite = $request->query->get('site');
            $queryNotif = $request->query->get('notif');
            $queryIsMock = $request->query->get('ismock');
            if ($queryToken != "") {
                $this->debug('Token ' . $queryToken . ' caught');
                $this->session->set('token', $queryToken);
            }
            if ($querySite != "") {
                $this->debug('Site ' . $querySite . ' caught');
                $this->session->set('site', $querySite);
            }
            if ($queryNotif != "") {
                $this->debug('Notif ' . $queryNotif . ' caught');
                $this->session->getFlashBag()->add('notif', $this->translator->trans($queryNotif));
            }
            if ($queryStatus != "") {
                $this->debug('Status ' . $queryStatus . ' caught');
                $this->session->set('status', $queryStatus);
            }
            if ($queryIsMock != "") {
                $this->debug('isMock ' . $queryIsMock . ' caught');
                $this->session->set('ismock', $queryIsMock);
            }
        }
    }

    public function onKernelResponse(FilterResponseEvent $event) {
        if($this->session->get('ismock') === "1") {
            $body = $event->getResponse();
            $body->setContent($body->getContent() . '<style>div.mock_indicator {background-color:#FFCC00;color:#000000;position:fixed;width:100px;top:17px;left:0;transform: rotate(-30deg);visibility: visible;opacity: 1;} div.mock_indicator_hidden {visibility: hidden;opacity: 0;transition: visibility 0s 5s, opacity 5s linear;}</style><script>var mock_indicator = document.createElement("DIV");mock_indicator.className = "mock_indicator";mock_indicator.appendChild(document.createTextNode("VISUAL ONLY"));document.body.appendChild(mock_indicator);setTimeout(function() {mock_indicator.className = "mock_indicator mock_indicator_hidden";}, 10000);</script>');
        }
    }
}
?>
