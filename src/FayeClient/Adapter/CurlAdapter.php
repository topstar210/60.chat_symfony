<?php

namespace App\FayeClient\Adapter;

class CurlAdapter implements AdapterInterface
{
    /**
     * {@inheritDoc}
     */
    public function postJSON($url, $body)
    {
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            sprintf('Content-Length: %s', strlen($body)),
        ));

        curl_exec($curl);

        curl_close($curl);
    }
}
