<?php

namespace App\Http\Client;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * When ext-curl is missing and allow_url_fopen is off, Symfony falls back to NativeHttpClient
 * and fails on HTTPS. Many hosts still ship the curl CLI; this decorator uses it as a fallback.
 */
final class CurlCliFallbackHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!$this->urlStreamsEffectivelyDisabled()) {
            return $this->httpClient->request($method, $url, $options);
        }

        return $this->requestViaCurlCli($method, $url, $options);
    }

    public function stream(iterable|\Symfony\Contracts\HttpClient\ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->httpClient->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return new self($this->httpClient->withOptions($options));
    }

    private function urlStreamsEffectivelyDisabled(): bool
    {
        if (extension_loaded('curl')) {
            return false;
        }

        return !filter_var(\ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL);
    }

    private function requestViaCurlCli(string $method, string $url, array $options): ResponseInterface
    {
        $curlBinary = (new ExecutableFinder())->find('curl');
        if ($curlBinary === null) {
            throw new \RuntimeException(
                'Outbound HTTPS is blocked: PHP has no cURL extension, allow_url_fopen is off, and the curl program was not found in PATH. '
                .'Ask your host to enable ext-curl or install the curl package.'
            );
        }

        $query = $options['query'] ?? [];
        if (\is_array($query) && $query !== []) {
            $glue = str_contains($url, '?') ? '&' : '?';
            $url .= $glue.http_build_query($query);
        }

        $timeout = (int) ($options['timeout'] ?? 30);
        if ($timeout < 1) {
            $timeout = 30;
        }

        $body = $this->resolveRequestBody($options);
        $requestHeaders = $options['headers'] ?? [];
        if (\array_key_exists('json', $options)) {
            $hasContentType = false;
            foreach ($requestHeaders as $name => $_) {
                if (strcasecmp((string) $name, 'Content-Type') === 0) {
                    $hasContentType = true;
                    break;
                }
            }
            if (!$hasContentType) {
                $requestHeaders['Content-Type'] = ['application/json'];
            }
        }

        $headerFile = tempnam(sys_get_temp_dir(), 'sf_hdr_');
        $bodyOutFile = tempnam(sys_get_temp_dir(), 'sf_body_');
        $bodyInFile = null;

        try {
            $argv = [
                $curlBinary,
                '-sS',
                '-X',
                strtoupper($method),
                '-D',
                $headerFile,
                '-o',
                $bodyOutFile,
                '--max-time',
                (string) $timeout,
                '--connect-timeout',
                (string) min($timeout, 30),
                $url,
            ];

            foreach ($requestHeaders as $name => $values) {
                foreach ((array) $values as $value) {
                    $argv[] = '-H';
                    $argv[] = $name.': '.$value;
                }
            }

            if ($body !== null) {
                $bodyInFile = tempnam(sys_get_temp_dir(), 'sf_payload_');
                file_put_contents($bodyInFile, $body);
                $argv[] = '--data-binary';
                $argv[] = '@'.$bodyInFile;
                $argv[] = '-H';
                $argv[] = 'Expect:';
            }

            $process = new Process($argv);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException(trim('curl failed: '.$process->getErrorOutput().' '.$process->getOutput()) ?: 'curl exited '.$process->getExitCode());
            }

            $rawHeaders = file_get_contents($headerFile);
            $responseBody = file_get_contents($bodyOutFile);
            if ($rawHeaders === false || $responseBody === false) {
                throw new \RuntimeException('Could not read curl output files.');
            }

            [$status, $respHeadersMap] = $this->parseCurlHeaderBlock($rawHeaders);
            $headerLines = $this->curlDumpHeaderLines($rawHeaders);

            return new CurlCapturedResponse(
                strtoupper($method),
                $url,
                $status,
                $respHeadersMap,
                $responseBody,
                $headerLines,
                $options['user_data'] ?? null,
            );
        } finally {
            foreach ([$headerFile, $bodyOutFile, $bodyInFile] as $f) {
                if (\is_string($f) && is_file($f)) {
                    @unlink($f);
                }
            }
        }
    }

    private function resolveRequestBody(array $options): ?string
    {
        if (\array_key_exists('json', $options)) {
            $encoded = json_encode($options['json'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $encoded;
        }

        $body = $options['body'] ?? null;
        if ($body === null || $body === '') {
            return null;
        }
        if (\is_string($body)) {
            return $body;
        }
        if (\is_resource($body)) {
            $s = stream_get_contents($body);
            if ($s === false) {
                throw new \InvalidArgumentException('Could not read request body stream.');
            }

            return $s;
        }

        throw new \InvalidArgumentException('Unsupported request body type for curl CLI fallback: '.\get_debug_type($body));
    }

    /**
     * @return array{0: int, 1: array<string, list<string>>}
     */
    private function parseCurlHeaderBlock(string $raw): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($raw)) ?: [];
        if ($lines === []) {
            return [0, []];
        }

        $status = 0;
        $first = $lines[0];
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $first, $m)) {
            $status = (int) $m[1];
        }

        $headers = [];
        for ($i = 1, $n = \count($lines); $i < $n; ++$i) {
            $line = $lines[$i];
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, 'HTTP/')) {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            if ($name === '') {
                continue;
            }
            $headers[$name][] = $value;
        }

        return [$status, $headers];
    }

    /**
     * @return list<string>
     */
    private function curlDumpHeaderLines(string $raw): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        $out = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $out[] = $line;
        }

        return $out;
    }
}
