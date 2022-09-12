<?php

namespace App\Utils;

use App\Controller\Constant;
use App\Entity\User;

class PushNotification
{
    /**
     * Send a push notification to...
     *
     * @return boolean True is successfully sent, otherwise false
     */
    public static function send(User $user, array $data)
    {
        $error = false;

        /** @deprecated since version 1.2, to be removed in 2.0. */
        /*if ($user->getEndroidGcmId()) {
            if (!self::send2Andoid($user->getEndroidGcmId(), $data)) {
                $error = true;
            }
        }*/
        if ($user->getIosDeviceId()) {
            if (!self::send2iOs($user->getIosDeviceId(), $data)) {
                $error = true;
            }
        }

        foreach ($user->getDevices() as $device) {
            if ($device->isEnabled()) {
                if ($device->isAndroid()) {
                    if (!self::send2Andoid($device->getDeviceId(), $data)) {
                        $error = true;
                    }
                }
                if ($device->isIos()) {
                    if (!self::send2iOs($device->getDeviceId(), $data)) {
                        $error = true;
                    }
                }
            }
        }

        return !$error;
    }

    /**
     * @return boolean True is successfully sent, otherwise false
     */
    public static function send2Andoid($deviceId, array $data)
    {
        return true;
        $tmp = $data;
        switch ($tmp['title']) {
            case 'START_TYPING':
            case 'STOP_TYPING':
            case 'NEW_MESSAGE':
            case 'NEW_CLUB_MESSAGE':
            case 'INVITE':
                $tmp['chat_id'] = $tmp['parameters'];
                break;

            case 'INVITE_CLUB_REQUEST':
            case 'INVITE_CLUB_APPROVE':
                $tmp['club_id'] = $tmp['parameters'];
                break;

            case 'MOMENT_COMMENT':
            case 'MOMENT_LIKE':
                $tmp['moment_id'] = $tmp['parameters'];
                break;
        }
        unset($tmp['parameters']);

        /*if ($app['endroid.gcm']->send($tmp, array($deviceId))) {
            return true;
        }*/

        return false;
    }

    /**
     * Note: Ensure that the $deviceId does not have spaces in it.
     *
     * @return boolean True is successfully sent, otherwise false
     */
    public static function send2iOs($deviceId, array $data)
    {
//        dd($deviceId, $data);
        // provide the Private Key Passphrase (alternatively you can keep this secrete
        // and enter the key manually on the terminal -> remove relevant line from code)
        $passphrase = Constant::$conf_ios_push_notification['passphrase'];

        // provide the Host Information
        $host = Constant::$conf_ios_push_notification['host'];
        $port = Constant::$conf_ios_push_notification['port'];

        // provide the Certificate and Key Data
        $cert = Constant::$conf_ios_push_notification['cert'];

        // override with debug mode
        /*if (true) {
            $host = Constant::$conf_ios_push_notification['dev']['host'];
            $port = Constant::$conf_ios_push_notification['dev']['port'];
            $cert = Constant::$conf_ios_push_notification['dev']['cert'];
        }*/

        $badge = 1;
        $type  = 1; //NEW_MESSAGE
        switch (strtoupper($data['title'])) {
            case 'INVITE': $type = 2; break;
            case 'START_TYPING': $type = 3; $badge = 0; break;
            case 'STOP_TYPING': $type = 4; $badge = 0; break;
            case 'MOMENT_LIKE': $type = 5; break;
            case 'MOMENT_COMMENT': $type = 6; break;
            case 'INVITE_CLUB_REQUEST': $type = 7; break;
            case 'INVITE_CLUB_APPROVE': $type = 8; break;
            case 'NEW_CLUB_MESSAGE': $type = 9; break;
        }

        // create the message content that is to be sent to the device.
        // and encode the body to JSON.
        $body = json_encode(array(
            'aps' => array(
                // the message that is to appear on the dialog.
                'alert' => $data['from_username'],

                // the Badge Number for the Application Icon (integer >=0)
                'badge' => $badge,

                // audible Notification Option
                'sound' => 'default',

                // type of Notification (see above switch for values)
                'payload' => sprintf('%d;%d', $type, $data['parameters']),
            ),
        ));

        try {
            $error = false;

            // create the Socket Stream.
            $context = stream_context_create();
            stream_context_set_option($context, 'ssl', 'local_cert', $cert);

            // remove this line if you would like to enter the Private Key Passphrase manually.
            stream_context_set_option($context, 'ssl', 'passphrase', $passphrase);

            // open the Connection to the APNS Server.
            $socket = stream_socket_client('ssl://'.$host.':'.$port, $error, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $context);

            // check if we were able to open a socket.
            if (!$socket) {
                throw new \Exception(sprintf('APNS Connection Failed: %s %s.', $error, $errstr));
            }

            // build the Binary Notification.
            $message = chr(0).pack('n', 32).pack('H*', $deviceId).pack('n', strlen($body)).$body;

            // send the Notification to the Server.
            $result = fwrite ($socket, $message, strlen($message));

            if (!$result) {
                throw new \Exception('An error occurred during the notification.');
            }

            // close the Connection to the Server.
            fclose ($socket);

            return true;

        } catch (\Exception $e) {
        }

        return false;
    }
}
