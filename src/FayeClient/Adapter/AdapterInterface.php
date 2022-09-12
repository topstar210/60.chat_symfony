<?php

namespace App\FayeClient\Adapter;

/**
 * Interface for HTTP adapter to make a post Request
 */
interface AdapterInterface
{
    /**
     * Exec a post request with json content type.
     *
     * @param string $url  Request url
     * @param string $body Body to send
     */
    public function postJSON($url, $body);
}
