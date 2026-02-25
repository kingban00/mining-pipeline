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

class ProcessCompanyIntelligenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $companyName;

    // Retry configurations for resilience
    public int $tries = 3;
    public int $timeout = 300; // 5 minutes max per company

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

        // 1. Ingestion (Search & Scrape)
        $markdownContext = $firecrawlService->getCompanyContext($this->companyName);

        // 2. Intelligence (Extraction to JSON)
        $structuredData = $geminiService->extractIntelligence($this->companyName, $markdownContext);

        // Domain Validation: Skip storage if no relevant data was found (prevents "ghost" records)
        if (empty($structuredData['leadership']) && empty($structuredData['assets'])) {
            Log::warning("Job Skipped: No mining intelligence found for {$this->companyName}. Possible non-mining company.");
            return;
        }

        // 3. Storage (Relational Database mapping)
        try {
            // Use a transaction to ensure we don't save half the data if something fails
            DB::transaction(function () use ($structuredData) {

                // Find or create the root company record
                $company = Company::firstOrCreate(['name' => $this->companyName]);

                // Clear old data if reprocessing the same company to avoid duplicates
                $company->executives()->delete();
                $company->assets()->delete();

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
            });

            Log::info("Job Completed: Data securely stored for {$this->companyName}");

        } catch (\Exception $e) {
            Log::error("Database Transaction Failed for {$this->companyName}: " . $e->getMessage());
            // Throwing the exception tells Laravel's Queue system to retry the job
            throw $e;
        }
    }
}