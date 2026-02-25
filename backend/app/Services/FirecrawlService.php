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
     * Parallelizes requests to cut ingestion time by 50%.
     */
    public function getCompanyContext(string $companyName): string
    {
        Log::info("Starting parallelized ingestion for: {$companyName}");

        $leadershipQuery = "{$companyName} mining company official board of directors executive management leadership team";
        $assetsQuery = "{$companyName} mining company list of active mines operations projects location and coordinates overview";

        // Dispatch parallel requests using Laravel Http Pool
        $responses = Http::pool(fn(\Illuminate\Http\Client\Pool $pool) => [
            $pool->as('leadership')->withToken($this->apiKey)->timeout(90)->post("{$this->baseUrl}/search", [
                'query' => $leadershipQuery,
                'limit' => 2,
                'scrapeOptions' => ['formats' => ['markdown'], 'onlyMainContent' => true]
            ]),
            $pool->as('assets')->withToken($this->apiKey)->timeout(90)->post("{$this->baseUrl}/search", [
                'query' => $assetsQuery,
                'limit' => 2,
                'scrapeOptions' => ['formats' => ['markdown'], 'onlyMainContent' => true]
            ]),
        ]);

        $leadershipMarkdown = $this->parseFirecrawlResponse($responses['leadership'], $leadershipQuery);
        $assetsMarkdown = $this->parseFirecrawlResponse($responses['assets'], $assetsQuery);

        return "--- LEADERSHIP CONTEXT ---\n" . $leadershipMarkdown . "\n\n--- ASSETS CONTEXT ---\n" . $assetsMarkdown;
    }

    /**
     * Parses the JSON response from Firecrawl into a concatenated string.
     */
    private function parseFirecrawlResponse($response, string $query): string
    {
        if (!$response || $response->failed()) {
            Log::error("Firecrawl failed for: {$query}", ['body' => $response ? $response->body() : 'No response']);
            return "No data found for this section.";
        }

        $results = $response->json('data', []);
        $markdownContent = "";

        foreach ($results as $result) {
            if (!empty($result['markdown'])) {
                $markdownContent .= "Source URL: " . ($result['url'] ?? 'Unknown') . "\n";
                $markdownContent .= $result['markdown'] . "\n\n";
            }
        }

        return $markdownContent ?: "No markdown extracted for this query.";
    }
}