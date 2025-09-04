<?php

declare(strict_types=1);

namespace CodeQ\OAuthSymfonyMailer\Exception;

class OAuthTokenRequestException extends \RuntimeException
{
    private int $statusCode;
    private ?string $error;
    private ?string $errorDescription;
    private ?array $errorCodes;
    private ?string $traceId;
    private ?string $correlationId;
    private ?string $timestamp;
    private ?string $errorUri;

    public function __construct(
        string $message,
        int $statusCode,
        ?string $error = null,
        ?string $errorDescription = null,
        ?array $errorCodes = null,
        ?string $traceId = null,
        ?string $correlationId = null,
        ?string $timestamp = null,
        ?string $errorUri = null
    ) {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->error = $error;
        $this->errorDescription = $errorDescription;
        $this->errorCodes = $errorCodes;
        $this->traceId = $traceId;
        $this->correlationId = $correlationId;
        $this->timestamp = $timestamp;
        $this->errorUri = $errorUri;
    }

    public static function fromHttp(int $statusCode, string $body): self
    {
        $error = $errorDescription = $traceId = $correlationId = $timestamp = $errorUri = null;
        $errorCodes = null;
        $short = null;

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $error = isset($decoded['error']) ? (string)$decoded['error'] : null;
            $errorDescription = isset($decoded['error_description']) ? (string)$decoded['error_description'] : null;
            $errorCodes = isset($decoded['error_codes']) && is_array($decoded['error_codes']) ? $decoded['error_codes'] : null;
            $traceId = isset($decoded['trace_id']) ? (string)$decoded['trace_id'] : null;
            $correlationId = isset($decoded['correlation_id']) ? (string)$decoded['correlation_id'] : null;
            $timestamp = isset($decoded['timestamp']) ? (string)$decoded['timestamp'] : null;
            $errorUri = isset($decoded['error_uri']) ? (string)$decoded['error_uri'] : null;
        }

        if ($error || $errorDescription) {
            $short = trim(sprintf('%s: %s', $error ?? 'error', $errorDescription ?? ''));
        }

        $message = $short
            ? sprintf('OAuth token request failed (HTTP %d) - %s', $statusCode, $short)
            : sprintf('OAuth token request failed (HTTP %d).', $statusCode);

        return new self($message, $statusCode, $error, $errorDescription, $errorCodes, $traceId, $correlationId, $timestamp, $errorUri);
    }

    public function getStatusCode(): int { return $this->statusCode; }
    public function getError(): ?string { return $this->error; }
    public function getErrorDescription(): ?string { return $this->errorDescription; }
    public function getErrorCodes(): ?array { return $this->errorCodes; }
    public function getTraceId(): ?string { return $this->traceId; }
    public function getCorrelationId(): ?string { return $this->correlationId; }
    public function getTimestamp(): ?string { return $this->timestamp; }
    public function getErrorUri(): ?string { return $this->errorUri; }
}
