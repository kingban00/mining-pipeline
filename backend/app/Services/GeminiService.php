<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;

    /**
     * Gemini 3 Flash Preview (February 2026):
     * Frontier intelligence with Flash-level speed.
     */
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
    }

    /**
     * Extracts structured JSON intelligence from raw markdown using the Gemini API.
     *
     * @param string $companyName
     * @param string $markdownContext
     * @return array
     */
    public function extractIntelligence(string $companyName, string $markdownContext): array
    {
        Log::info("Starting AI extraction for: {$companyName}");

        $prompt = $this->buildSystemPrompt($companyName);

        try {
            // We use a high timeout because LLM text generation can take a few seconds
            $response = Http::timeout(120)->post("{$this->baseUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt . "\n\n--- CONTEXT TO ANALYZE ---\n" . $markdownContext]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1, // Low temperature to prevent data hallucinations
                    'responseMimeType' => 'application/json', // Forces the output strictly to JSON
                ]
            ]);

            // Resilience: If the API goes down or exceeds quota, throw an exception to trigger a retry
            if ($response->failed()) {
                Log::error("Gemini API request failed for {$companyName}.", ['response' => $response->body()]);
                throw new \Exception("Gemini API request failed with status {$response->status()} for {$companyName}.");
            }

            // Extracts the JSON string from the Gemini response structure
            $jsonString = $response->json('candidates.0.content.parts.0.text');

            if (!$jsonString) {
                Log::warning("Gemini returned empty text for {$companyName}.");
                throw new \Exception("Gemini returned empty text for {$companyName}.");
            }

            // Decodes the JSON string into an associative PHP Array
            $extractedData = json_decode($jsonString, true);

            // Validates if the decoded JSON has the minimum keys expected by the database
            if (!$extractedData || !isset($extractedData['leadership']) || !isset($extractedData['assets'])) {
                Log::warning("Gemini returned invalid JSON structure for {$companyName}.", ['raw' => $jsonString]);
                throw new \Exception("Gemini returned invalid JSON structure for {$companyName}.");
            }

            return $extractedData;

        } catch (\Exception $e) {
            Log::error("Exception connecting to Gemini for {$companyName}. Error: " . $e->getMessage());
            // Re-throw the exception so the caller (Job) can handle retries
            throw $e;
        }
    }

    /**
     * Constructs the strict Prompt Engineering rules for the LLM.
     *
     * @param string $companyName
     * @return string
     */
    private function buildSystemPrompt(string $companyName): string
    {
        // The Heredoc format (<<<PROMPT) keeps the string formatting clean
        return <<<PROMPT
You are a highly precise data extraction AI working for a mining intelligence pipeline.
Your task is to analyze the provided scraped markdown context for the company "{$companyName}" and extract structured data.

CRITICAL RULES:
1. Return ONLY a valid JSON object. No markdown blocks, no conversational text.
2. If data for a specific field is missing in the context, use null (for strings/numbers) or an empty array [].
3. For the "technical_summary" in leadership, you MUST provide exactly 3 bullet points focusing on project experience and operational history.
4. For coordinates, estimate or extract approximate latitude and longitude as numbers (decimals) if the exact location is mentioned.
5. DOMAIN VALIDATION: ONLY extract data if the company explicitly operates in the mining, resources, or exploration sectors. Otherwise, return empty arrays for both leadership and assets to prevent false positives.

REQUIRED JSON SCHEMA:
{
  "leadership": [
    {
      "name": "string (Executive/Board member name)",
      "expertise": ["string", "string"],
      "technical_summary": [
        "string (Bullet 1)",
        "string (Bullet 2)",
        "string (Bullet 3)"
      ]
    }
  ],
  "assets": [
    {
      "name": "string (Mine/Project name)",
      "commodities": ["string", "string"],
      "status": "string (e.g., operating, developing)",
      "country": "string",
      "state_province": "string",
      "town": "string",
      "latitude": float,
      "longitude": float
    }
  ]
}
PROMPT;
    }

    /**
     * Fallback array to ensure graceful degradation if the API fails.
     *
     * @return array
     */
    private function getEmptyResponse(): array
    {
        return [
            'leadership' => [],
            'assets' => []
        ];
    }
}