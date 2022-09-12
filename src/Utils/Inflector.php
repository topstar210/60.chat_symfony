<?php

namespace App\Utils;

use Doctrine\Common\Util\Inflector as BaseInflector;

/**
 * Inflector object.
 */
class Inflector extends BaseInflector
{
    /**
     * Modifies a string to remove all non ASCII characters and spaces.
     *
     * @param string $text The text to slugify
     *
     * @return string The slugified text
     */
    public static function slugify($text)
    {
        // replace non letter or digits by _
        $text = preg_replace('~[^\\pL\d]+~u', '_', $text);

        // trim
        $text = trim($text, '_');

        // transliterate
        if (function_exists('iconv')) {
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        } elseif (function_exists('mb_convert_encoding')) {
            $text = mb_convert_encoding($text, 'us-ascii//TRANSLIT', 'utf-8');
        }

        // lowercase
        $text = strtolower($text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        return $text;
    }

    /**
     * Generates a random string.
     *
     * @param type $length
     * @param type $chars
     *
     * @return string
     */
    public static function getRandomString($length, $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789')
    {
        $s = null;
        $cLength = strlen($chars);

        while (strlen($s) < $length) {
            $s .= $chars[mt_rand(0, $cLength-1)];
        }

        return $s;
    }

    /**
     * Generate v4 UUID.
     *
     * Version 4 UUIDs are pseudo-random.
     *
     * @return string
     */
    public static function getUUID()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
