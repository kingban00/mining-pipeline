<?php

namespace Tests\Feature\Services;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GeminiServiceTest extends TestCase
{
    #[Test]
    public function it_extracts_and_formats_intelligence_into_structured_arrays()
    {
        // Arrange: Simulate Gemini API response using its native format.
        // We simulate that the AI did the job perfectly and returned the JSON we requested.
        $mockedGeminiResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'leadership' => [
                                        [
                                            'name' => 'Mike Henry',
                                            'expertise' => ['Mining', 'Executive Management'],
                                            'technical_summary' => [
                                                'Over 30 years of experience in the resources industry.',
                                                'Appointed CEO of BHP in 2020.',
                                                'Strong background in operational leadership.'
                                            ]
                                        ]
                                    ],
                                    'assets' => [
                                        [
                                            'name' => 'Escondida',
                                            'commodities' => ['Copper'],
                                            'status' => 'operating',
                                            'country' => 'Chile',
                                            'state_province' => 'Antofagasta',
                                            'town' => 'Antofagasta',
                                            'latitude' => -24.266,
                                            'longitude' => -69.066
                                        ]
                                    ]
                                ])
                            ]
                        ]
                    ]
                ]
            ]
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($mockedGeminiResponse, 200)
        ]);

        $service = new GeminiService();

        // Act
        $result = $service->extractIntelligence('BHP', 'Context...');

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('Mike Henry', $result['leadership'][0]['name']);
        $this->assertEquals('Escondida', $result['assets'][0]['name']);
        $this->assertEquals(-24.266, $result['assets'][0]['latitude']);

        // Verify that the prompt sent to Gemini contains the "EXHAUSTIVE" instruction
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];
            return str_contains($prompt, 'BE EXHAUSTIVE') &&
                str_contains($prompt, 'EVERY SINGLE mining asset');
        });
    }

    #[Test]
    public function it_throws_exception_if_gemini_fails_to_respond()
    {
        // Arrange: Simulate API error
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Quota Exceeded'], 429)
        ]);

        $service = new GeminiService();

        // Assert: The system should throw an exception to trigger Job retries.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Gemini API request failed with status 429 for BHP.');

        // Act
        $service->extractIntelligence('BHP', 'Some context');
    }

    #[Test]
    public function it_throws_exception_if_gemini_returns_invalid_json()
    {
        // Arrange: Malformed JSON string
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'this is not json']]]]]
            ], 200)
        ]);

        $service = new GeminiService();

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Gemini returned invalid JSON structure for BHP.');

        // Act
        $service->extractIntelligence('BHP', 'Some context');
    }

    #[Test]
    public function it_throws_exception_if_gemini_returns_missing_keys()
    {
        // Arrange: Valid JSON but missing required 'leadership' key
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode(['only_assets' => []])]]]]]
            ], 200)
        ]);

        $service = new GeminiService();

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Gemini returned invalid JSON structure for BHP.');

        // Act
        $service->extractIntelligence('BHP', 'Some context');
    }

    #[Test]
    public function it_truncates_extremely_large_contexts_before_sending_to_gemini()
    {
        // 80,001 characters
        $largeContext = str_repeat('a', 80001);

        $mockedGeminiResponse = [
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['leadership' => [], 'assets' => []])]]]]]
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($mockedGeminiResponse, 200)
        ]);

        $service = new GeminiService();
        $service->extractIntelligence('BHP', $largeContext);

        // Verify that the prompt sent to Gemini contains the truncation message
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];
            return str_contains($prompt, '[CONTEXT TRUNCATED FOR TOKEN SAVING]') &&
                mb_strlen($prompt) < 85000; // Roughly trunc limit + prompt size
        });
    }
}