<?php

namespace App\Service;

use App\Exception\ReceiptNotReadableException;
use App\Utils\UploadedMimeType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Mirrors the OpenAI vision flow from ocr_receipt_nutri.py: structured product lines + optional weight.
 */
final class ReceiptOcrExtractorService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a receipt parser for nutrition and grocery logging. The database for nutrition lookup is USDA FoodData Central (English commodity-style foods).

Task: extract only purchasable product lines from the receipt.

Rules:
- For each product, output:
  - "name": a short human-readable label as printed (cleaned): real product words in the receipt language if needed.
    Strip internal store codes that are only digits or mostly digits (e.g. 00017412, 084699803238),
    SKUs, and barcode numbers when they are not the actual product name. If the line is only such a code
    and no readable product name exists in that line or its immediate context, skip the line.
  - "usdaSearch": a separate string — the same product expressed as a short English generic food name (2–8 words)
    suitable for searching a US nutrition database. No brand names, no store names, no SKUs, no paper titles or citations.
    Translate German/Austrian/Swiss abbreviations and words to English (e.g. THUNFISCH/THUN. → tuna, KÄSE → cheese,
    SCHINKEN → ham, BACKPULVER → baking powder, BANANEN → bananas, SOJASAUCE → soy sauce, TOFU → tofu, REIS → rice,
    ROHRZUCKER → sugar, MANDELN → almonds, TOMATENMARK → tomato paste, MOZZARELLA → mozzarella cheese).
    Prefer common categories: "canned tuna in oil", "unsalted butter", "sliced ham", "strawberry yogurt",
    "plant-based yogurt", "soy drink vanilla", "white rice", "pesto", "honey", "tea bags", "eggs", "sourdough bread".
- Top-level "readable" (boolean): Set readable to false if the image is too blurry, dark, glare-covered, cropped, or
    low-resolution so you cannot read real product lines with confidence. Do NOT invent placeholder foods (e.g. random
    snacks or "cookies") when the receipt text is not legible. If uncertain, use readable false and items [].
    Set readable to true only when you can actually read distinct product lines from the receipt.
- If the line shows net weight or net volume, set "weight" to {"value": <number>, "unit": "<unit>"}.
  Use one of: g, kg, mg, ml, l, oz, lb. Convert commas in decimals to dots (e.g. 1,5 kg → 1.5).
  If no weight/volume is stated for that product, set "weight" to null.
- Skip: subtotal, tax, VAT, total, balance, payment method, card lines, change, tips, discounts
  summary lines, empty lines, dates/times alone, loyalty messages, and non-food service fees unless
  they are clearly a product.

Output: a single JSON object, no markdown, with exactly this shape:
{"readable": <true|false>, "items":[{"name":"<string>","usdaSearch":"<string>","weight":<object or null>}, ...]}
If readable is false, items must be []. Each weight object is {"value": <number>, "unit": "<g|kg|mg|ml|l|oz|lb>"}.
PROMPT;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'OPENAI_API_KEY')]
        private readonly string $openaiApiKey,
    ) {
    }

    /**
     * @return list<array{name: string, usdaSearch: string, weight?: array{value: float, unit: string}}>
     */
    public function extractItemsFromUploadedImage(UploadedFile $file): array
    {
        if ($this->openaiApiKey === '') {
            throw new \RuntimeException('OPENAI_API_KEY is not set. Add it to your .env or .env.local to use receipt upload.');
        }

        $mime = UploadedMimeType::guess($file) ?? 'image/jpeg';
        $path = $file->getPathname();
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Could not read uploaded file.');
        }

        $b64 = base64_encode($raw);
        $dataUrl = 'data:'.$mime.';base64,'.$b64;

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
                            ['type' => 'text', 'text' => 'Read the attached receipt image and apply the rules. Respond with only the JSON object, nothing else.'],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
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

        if (!\is_array($parsed)) {
            throw new \InvalidArgumentException('Model JSON root must be an object');
        }

        $readable = $parsed['readable'] ?? true;
        if ($readable === false) {
            throw new ReceiptNotReadableException();
        }

        return $this->normalizeOutput($parsed);
    }

    /**
     * @return list<array{name: string, weight?: array{value: float, unit: string}}>
     */
    private function normalizeOutput(mixed $parsed): array
    {
        if (!\is_array($parsed)) {
            throw new \InvalidArgumentException('Model JSON root must be an object');
        }

        $rawItems = $parsed['items'] ?? null;
        if (!\is_array($rawItems)) {
            throw new \InvalidArgumentException('Model JSON must contain an array "items"');
        }

        $allowedUnits = ['g', 'kg', 'mg', 'ml', 'l', 'oz', 'lb'];
        $items = [];

        foreach ($rawItems as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $name = $row['name'] ?? null;
            if (!\is_string($name)) {
                continue;
            }
            $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
            if ($name === '') {
                continue;
            }

            $usdaSearch = $row['usdaSearch'] ?? null;
            if (!\is_string($usdaSearch)) {
                $usdaSearch = $name;
            }
            $usdaSearch = trim(preg_replace('/\s+/', ' ', $usdaSearch) ?? '');
            if ($usdaSearch === '') {
                $usdaSearch = $name;
            }

            $entry = ['name' => $name, 'usdaSearch' => $usdaSearch];
            $w = $row['weight'] ?? null;
            if ($w === null) {
                $items[] = $entry;
                continue;
            }
            if (!\is_array($w)) {
                continue;
            }
            if (!isset($w['value'])) {
                $items[] = $entry;
                continue;
            }
            $val = filter_var($w['value'], FILTER_VALIDATE_FLOAT);
            if ($val === false) {
                $items[] = $entry;
                continue;
            }
            $unit = $w['unit'] ?? null;
            if (!\is_string($unit)) {
                $items[] = $entry;
                continue;
            }
            $unit = strtolower(trim($unit));
            if (!\in_array($unit, $allowedUnits, true)) {
                $items[] = $entry;
                continue;
            }
            $entry['weight'] = ['value' => (float) $val, 'unit' => $unit];
            $items[] = $entry;
        }

        return $items;
    }
}
