<?php

namespace Sharryy\Docker;

use GuzzleHttp\RequestOptions;
use RuntimeException;

readonly class ConnectionOptions
{
    private function __construct(private string $baseUri = 'http://localhost', private array $curlOptions = []) {}

    public static function fromSocket(string $socket = '/var/run/docker.sock'): self
    {
        if (! file_exists($socket)) {
            throw new RuntimeException("Docker socket not found at: {$socket}");
        }

        return new self('http://localhost', [
            CURLOPT_UNIX_SOCKET_PATH => $socket,
        ]);
    }

    public function getGuzzleConfig(): array
    {
        $config = [
            'base_uri' => $this->baseUri,
            RequestOptions::HEADERS => [],
        ];

        if (! empty($this->curlOptions)) {
            $config['curl'] = $this->curlOptions;
        }

        return $config;
    }
}
