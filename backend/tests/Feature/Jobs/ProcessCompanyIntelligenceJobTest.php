<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessCompanyIntelligenceJob;
use App\Models\Company;
use App\Services\FirecrawlService;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\MockInterface;
use Tests\TestCase;

use PHPUnit\Framework\Attributes\Test;

class ProcessCompanyIntelligenceJobTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_orchestrates_the_pipeline_and_saves_data_to_the_database()
    {
        // Arrange: Prepare dummy data that the services should return
        $companyName = 'Test Mining Corp';
        $dummyMarkdown = '--- LEADERSHIP --- John Doe';
        $dummyExtractedData = [
            'official_name' => 'Test Mining Corp S.A.',
            'is_mining_sector' => true,
            'leadership' => [
                [
                    'name' => 'John Doe',
                    'expertise' => ['Geology'],
                    'technical_summary' => ['Point 1', 'Point 2', 'Point 3']
                ]
            ],
            'assets' => [
                [
                    'name' => 'Alpha Mine',
                    'commodities' => ['Gold'],
                    'status' => 'operating',
                    'country' => 'Brazil',
                    'state_province' => 'Minas Gerais',
                    'town' => 'Belo Horizonte',
                    'latitude' => -19.916681,
                    'longitude' => -43.934493
                ]
            ]
        ];

        // Arrange: Mock the FirecrawlService
        $firecrawlMock = $this->mock(FirecrawlService::class, function (MockInterface $mock) use ($companyName, $dummyMarkdown) {
            $mock->shouldReceive('getCompanyContext')
                ->once()
                ->with($companyName)
                ->andReturn($dummyMarkdown);
        });

        // Arrange: Mock the GeminiService
        $geminiMock = $this->mock(GeminiService::class, function (MockInterface $mock) use ($companyName, $dummyMarkdown, $dummyExtractedData) {
            $mock->shouldReceive('extractIntelligence')
                ->once()
                ->with($companyName, $dummyMarkdown)
                ->andReturn($dummyExtractedData);
        });

        // Act
        $job = new ProcessCompanyIntelligenceJob($companyName);
        $job->handle($firecrawlMock, $geminiMock);

        // Assert: Verify the Company was created with official_name and status completed
        $this->assertDatabaseHas('companies', [
            'name' => 'Test Mining Corp S.A.',
            'status' => 'completed'
        ]);

        $company = Company::where('name', 'Test Mining Corp S.A.')->first();

        $this->assertDatabaseHas('executives', [
            'fk_company_id' => $company->id,
            'name' => 'John Doe',
        ]);

        $this->assertDatabaseHas('assets', [
            'fk_company_id' => $company->id,
            'name' => 'Alpha Mine',
            'latitude' => -19.916681,
            'longitude' => -43.934493
        ]);
    }

    #[Test]
    public function it_marks_as_rejected_if_it_is_not_a_mining_company()
    {
        $companyName = 'Pizza delivery';
        $dummyMarkdown = 'We deliver pizza.';
        $dummyExtractedData = [
            'official_name' => 'Best Pizza',
            'is_mining_sector' => false,
            'leadership' => [],
            'assets' => []
        ];

        $firecrawlMock = $this->mock(FirecrawlService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getCompanyContext')->once()->andReturn('...');
        });

        $geminiMock = $this->mock(GeminiService::class, function (MockInterface $mock) use ($dummyExtractedData) {
            $mock->shouldReceive('extractIntelligence')->once()->andReturn($dummyExtractedData);
        });

        $job = new ProcessCompanyIntelligenceJob($companyName);
        $job->handle($firecrawlMock, $geminiMock);

        // Assert: Verify it exists but as REJECTED
        // Note: Using Title Case normalization in search now, so "Best Pizza"
        $this->assertDatabaseHas('companies', [
            'name' => 'Best Pizza',
            'status' => 'rejected'
        ]);
    }

    #[Test]
    public function it_skips_processing_if_cache_is_fresh()
    {
        $companyName = 'Existing Mine';
        // Mocking name to match what 'ilike' would find
        Company::create(['name' => $companyName, 'status' => 'completed']);

        // Mock services and ensure they are NEVER called (skip)
        $firecrawlMock = $this->mock(FirecrawlService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getCompanyContext')->never();
        });

        $geminiMock = $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('extractIntelligence')->never();
        });

        $job = new ProcessCompanyIntelligenceJob($companyName);
        $job->handle($firecrawlMock, $geminiMock);

        $this->assertTrue(true);
    }
}