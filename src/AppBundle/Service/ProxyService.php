<?php

namespace AppBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProxyService {
    private $session;
    private $rest;
    private $logger;

    function __construct($session, $rest, $logger) {
        $this->session = $session;
        $this->rest = $rest;
        $this->logger = $logger;
    }

    private function debug($msg) {
        return $this->logger->debug(debug_backtrace(01, 3)[1]['function'] . " ". $msg);
    }

    public function proxyGet($url) {
        $this->debug("url is " . $url);
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_MAXREDIRS      => 10,
        );
        $ch = curl_init( $url );
        curl_setopt_array( $ch, $options );
        $remoteSite = curl_exec( $ch );
        $header = curl_getinfo( $ch );
        curl_close( $ch );

        $header['content'] = $remoteSite;
        return $header;
    }
}
?>