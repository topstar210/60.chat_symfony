<?php

namespace App\FayeClient\Adapter;

use Guzzle\Service\ClientInterface;
use Guzzle\Service\Client;

class GuzzleAdapter implements AdapterInterface
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @param ClientInterface $client Guzzle client
     */
    public function __construct(ClientInterface $client = null)
    {
        $this->client = null === $client ? new Client() : $client;
    }

    /**
     * {@inheritDoc}
     */
    public function postJSON($url, $body)
    {
        $this->client
            ->post($url, array('Content-Type' => 'application/json'), $body)
            ->send();
    }
}
