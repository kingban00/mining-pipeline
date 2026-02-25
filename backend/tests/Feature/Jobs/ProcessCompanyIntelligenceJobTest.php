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

        // Arrange: Mock the FirecrawlService to return our dummy markdown
        $firecrawlMock = $this->mock(FirecrawlService::class, function (MockInterface $mock) use ($companyName, $dummyMarkdown) {
            $mock->shouldReceive('getCompanyContext')
                ->once()
                ->with($companyName)
                ->andReturn($dummyMarkdown);
        });

        // Arrange: Mock the GeminiService to return our predefined array
        $geminiMock = $this->mock(GeminiService::class, function (MockInterface $mock) use ($companyName, $dummyMarkdown, $dummyExtractedData) {
            $mock->shouldReceive('extractIntelligence')
                ->once()
                ->with($companyName, $dummyMarkdown)
                ->andReturn($dummyExtractedData);
        });

        // Act: Instantiate the Job and call handle(), passing the mocked services
        $job = new ProcessCompanyIntelligenceJob($companyName);
        $job->handle($firecrawlMock, $geminiMock);

        // Assert: Verify the Company was created
        $this->assertDatabaseHas('companies', [
            'name' => 'Test Mining Corp'
        ]);

        // Retrieve the created company to check its relations
        $company = Company::where('name', 'Test Mining Corp')->first();

        // Assert: Verify the Executive was saved with the correct relations
        $this->assertDatabaseHas('executives', [
            'fk_company_id' => $company->id,
            'name' => 'John Doe',
        ]);

        // Assert: Verify the Asset was saved with the correct coordinates
        $this->assertDatabaseHas('assets', [
            'fk_company_id' => $company->id,
            'name' => 'Alpha Mine',
            'status' => 'operating',
            'latitude' => -19.916681,
            'longitude' => -43.934493
        ]);
    }
    #[Test]
    public function it_does_not_save_ghost_records_if_no_data_is_extracted()
    {
        // Arrange
        $companyName = 'Ghost Company';
        $dummyMarkdown = 'Context for a pizza company.';

        // Simulates Gemini returning empty arrays (as if it's not a mining company)
        $emptyExtractedData = [
            'leadership' => [],
            'assets' => []
        ];

        $firecrawlMock = $this->mock(FirecrawlService::class, function (MockInterface $mock) use ($companyName, $dummyMarkdown) {
            $mock->shouldReceive('getCompanyContext')->once()->andReturn($dummyMarkdown);
        });

        $geminiMock = $this->mock(GeminiService::class, function (MockInterface $mock) use ($companyName, $dummyMarkdown, $emptyExtractedData) {
            $mock->shouldReceive('extractIntelligence')->once()->andReturn($emptyExtractedData);
        });

        // Act
        $job = new ProcessCompanyIntelligenceJob($companyName);
        $job->handle($firecrawlMock, $geminiMock);

        // Assert: Verify the company was NOT created in the database
        $this->assertDatabaseMissing('companies', [
            'name' => 'Ghost Company'
        ]);
    }
}