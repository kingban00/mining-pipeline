<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Executive;
use App\Models\Asset;
use App\Services\FirecrawlService;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProcessCompanyIntelligenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $companyName;

    // Retry configurations for resilience
    public int $tries = 3;
    public int $timeout = 300; // 5 minutes max per company

    public array $backoff = [60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(string $companyName)
    {
        $this->companyName = $companyName;
    }

    /**
     * Execute the job. Orchestrates Search, Extraction, and Storage.
     */
    public function handle(FirecrawlService $firecrawlService, GeminiService $geminiService): void
    {
        Log::info("Job Started: Processing intelligence for {$this->companyName}");

        // 0. Pre-flight Check: Prevent redundant AI processing (Token Saving)
        // Check if company exists and was processed recently
        $existingCompany = Company::where('name', 'ilike', $this->companyName)->first();
        if ($existingCompany && $existingCompany->updated_at->gt(now()->subHours(24))) {
            Log::info("Job Skipped: {$this->companyName} version exists in cache.");
            return;
        }

        // 1. Ingestion (Search & Scrape)
        $cacheKey = 'firecrawl_markdown_' . Str::slug($this->companyName);

        $markdownContext = Cache::remember($cacheKey, now()->addHours(2), function () use ($firecrawlService) {
            Log::info("Fetching new context from Firecrawl for: {$this->companyName}");
            return $firecrawlService->getCompanyContext($this->companyName);
        });

        // 2. Intelligence (Extraction to JSON)
        $structuredData = $geminiService->extractIntelligence($this->companyName, $markdownContext);

        // Name resolution: Use the AI-identified official name to handle aliases/typos
        $officialName = $structuredData['official_name'] ?? $this->companyName;
        $isMining = $structuredData['is_mining_sector'] ?? false;

        // Logic check: Even if AI says it is mining, if it found absolutely nothing, we treat as rejected for now
        $hasData = !empty($structuredData['leadership']) || !empty($structuredData['assets']);

        if (!$isMining || !$hasData) {
            $this->updateOrCreateCompanyStatus($officialName, 'rejected');
            Log::warning("Job Finished: Company {$officialName} rejected (Non-mining or No Data).");
            return;
        }

        // 3. Storage
        try {
            DB::transaction(function () use ($structuredData, $officialName) {
                // Find or create using resolved name
                $company = Company::firstOrCreate(['name' => $officialName]);

                // Clear old data
                $company->executives()->delete();
                $company->assets()->delete();

                // Standardize and Update State
                $company->update([
                    'name' => $officialName,
                    'status' => 'completed'
                ]);

                // Insert Leadership
                if (!empty($structuredData['leadership'])) {
                    foreach ($structuredData['leadership'] as $executiveData) {
                        Executive::create([
                            'fk_company_id' => $company->id,
                            'name' => $executiveData['name'] ?? 'Unknown',
                            'expertise' => $executiveData['expertise'] ?? [],
                            'technical_summary' => $executiveData['technical_summary'] ?? []
                        ]);
                    }
                }

                // Insert Assets/Mines
                if (!empty($structuredData['assets'])) {
                    foreach ($structuredData['assets'] as $assetData) {
                        Asset::create([
                            'fk_company_id' => $company->id,
                            'name' => $assetData['name'] ?? 'Unknown',
                            'commodities' => $assetData['commodities'] ?? [],
                            'status' => $assetData['status'] ?? null,
                            'country' => $assetData['country'] ?? null,
                            'state_province' => $assetData['state_province'] ?? null,
                            'town' => $assetData['town'] ?? null,
                            'latitude' => $assetData['latitude'] ?? null,
                            'longitude' => $assetData['longitude'] ?? null,
                        ]);
                    }
                }

                $company->touch();
            });

            Log::info("Job Completed: Data stored for {$officialName}");

        } catch (\Exception $e) {
            Log::error("Database Transaction Failed for {$officialName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Updates company status and refreshes cache timer.
     */
    private function updateOrCreateCompanyStatus(string $name, string $status): void
    {
        $company = Company::where('name', 'ilike', $name)->first();
        if ($company) {
            $company->update(['status' => $status]);
            $company->touch();
        } else {
            Company::create(['name' => $name, 'status' => $status]);
        }
    }
}