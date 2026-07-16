<?php

namespace Eminos\StatamicCloudflareCache\Tests\Unit;

use Eminos\StatamicCloudflareCache\Tests\TestCase;
use Eminos\StatamicCloudflareCache\Http\Client;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class ClientTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/*/purge_cache' => Http::response([
                'success' => true,
                'errors' => [],
                'messages' => [],
                'result' => ['id' => '9a7806061c88ada191ed06f989cc3dac'],
            ], 200),
        ]);
    }
    
    #[Test]
    public function it_can_purge_specific_urls()
    {
        $client = app(Client::class); // Uses updated Client class
        $result = $client->purgeUrls(['https://example.com/page1', 'https://example.com/page2']);

        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request->url() == 'https://api.cloudflare.com/client/v4/zones/test-zone-id/purge_cache' &&
                   $request->hasHeader('Authorization', 'Bearer test-token') &&
                   isset($request['files']) &&
                   count($request['files']) === 2 &&
                   in_array('https://example.com/page1', $request['files']) &&
                   in_array('https://example.com/page2', $request['files']);
        });
    }
    
    #[Test]
    public function it_can_purge_everything()
    {
        $client = app(Client::class); // Uses updated Client class
        $result = $client->purgeEverything();

        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request->url() == 'https://api.cloudflare.com/client/v4/zones/test-zone-id/purge_cache' &&
                   $request->hasHeader('Authorization', 'Bearer test-token') &&
                   isset($request['purge_everything']) &&
                   $request['purge_everything'] === true;
        });
    }
    
    #[Test]
    public function it_can_purge_tags()
    {
        $client = app(Client::class);
        $result = $client->purgeTags(['wangapeka-pages', 'other-tag']);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() == 'https://api.cloudflare.com/client/v4/zones/test-zone-id/purge_cache' &&
                   $request->hasHeader('Authorization', 'Bearer test-token') &&
                   isset($request['tags']) &&
                   $request['tags'] === ['wangapeka-pages', 'other-tag'] &&
                   !isset($request['purge_everything']) &&
                   !isset($request['files']);
        });
    }

    #[Test]
    public function it_purges_tags_in_all_configured_zones()
    {
        config()->set('cloudflare-cache.zones', [
            'example.com' => 'zone-one',
            'example.org' => 'zone-two',
        ]);

        $client = app(Client::class);
        $result = $client->purgeTags(['wangapeka-pages']);

        $this->assertTrue($result);

        foreach (['zone-one', 'zone-two'] as $zoneId) {
            Http::assertSent(function ($request) use ($zoneId) {
                return $request->url() == "https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache" &&
                       $request['tags'] === ['wangapeka-pages'];
            });
        }
    }

    #[Test]
    public function it_returns_false_when_purging_empty_tags_array()
    {
        $client = app(Client::class);
        $result = $client->purgeTags([]);

        $this->assertFalse($result);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_returns_false_when_purging_empty_urls_array()
    {
        $client = app(Client::class); // Uses updated Client class
        $result = $client->purgeUrls([]);

        $this->assertFalse($result);
        
        Http::assertNothingSent();
    }
}
