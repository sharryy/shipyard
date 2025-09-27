<?php

namespace Sharryy\Docker\Tests;

use Sharryy\Docker\ConnectionOptions;

test('can create connection from socket', function () {
    $options = ConnectionOptions::fromSocket('/var/run/docker.sock');

    $config = $options->getGuzzleConfig();

    expect($config['base_uri'])->toBe('http://localhost')
        ->and($config['curl'][CURLOPT_UNIX_SOCKET_PATH])->toBe('/var/run/docker.sock');
});

test('throws exception for non-existent socket', function () {
    ConnectionOptions::fromSocket('/non/existent/socket.sock');
})->throws(\RuntimeException::class, 'Docker socket not found');
