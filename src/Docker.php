<?php

namespace Sharryy\Docker;

use GuzzleHttp\Client;

class Docker
{
    private Client $client;

    private ConnectionOptions $options;

    public function __construct(?ConnectionOptions $options = null)
    {
        $this->options = $options ?? ConnectionOptions::fromSocket();
        $this->client = new Client($this->options->getGuzzleConfig());
    }

    public function run(string $image, string $code): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'docker-php-');

        file_put_contents($tempFile, $code);

        $createResponse = $this->client->post('/v1.41/containers/create', [
            'json' => [
                'Image' => $image,
                'Cmd' => ['php', '/code.php'],
                'HostConfig' => [
                    'AutoRemove' => false, // Don't auto-remove, we'll do it manually after getting logs
                    'Binds' => [
                        $tempFile.':/code.php:ro',
                    ],
                    'NetworkMode' => 'none',
                    'Memory' => 128 * 1024 * 1024, // 128MB
                ],
            ],
        ]);

        $containerData = json_decode($createResponse->getBody()->getContents(), true);

        $containerId = $containerData['Id'];

        $this->client->post("/v1.41/containers/{$containerId}/start");

        $this->client->post("/v1.41/containers/{$containerId}/wait");

        $logsResponse = $this->client->get("/v1.41/containers/{$containerId}/logs", [
            'query' => [
                'stdout' => true,
                'stderr' => true,
            ],
        ]);

        $output = $this->parseDockerLogs($logsResponse->getBody()->getContents());

        $this->client->delete("/v1.41/containers/{$containerId}");

        unlink($tempFile);

        return $output;
    }

    private function parseDockerLogs(string $logs): string
    {
        // Docker logs have a special format with 8-byte header
        // We need to strip these headers
        $output = '';
        $pos = 0;
        $length = strlen($logs);

        while ($pos < $length) {
            if ($length - $pos >= 8) {
                $header = substr($logs, $pos, 8);
                $size = unpack('N', substr($header, 4, 4))[1] ?? 0;
                $pos += 8;

                if ($size > 0 && $pos + $size <= $length) {
                    $output .= substr($logs, $pos, $size);
                    $pos += $size;
                } else {
                    break;
                }
            } else {
                // If we can't read a full header, just append the rest
                $output .= substr($logs, $pos);
                break;
            }
        }

        return trim($output);
    }
}
