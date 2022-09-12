<?php

namespace App\Utils;

use App\Entity\User;
use Swift_Mailer;
use Swift_SmtpTransport;

class Mailer
{
    /**
     * Send email using php mail().
     *
     * @param string $to
     * @param string $subject
     * @param string $message
     *
     * @return boolean
     */
    public static function send($to, $subject, $message)
    {

        if (!is_array($to)) {
            $to = array($to);
        }

        foreach ($to as $key => $value) {
            if ($value instanceof User) {
                if ($value->isVerifiedEmail() && $value->isNotifyViaEmail()) {
                    $to[$key] = $value->getEmail();
                } else {
                    unset($to[$key]);
                }
            }
        }

        if (count($to) === 0) {
            return false;
        }

        $message .= sprintf("
Best,

Your ChatApp Team
http://chatapp.mobi
        ", 'test');//$

        $message = (new \Swift_Message($subject))
            ->setFrom(array('hello@chatapp.mobi'))
            ->setTo($to)
            ->setBody($message);


        $transport = (new Swift_SmtpTransport("mail.guiang.com", 587))
            ->setUsername("smtp@guiang.com")
            ->setPassword("Float123");

        $mailer = new Swift_Mailer($transport);


        return $mailer->send($message);
    }
}
