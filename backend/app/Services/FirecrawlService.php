<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirecrawlService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.firecrawl.dev/v1';

    public function __construct()
    {
        $this->apiKey = config('services.firecrawl.key');
    }

    /**
     * Orchestrates the autonomous search for both required scopes: Leadership and Assets.
     * * @param string $companyName
     * @return string The concatenated markdown context.
     */
    public function getCompanyContext(string $companyName): string
    {
        Log::info("Starting precision-targeted ingestion for: {$companyName}");

        // Scope 1: Leadership (Biographies take less space)
        $leadershipQuery = "{$companyName} mining company official board of directors executive management leadership team";
        $leadershipRaw = $this->searchAndScrape($leadershipQuery, 3);
        $leadershipContext = mb_substr($leadershipRaw, 0, 30000); // Allows ~6,000 tokens

        // Scope 2: Assets (Tables and coordinates require more characters)
        $assetsQuery = "{$companyName} mining company list of active mines operations projects location and coordinates overview";
        $assetsRaw = $this->searchAndScrape($assetsQuery, 4);
        $assetsContext = mb_substr($assetsRaw, 0, 50000); // Allows ~11,000 tokens

        return "--- LEADERSHIP CONTEXT ---\n" . $leadershipContext . "\n\n--- ASSETS CONTEXT ---\n" . $assetsContext;
    }

    /**
     * Consumes the Firecrawl API to search and extract Markdown content.
     * * @param string $query
     * @return string
     */
    private function searchAndScrape(string $query, int $limit = 4): string
    {
        try {
            // High timeout as web scraping can be slow
            $response = Http::withToken($this->apiKey)
                ->timeout(90)
                ->post("{$this->baseUrl}/search", [
                    'query' => $query,
                    'limit' => $limit,
                    'scrapeOptions' => [
                        'formats' => ['markdown'],
                        'onlyMainContent' => true // Strip useless navigation/footers (Pragmatism)
                    ]
                ]);

            // Resilience required by the bounty: if it fails, log and continue the pipeline
            if ($response->failed()) {
                Log::error("Firecrawl search failed for query: {$query}", ['response' => $response->body()]);
                return "No data found for this section.";
            }

            $results = $response->json('data', []);
            $markdownContext = "";

            foreach ($results as $result) {
                if (!empty($result['markdown'])) {
                    // Append source URL to aid future RAG/Vector searches
                    $markdownContext .= "Source URL: " . ($result['url'] ?? 'Unknown') . "\n";
                    $markdownContext .= $result['markdown'] . "\n\n";
                }
            }

            return $markdownContext;

        } catch (\Exception $e) {
            Log::error("Connection error with Firecrawl on query: {$query}. Error: " . $e->getMessage());
            return "Error fetching data.";
        }
    }
}