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
        Log::info("Starting autonomous ingestion for: {$companyName}");

        // 1. Search for Leadership (Board/Executives)
        $leadershipQuery = "{$companyName} mining company board of directors executive management team";
        $leadershipContext = $this->searchAndScrape($leadershipQuery);

        // 2. Search for Assets (Mines/Operations)
        $assetsQuery = "{$companyName} mining company active operations assets mines projects location";
        $assetsContext = $this->searchAndScrape($assetsQuery);

        // Return concatenated context for the LLM to process
        return "--- LEADERSHIP CONTEXT ---\n" . $leadershipContext . "\n\n--- ASSETS CONTEXT ---\n" . $assetsContext;
    }

    /**
     * Consumes the Firecrawl API to search and extract Markdown content.
     * * @param string $query
     * @return string
     */
    private function searchAndScrape(string $query): string
    {
        try {
            // High timeout as web scraping can be slow
            $response = Http::withToken($this->apiKey)
                ->timeout(90)
                ->post("{$this->baseUrl}/search", [
                    'query' => $query,
                    'limit' => 2, // Limit to 2 results for precision and token saving
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