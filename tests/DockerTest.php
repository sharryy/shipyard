<?php

namespace Sharryy\Docker\Tests;

use Sharryy\Docker\Docker;
use Sharryy\Docker\ConnectionOptions;

test('can run simple PHP code in container', function () {
    $docker = new Docker();

    $code = '<?php echo "Hello from Docker!";';

    $output = $docker->run('php:8.2-cli', $code);

    expect($output)->toBe('Hello from Docker!');
});

test('can use custom connection options', function () {
    $connectionOptions = ConnectionOptions::fromSocket();

    $docker = new Docker($connectionOptions);

    $code = '<?php echo "Connected via socket!";';

    $output = $docker->run('php:8.2-cli', $code);

    expect($output)->toBe('Connected via socket!');
});

test('can run PHP code with calculations', function () {
    $docker = new Docker();

    $code = '<?php echo 2 + 2;';

    $output = $docker->run('php:8.2-cli', $code);

    expect($output)->toBe('4');
});

test('can capture error output', function () {
    $docker = new Docker();

    $code = '<?php trigger_error("This is a test error", E_USER_WARNING); echo "Done";';

    $output = $docker->run('php:8.2-cli', $code);

    expect($output)->toContain('Warning')
        ->and($output)->toContain('This is a test error')
        ->and($output)->toContain('Done');
});
