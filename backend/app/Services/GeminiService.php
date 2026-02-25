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
            // Safety Truncation: Prevent token waste from extremely large markdown contexts
            $truncatedContext = mb_substr($markdownContext, 0, 30000);
            if (mb_strlen($markdownContext) > 30000) {
                $truncatedContext .= "\n\n[CONTEXT TRUNCATED FOR TOKEN SAVING]";
                Log::info("Context truncated for {$companyName} ({$this->baseUrl})");
            }

            // We use a high timeout because LLM text generation can take a few seconds
            $response = Http::timeout(120)->post("{$this->baseUrl}?key={$this->apiKey}", [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt . "\n\n--- CONTEXT TO ANALYZE ---\n" . $truncatedContext]
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
        return <<<PROMPT
Role: Expert Mining Analyst AI.
Task: Extract structured JSON for "{$companyName}" from markdown.

STRICT INSTRUCTIONS:
1. Output ONLY valid JSON.
2. Domain Lock: If context is NOT about mining/resources/extraction, return {"is_mining_sector": false, "leadership":[], "assets":[]}.
3. Missing fields: Use null or [].
4. Leadership: Extract top executives. "technical_summary" must be exactly 3 precise bullet points on their mining/operational career.
5. Assets: Extract mines/projects. Estimate lat/long decimals from location descriptions if not explicit.
6. Official Name: Extract the full legal/official name of the company.

REQUIRED JSON SCHEMA:
{
  "official_name": "Full Official Company Name",
  "is_mining_sector": boolean,
  "leadership": [
    {
      "name": "string",
      "expertise": ["string"],
      "technical_summary": ["bullet 1", "bullet 2", "bullet 3"]
    }
  ],
  "assets": [
    {
      "name": "string",
      "commodities": ["string"],
      "status": "string (developing/operating/care and maintenance)",
      "country": "string",
      "state_province": "string",
      "latitude": float,
      "longitude": float
    }
  ]
}
PROMPT;
    }

}