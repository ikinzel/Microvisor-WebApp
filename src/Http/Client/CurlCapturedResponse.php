<?php

namespace App\Http\Client;

use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\JsonException as JsonDecodeException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Sync response for curl-cli transport (not compatible with Symfony HttpClient::stream(); use only synchronously).
 */
final class CurlCapturedResponse implements ResponseInterface
{
    private bool $canceled = false;

    private readonly float $startTime;

    /**
     * @param array<string, list<string>> $normalizedHeaders
     * @param list<string>                $responseHeaderLines mimics php $http_response_header lines
     * @param mixed                       $userData
     */
    public function __construct(
        private readonly string $httpMethod,
        private readonly string $url,
        private readonly int $httpCode,
        private readonly array $normalizedHeaders,
        private readonly string $body,
        private readonly array $responseHeaderLines,
        private readonly mixed $userData,
    ) {
        $this->startTime = microtime(true);
    }

    public function getStatusCode(): int
    {
        return $this->httpCode;
    }

    public function getHeaders(bool $throw = true): array
    {
        $this->throwOnErrorStatus($throw);

        return $this->normalizedHeaders;
    }

    public function getContent(bool $throw = true): string
    {
        $this->throwOnErrorStatus($throw);

        return $this->body;
    }

    public function toArray(bool $throw = true): array
    {
        if ('' === $content = $this->getContent($throw)) {
            throw new JsonDecodeException('Response body is empty.');
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonDecodeException($e->getMessage().\sprintf(' for "%s".', $this->url), $e->getCode());
        }

        if (!\is_array($decoded)) {
            throw new JsonDecodeException(\sprintf('JSON content was expected to decode to an array, "%s" returned for "%s".', \get_debug_type($decoded), $this->url));
        }

        return $decoded;
    }

    public function cancel(): void
    {
        $this->canceled = true;
    }

    public function getInfo(?string $type = null): mixed
    {
        $info = [
            'canceled' => $this->canceled,
            'error' => null,
            'http_code' => $this->httpCode,
            'http_method' => $this->httpMethod,
            'redirect_count' => 0,
            'redirect_url' => null,
            'response_headers' => $this->responseHeaderLines,
            'start_time' => $this->startTime,
            'url' => $this->url,
            'user_data' => $this->userData,
        ];

        return null === $type ? $info : ($info[$type] ?? null);
    }

    private function throwOnErrorStatus(bool $throw): void
    {
        if (!$throw) {
            return;
        }

        $code = $this->httpCode;
        if ($code >= 500) {
            throw new ServerException($this);
        }
        if ($code >= 400) {
            throw new ClientException($this);
        }
        if ($code >= 300) {
            throw new RedirectionException($this);
        }
    }
}
