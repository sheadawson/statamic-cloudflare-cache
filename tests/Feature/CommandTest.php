<?php

use Eminos\StatamicCloudflareCache\Http\Client;
use Illuminate\Support\Facades\Http;
beforeEach(function () {
    Http::fake([
        'https://api.cloudflare.com/client/v4/zones/*/purge_cache' => Http::response([
            'success' => true,
        ], 200),
    ]);
});

test('command can purge all cache', function () {
    $this->mock(Client::class, function ($mock) {
        $mock->shouldReceive('purgeEverything')
             ->once()
             ->andReturn(true);
    });

    $this->artisan('cloudflare:purge')
         ->expectsOutput('Purging all Cloudflare cache...')
         ->expectsOutput('Cache purged successfully!')
         ->assertExitCode(0);
});

test('command can purge specific URL', function () {
    $this->mock(Client::class, function ($mock) {
        $mock->shouldReceive('purgeUrls')
             ->once()
             ->with(['https://example.com/test'])
             ->andReturn(true);
    });

    $this->artisan('cloudflare:purge', ['--url' => 'https://example.com/test'])
         ->expectsOutput('Purging cache for URL: https://example.com/test')
         ->expectsOutput('Cache purged successfully!')
         ->assertExitCode(0);
});

test('command can purge cache tags', function () {
    $this->mock(Client::class, function ($mock) {
        $mock->shouldReceive('purgeTags')
             ->once()
             ->with(['wangapeka-pages'])
             ->andReturn(true);
        $mock->shouldNotReceive('purgeEverything');
    });

    $this->artisan('cloudflare:purge', ['--tag' => ['wangapeka-pages']])
         ->expectsOutput('Purging cache for tag(s): wangapeka-pages')
         ->expectsOutput('Cache purged successfully!')
         ->assertExitCode(0);
});

test('command can purge multiple cache tags', function () {
    $this->mock(Client::class, function ($mock) {
        $mock->shouldReceive('purgeTags')
             ->once()
             ->with(['pages', 'assets'])
             ->andReturn(true);
    });

    $this->artisan('cloudflare:purge', ['--tag' => ['pages', 'assets']])
         ->expectsOutput('Purging cache for tag(s): pages, assets')
         ->expectsOutput('Cache purged successfully!')
         ->assertExitCode(0);
});

test('command rejects tag combined with url', function () {
    $this->mock(Client::class, function ($mock) {
        $mock->shouldNotReceive('purgeTags');
        $mock->shouldNotReceive('purgeUrls');
        $mock->shouldNotReceive('purgeEverything');
    });

    $this->artisan('cloudflare:purge', [
        '--tag' => ['wangapeka-pages'],
        '--url' => 'https://example.com/test',
    ])
         ->expectsOutput('The --url, --tag options cannot be combined. Provide only one purge target.')
         ->assertExitCode(1);
});

test('command rejects zone combined with domain', function () {
    $this->mock(Client::class, function ($mock) {
        $mock->shouldNotReceive('purgeEverythingForZone');
        $mock->shouldNotReceive('purgeEverything');
    });

    $this->artisan('cloudflare:purge', [
        '--zone' => 'zone_id_123',
        '--domain' => 'example.com',
    ])
         ->expectsOutput('The --zone, --domain options cannot be combined. Provide only one purge target.')
         ->assertExitCode(1);
});

test('command handles failure', function () {
    $this->mock(Client::class, function ($mock) {
        $mock->shouldReceive('purgeEverything')
             ->once()
             ->andReturn(false); // Simulate failure
    });

    $this->artisan('cloudflare:purge')
         ->expectsOutput('Purging all Cloudflare cache...')
         ->expectsOutput('Failed to purge cache. Check logs for details.')
         ->assertExitCode(1); // Expect exit code 1 on failure
});

test('command respects disabled setting', function () {
    // Disable the cache purging
    config(['cloudflare-cache.enabled' => false]);

    // No need to mock Client here, as the command should exit early

    $this->artisan('cloudflare:purge')
         ->expectsOutput('Cloudflare Cache is disabled in configuration.')
         ->assertExitCode(1); // Expect exit code 1 when disabled
});
