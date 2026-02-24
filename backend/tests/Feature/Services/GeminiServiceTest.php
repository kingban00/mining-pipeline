<?php

namespace Tests\Feature\Services;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    /** @test */
    public function it_extracts_and_formats_intelligence_into_structured_arrays()
    {
        // Arrange: Simulamos a resposta da API do Gemini usando o formato nativo dela.
        // Simulamos que a IA fez o trabalho perfeitamente e devolveu o JSON que pedimos.
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

        // Interceptamos qualquer chamada para a API do Google/Gemini
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($mockedGeminiResponse, 200)
        ]);

        $service = new GeminiService();
        $fakeMarkdownContext = "--- LEADERSHIP CONTEXT ---\nMike Henry is CEO...\n\n--- ASSETS CONTEXT ---\nEscondida is a copper mine...";

        // Act: Executamos o método que vamos criar em seguida
        $result = $service->extractIntelligence('BHP', $fakeMarkdownContext);

        // Assert: Verificamos se o serviço nos entregou a estrutura exata que o Banco de Dados precisa
        $this->assertIsArray($result);
        $this->assertArrayHasKey('leadership', $result);
        $this->assertArrayHasKey('assets', $result);

        // Validamos a tipagem dos dados de Liderança
        $this->assertEquals('Mike Henry', $result['leadership'][0]['name']);
        $this->assertCount(3, $result['leadership'][0]['technical_summary']); // Garante que tem os 3 bullet-points

        // Validamos a tipagem dos dados dos Ativos (especialmente as coordenadas numéricas)
        $this->assertEquals('Escondida', $result['assets'][0]['name']);
        $this->assertEquals(-24.266, $result['assets'][0]['latitude']);
    }

    /** @test */
    public function it_returns_empty_arrays_if_gemini_fails_to_respond()
    {
        // Arrange: Simulamos que a API da IA caiu ou deu erro de quota (Erro 500)
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(null, 500)
        ]);

        $service = new GeminiService();

        // Act
        $result = $service->extractIntelligence('BHP', 'Some context');

        // Assert: O sistema não deve quebrar. Deve retornar arrays vazios para salvar no banco.
        $this->assertIsArray($result);
        $this->assertEmpty($result['leadership']);
        $this->assertEmpty($result['assets']);
    }
}