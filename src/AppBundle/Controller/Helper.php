<?php
/**
 * Created by PhpStorm.
 * User: anh
 * Date: 01/03/17
 * Time: 17:28
 */

namespace AppBundle\Controller;


trait Helper
{
    private function debug($msg) {
        return $this->getLogger()->debug(debug_backtrace(01, 3)[1]['function'] . " ". $msg);
    }

    private function trans($msg) {
        return $this->getTranslator()->trans($msg);
    }

    private function send_email($data) {
        return $this->getMailer()->send_email($data);
    }
}