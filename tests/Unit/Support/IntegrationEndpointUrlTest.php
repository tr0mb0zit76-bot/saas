<?php

namespace Tests\Unit\Support;

use App\Support\IntegrationEndpointUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IntegrationEndpointUrlTest extends TestCase
{
    public function test_public_https_url_is_allowed(): void
    {
        IntegrationEndpointUrl::assertPublicHttpUrl('https://api.tinkoff.ru/v1');
        $this->assertTrue(true);
    }

    #[DataProvider('blockedUrls')]
    public function test_private_and_metadata_urls_are_rejected(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        IntegrationEndpointUrl::assertPublicHttpUrl($url);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function blockedUrls(): array
    {
        return [
            ['http://127.0.0.1/secret'],
            ['http://10.0.0.5/odata'],
            ['http://192.168.1.1/'],
            ['http://localhost/admin'],
            ['file:///etc/passwd'],
            ['https://metadata.google.internal/'],
        ];
    }
}
