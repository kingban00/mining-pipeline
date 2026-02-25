<?php

namespace Tests\Feature\Services;

use App\Services\FirecrawlService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

use PHPUnit\Framework\Attributes\Test;

class FirecrawlServiceTest extends TestCase
{
    #[Test]
    public function it_fetches_and_structures_company_context_successfully()
    {
        // Arrange: Fake the HTTP response so we don't actually hit the Firecrawl API
        Http::fake([
            'api.firecrawl.dev/v1/search' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'url' => 'https://bhp.com/board',
                        'markdown' => '## Board of Directors\nJohn Doe - CEO'
                    ]
                ]
            ], 200)
        ]);

        $service = new FirecrawlService();

        // Act
        $context = $service->getCompanyContext('BHP');

        // Assert: Ensure both searches (leadership and assets) are in the string
        $this->assertStringContainsString('--- LEADERSHIP CONTEXT ---', $context);
        $this->assertStringContainsString('John Doe - CEO', $context);
        $this->assertStringContainsString('--- ASSETS CONTEXT ---', $context);

        // Verify that 2 calls were made to Firecrawl (one for leadership, one for assets)
        // And verify they use the new increased limits (3 and 4)
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request->url() === 'https://api.firecrawl.dev/v1/search' &&
                $request['limit'] === 3;
        });

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request->url() === 'https://api.firecrawl.dev/v1/search' &&
                $request['limit'] === 4;
        });
    }

    #[Test]
    public function it_handles_api_failures_gracefully_without_breaking_the_pipeline()
    {
        // Arrange: Simulate a 500 Server Error from Firecrawl
        Http::fake([
            'api.firecrawl.dev/v1/search' => Http::response(null, 500)
        ]);

        $service = new FirecrawlService();

        // Act
        $context = $service->getCompanyContext('Unknown Mining Co');

        // Assert: It should return the fallback message instead of throwing an exception
        $this->assertStringContainsString('No data found for this section.', $context);
    }
}