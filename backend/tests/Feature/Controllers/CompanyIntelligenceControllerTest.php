<?php

namespace Tests\Feature\Controllers;

use App\Jobs\ProcessCompanyIntelligenceJob;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyIntelligenceControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SUCCESS SCENARIOS
     */

    #[Test]
    public function it_dispatches_jobs_for_valid_company_names()
    {
        Queue::fake();

        $payload = ['companies' => 'BHP, Rio Tinto, , Vale'];

        $response = $this->postJson('/api/companies/process', $payload);

        $response->assertStatus(202)
            ->assertJson([
                'message' => 'Processing started successfully in the background.',
                'companies_queued' => 3
            ]);

        Queue::assertPushed(ProcessCompanyIntelligenceJob::class, 3);
        Queue::assertPushed(ProcessCompanyIntelligenceJob::class, function ($job) {
            return in_array($job->companyName, ['BHP', 'Rio Tinto', 'Vale']);
        });
    }

    #[Test]
    public function it_trims_and_deduplicates_company_names()
    {
        Queue::fake();

        $payload = ['companies' => ' BHP , BHP, Rio Tinto '];

        $response = $this->postJson('/api/companies/process', $payload);

        $response->assertStatus(202)
            ->assertJson(['companies_queued' => 2]);

        Queue::assertPushed(ProcessCompanyIntelligenceJob::class, 2);
    }

    #[Test]
    public function it_lists_companies_with_pagination()
    {
        for ($i = 1; $i <= 15; $i++) {
            Company::create([
                'name' => "Company " . str_pad($i, 2, '0', STR_PAD_LEFT),
                'status' => 'completed'
            ]);
        }

        $response = $this->getJson('/api/companies');

        $response->assertStatus(200)
            ->assertJsonPath('total', 15)
            ->assertJsonPath('per_page', 10)
            ->assertJsonCount(10, 'data');
    }

    #[Test]
    public function it_filters_companies_by_search_query()
    {
        Company::create(['name' => 'BHP Group', 'status' => 'completed']);
        Company::create(['name' => 'Rio Tinto', 'status' => 'completed']);

        $response = $this->getJson('/api/companies?search=BHP');

        $response->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonFragment(['name' => 'BHP Group'])
            ->assertJsonMissing(['name' => 'Rio Tinto']);
    }

    #[Test]
    public function it_shows_company_details_with_relations()
    {
        $company = Company::create(['name' => 'Tech Mining', 'status' => 'completed']);

        $company->executives()->create([
            'name' => 'Jane Doe',
            'expertise' => ['Engineering'],
            'technical_summary' => ['Point 1', 'Point 2', 'Point 3']
        ]);

        $response = $this->getJson("/api/companies/{$company->id}");

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Tech Mining')
            ->assertJsonPath('executives.0.name', 'Jane Doe');
    }

    /**
     * FAILURE & EDGE CASE SCENARIOS
     */

    #[Test]
    public function it_rejects_batches_larger_than_ten_companies()
    {
        Queue::fake();

        $names = [];
        for ($i = 0; $i < 11; $i++) {
            $names[] = "UniqueCompany_{$i}";
        }

        $response = $this->postJson('/api/companies/process', [
            'companies' => implode(',', $names)
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Please limit your request to a maximum of 10 companies to ensure processing stability.');

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_fails_validation_for_missing_companies_key()
    {
        $response = $this->postJson('/api/companies/process', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('companies');
    }

    #[Test]
    public function it_returns_400_for_empty_comma_strings()
    {
        $response = $this->postJson('/api/companies/process', ['companies' => ', , , ']);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'No valid company names provided.');
    }

    #[Test]
    public function it_returns_404_for_non_existent_company()
    {
        $response = $this->getJson('/api/companies/00000000-0000-0000-0000-000000000000');
        $response->assertStatus(404);
    }

    #[Test]
    public function it_returns_empty_pagination_for_no_search_matches()
    {
        Company::create(['name' => 'Vale', 'status' => 'completed']);
        $response = $this->getJson('/api/companies?search=NonExistent');

        $response->assertStatus(200)
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_fails_validation_if_companies_is_not_a_string()
    {
        $response = $this->postJson('/api/companies/process', [
            'companies' => ['BHP', 'Vale']
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('companies');
    }

}