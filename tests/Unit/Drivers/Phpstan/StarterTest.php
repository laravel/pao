<?php

declare(strict_types=1);

use Laravel\Pao\Drivers\Phpstan\Starter;
use Laravel\Pao\UserFilters\CaptureFilter;

function phpstanParse(string $input): ?array
{
    CaptureFilter::reset();

    if (! in_array('agent_output_capture', stream_get_filters(), true)) {
        stream_filter_register('agent_output_capture', CaptureFilter::class);
    }

    $filter = stream_filter_append(STDOUT, 'agent_output_capture', STREAM_FILTER_WRITE);
    fwrite(STDOUT, $input);

    if (is_resource($filter)) {
        stream_filter_remove($filter);
    }

    $result = (new Starter)->parse();

    CaptureFilter::reset();

    return $result;
}

it('returns null for empty string', function (): void {
    expect(phpstanParse(''))->toBeNull();
});

it('surfaces raw output for invalid json instead of staying silent', function (): void {
    $result = phpstanParse('not json');

    expect($result)->not->toBeNull()
        ->and($result['raw'])->toBe(['not json'])
        ->and($result)->not->toHaveKey('result')
        ->and($result)->not->toHaveKey('errors');
});

it('surfaces raw output for json without totals', function (): void {
    $result = phpstanParse('{"foo":"bar"}');

    expect($result)->not->toBeNull()
        ->and($result['raw'])->toBe(['{"foo":"bar"}'])
        ->and($result)->not->toHaveKey('result');
});

it('returns passed for zero errors', function (): void {
    $json = (string) json_encode([
        'totals' => ['errors' => 0, 'file_errors' => 0],
        'files' => [],
        'errors' => [],
    ]);

    $result = phpstanParse($json);

    expect($result)->not->toBeNull()
        ->and($result['result'])->toBe('passed')
        ->and($result['errors'])->toBe(0)
        ->and($result)->not->toHaveKey('error_details')
        ->and($result)->not->toHaveKey('general_errors')
        ->and($result)->not->toHaveKey('instructions');
});

it('includes agent instructions when file errors are present', function (): void {
    $json = (string) json_encode([
        'totals' => ['errors' => 0, 'file_errors' => 1],
        'files' => [
            '/src/Foo.php' => [
                'errors' => 1,
                'messages' => [
                    ['message' => 'Some error', 'line' => 5, 'identifier' => 'return.type'],
                ],
            ],
        ],
        'errors' => [],
    ]);

    $result = phpstanParse($json);

    expect($result)->not->toBeNull()
        ->and($result)->toHaveKey('instructions')
        ->and($result['instructions'])->toBeString()
        ->and($result['instructions'])->toContain('Each error has an associated identifier')
        ->and($result['instructions'])->toContain('Do not add type casts just to silence errors.');
});

it('does not include instructions for general errors only', function (): void {
    $json = (string) json_encode([
        'totals' => ['errors' => 1, 'file_errors' => 0],
        'files' => [],
        'errors' => ['Autoload file not found'],
    ]);

    $result = phpstanParse($json);

    expect($result)->not->toBeNull()
        ->and($result)->not->toHaveKey('instructions');
});

it('returns failed with error details', function (): void {
    $json = (string) json_encode([
        'totals' => ['errors' => 0, 'file_errors' => 2],
        'files' => [
            '/src/Foo.php' => [
                'errors' => 2,
                'messages' => [
                    ['message' => 'No type specified', 'line' => 17, 'ignorable' => true, 'identifier' => 'missingType.parameter'],
                    ['message' => 'Undefined property', 'line' => 42, 'ignorable' => true, 'identifier' => 'property.notFound'],
                ],
            ],
        ],
        'errors' => [],
    ]);

    $result = phpstanParse($json);

    expect($result)->not->toBeNull()
        ->and($result['result'])->toBe('failed')
        ->and($result['errors'])->toBe(2)
        ->and($result['error_details'])->toHaveKey('/src/Foo.php')
        ->and($result['error_details']['/src/Foo.php'])->toHaveCount(2)
        ->and($result['error_details']['/src/Foo.php'][0]['line'])->toBe(17)
        ->and($result['error_details']['/src/Foo.php'][0]['message'])->toBe('No type specified')
        ->and($result['error_details']['/src/Foo.php'][0]['identifier'])->toBe('missingType.parameter')
        ->and($result['error_details']['/src/Foo.php'][1]['identifier'])->toBe('property.notFound');
});

it('defaults identifier to unknown when missing', function (): void {
    $json = (string) json_encode([
        'totals' => ['errors' => 0, 'file_errors' => 1],
        'files' => [
            '/src/Foo.php' => [
                'errors' => 1,
                'messages' => [
                    ['message' => 'Some error', 'line' => 5],
                ],
            ],
        ],
        'errors' => [],
    ]);

    $result = phpstanParse($json);

    expect($result)->not->toBeNull()
        ->and($result['error_details']['/src/Foo.php'][0]['identifier'])->toBe('unknown');
});

it('captures general errors', function (): void {
    $json = (string) json_encode([
        'totals' => ['errors' => 1, 'file_errors' => 0],
        'files' => [],
        'errors' => ['Autoload file not found'],
    ]);

    $result = phpstanParse($json);

    expect($result)->not->toBeNull()
        ->and($result['result'])->toBe('failed')
        ->and($result['errors'])->toBe(1)
        ->and($result['general_errors'])->toBe(['Autoload file not found'])
        ->and($result)->not->toHaveKey('error_details');
});

it('combines file errors and general errors', function (): void {
    $json = (string) json_encode([
        'totals' => ['errors' => 1, 'file_errors' => 1],
        'files' => [
            '/src/Foo.php' => [
                'errors' => 1,
                'messages' => [
                    ['message' => 'Error', 'line' => 10, 'identifier' => 'return.type'],
                ],
            ],
        ],
        'errors' => ['General error'],
    ]);

    $result = phpstanParse($json);

    expect($result)->not->toBeNull()
        ->and($result['result'])->toBe('failed')
        ->and($result['errors'])->toBe(2)
        ->and($result['error_details']['/src/Foo.php'])->toHaveCount(1)
        ->and($result['general_errors'])->toHaveCount(1);
});

it('includes tip and non-ignorable fields', function (): void {
    $json = (string) json_encode([
        'totals' => ['errors' => 0, 'file_errors' => 1],
        'files' => [
            '/src/Foo.php' => [
                'errors' => 1,
                'messages' => [
                    [
                        'message' => 'Access to undefined property',
                        'line' => 35,
                        'ignorable' => false,
                        'identifier' => 'property.notFound',
                        'tip' => 'Learn more: https://phpstan.org/blog/solving-phpstan-access-to-undefined-property',
                    ],
                ],
            ],
        ],
        'errors' => [],
    ]);

    $result = phpstanParse($json);

    expect($result)->not->toBeNull()
        ->and($result['error_details']['/src/Foo.php'][0])->toBe([
            'line' => 35,
            'message' => 'Access to undefined property',
            'identifier' => 'property.notFound',
            'ignorable' => false,
            'tip' => 'Learn more: https://phpstan.org/blog/solving-phpstan-access-to-undefined-property',
        ]);
});

it('strips leading non-json content like phpstan note lines', function (): void {
    $json = (string) json_encode([
        'totals' => ['errors' => 0, 'file_errors' => 0],
        'files' => [],
        'errors' => [],
    ]);

    $input = "Note: Using configuration file /home/user/project/phpstan.neon.\n".$json;

    $result = phpstanParse($input);

    expect($result)->not->toBeNull()
        ->and($result['result'])->toBe('passed')
        ->and($result['errors'])->toBe(0);
});

it('truncates error details beyond 30 by default', function (): void {
    $messages = [];
    for ($i = 1; $i <= 50; $i++) {
        $messages[] = ['message' => 'Error '.$i, 'line' => $i * 5, 'identifier' => 'return.type'];
    }

    $json = (string) json_encode([
        'totals' => ['errors' => 0, 'file_errors' => 50],
        'files' => [
            '/src/Foo.php' => [
                'errors' => 50,
                'messages' => $messages,
            ],
        ],
        'errors' => [],
    ]);

    $result = phpstanParse($json);

    expect($result)->not->toBeNull()
        ->and($result['errors'])->toBe(50)
        ->and($result['error_details'])->toHaveKey('/src/Foo.php')
        ->and($result['error_details']['/src/Foo.php'])->toHaveCount(30)
        ->and($result['truncated'])->toBeTrue()
        ->and($result['hint'])->toBe('Pass -v to see all errors.');
});

it('shows all errors when verbose flag is set', function (): void {
    $originalArgv = $_SERVER['argv'] ?? [];
    $_SERVER['argv'] = ['phpstan', 'analyse', '-v'];

    $messages = [];
    for ($i = 1; $i <= 50; $i++) {
        $messages[] = ['message' => 'Error '.$i, 'line' => $i * 5, 'identifier' => 'return.type'];
    }

    $json = (string) json_encode([
        'totals' => ['errors' => 0, 'file_errors' => 50],
        'files' => [
            '/src/Foo.php' => [
                'errors' => 50,
                'messages' => $messages,
            ],
        ],
        'errors' => [],
    ]);

    $result = phpstanParse($json);

    $_SERVER['argv'] = $originalArgv;

    expect($result)->not->toBeNull()
        ->and($result['errors'])->toBe(50)
        ->and($result['error_details']['/src/Foo.php'])->toHaveCount(50)
        ->and($result)->not->toHaveKey('truncated')
        ->and($result)->not->toHaveKey('hint');
});

it('does not truncate when errors are at or below limit', function (): void {
    $messages = [];
    for ($i = 1; $i <= 30; $i++) {
        $messages[] = ['message' => 'Error '.$i, 'line' => $i * 5, 'identifier' => 'return.type'];
    }

    $json = (string) json_encode([
        'totals' => ['errors' => 0, 'file_errors' => 30],
        'files' => [
            '/src/Foo.php' => [
                'errors' => 30,
                'messages' => $messages,
            ],
        ],
        'errors' => [],
    ]);

    $result = phpstanParse($json);

    expect($result)->not->toBeNull()
        ->and($result['errors'])->toBe(30)
        ->and($result['error_details']['/src/Foo.php'])->toHaveCount(30)
        ->and($result)->not->toHaveKey('truncated')
        ->and($result)->not->toHaveKey('hint');
});

it('truncates across multiple files', function (): void {
    $messagesA = [];
    for ($i = 1; $i <= 20; $i++) {
        $messagesA[] = ['message' => 'Error A'.$i, 'line' => $i * 5, 'identifier' => 'return.type'];
    }

    $messagesB = [];
    for ($i = 1; $i <= 20; $i++) {
        $messagesB[] = ['message' => 'Error B'.$i, 'line' => $i * 5, 'identifier' => 'return.type'];
    }

    $json = (string) json_encode([
        'totals' => ['errors' => 0, 'file_errors' => 40],
        'files' => [
            '/src/Foo.php' => ['errors' => 20, 'messages' => $messagesA],
            '/src/Bar.php' => ['errors' => 20, 'messages' => $messagesB],
        ],
        'errors' => [],
    ]);

    $result = phpstanParse($json);

    expect($result)->not->toBeNull()
        ->and($result['errors'])->toBe(40)
        ->and($result['truncated'])->toBeTrue()
        ->and($result['error_details']['/src/Foo.php'])->toHaveCount(20)
        ->and($result['error_details']['/src/Bar.php'])->toHaveCount(10);
});

it('handles multiple files with multiple errors', function (): void {
    $json = (string) json_encode([
        'totals' => ['errors' => 0, 'file_errors' => 3],
        'files' => [
            '/src/Foo.php' => [
                'errors' => 2,
                'messages' => [
                    ['message' => 'Error 1', 'line' => 10, 'identifier' => 'return.type'],
                    ['message' => 'Error 2', 'line' => 20, 'identifier' => 'missingType.parameter'],
                ],
            ],
            '/src/Bar.php' => [
                'errors' => 1,
                'messages' => [
                    ['message' => 'Error 3', 'line' => 5, 'identifier' => 'method.notFound'],
                ],
            ],
        ],
        'errors' => [],
    ]);

    $result = phpstanParse($json);

    expect($result)->not->toBeNull()
        ->and($result['errors'])->toBe(3)
        ->and($result['error_details'])->toHaveCount(2)
        ->and($result['error_details'])->toHaveKey('/src/Foo.php')
        ->and($result['error_details']['/src/Foo.php'])->toHaveCount(2)
        ->and($result['error_details'])->toHaveKey('/src/Bar.php')
        ->and($result['error_details']['/src/Bar.php'])->toHaveCount(1);
});
