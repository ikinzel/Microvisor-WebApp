<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;


final class BloodTestPdfExtractorService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a blood test result parser. Extract vitamin and mineral values from the provided blood test PDF report.

Only extract values for nutrients from this list:
Vitamin A, Vitamin C, Vitamin D, Vitamin E, Vitamin K, Thiamin (B1), Riboflavin (B2), Niacin (B3), Pantothenic acid (B5), Vitamin B6, Biotin (B7), Folate, Vitamin B12, Choline, Calcium, Chloride, Chromium, Fluoride, Iron, Magnesium, Manganese, Potassium, Zinc

For each nutrient found in the report output:
- "name": the exact name from the list above (e.g. "Vitamin D", "Iron")
- "value": the measured numeric value as a float
- "unit": the unit of measurement as printed (e.g. "ng/mL", "mg/dL", "µg/L", "mmol/L")

Output: a single JSON object, no markdown, with exactly this shape:
{"results":[{"name":"<string>","value":<number>,"unit":"<string>"}, ...]}

Only include nutrients that are actually present in the report. Skip any nutrient not found.
PROMPT;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'OPENAI_API_KEY')]
        private readonly string $openaiApiKey,
    ) {
    }

    /**
     * @return list<array{name: string, value: float, unit: string}>
     */
    public function extractFromPdf(UploadedFile $file): array
    {
        if ($this->openaiApiKey === '') {
            throw new \RuntimeException('OPENAI_API_KEY is not set. Add it to your .env or .env.local to use blood test upload.');
        }

        $path = $file->getPathname();
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Could not read uploaded file.');
        }

        $b64 = base64_encode($raw);

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->openaiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4o-mini',
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => 'Extract all vitamin and mineral values from this blood test report. Respond with only the JSON object.'],
                            [
                                'type' => 'file',
                                'file' => [
                                    'filename' => 'bloodtest.pdf',
                                    'file_data' => 'data:application/pdf;base64,'.$b64,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'timeout' => 120,
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('OpenAI API error: HTTP '.$status);
        }

        $payload = $response->toArray();
        $content = $payload['choices'][0]['message']['content'] ?? '{}';
        if (!\is_string($content)) {
            $content = '{}';
        }

        $parsed = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $this->normalizeOutput($parsed);
    }

    /**
     * @return list<array{name: string, value: float, unit: string}>
     */
    private function normalizeOutput(mixed $parsed): array
    {
        if (!\is_array($parsed)) {
            throw new \InvalidArgumentException('Model JSON root must be an object.');
        }

        $rawItems = $parsed['results'] ?? null;
        if (!\is_array($rawItems)) {
            throw new \InvalidArgumentException('Model JSON must contain an array "results".');
        }

        $items = [];

        foreach ($rawItems as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $name = $row['name'] ?? null;
            if (!\is_string($name) || trim($name) === '') {
                continue;
            }

            $valueRaw = $row['value'] ?? null;
            $value = filter_var($valueRaw, FILTER_VALIDATE_FLOAT);
            if ($value === false) {
                continue;
            }

            $unit = $row['unit'] ?? null;
            if (!\is_string($unit) || trim($unit) === '') {
                continue;
            }

            $items[] = [
                'name' => trim($name),
                'value' => (float) $value,
                'unit' => trim($unit),
            ];
        }

        return $items;
    }
}
