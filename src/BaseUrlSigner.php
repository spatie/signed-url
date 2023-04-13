<?php

namespace Spatie\UrlSigner;

use DateTime;
use JetBrains\PhpStorm\Deprecated;
use Spatie\UrlSigner\Exceptions\InvalidExpiration;
use Spatie\UrlSigner\Exceptions\InvalidSignatureKey;
use Spatie\UrlSigner\Support\Url;

abstract class BaseUrlSigner implements UrlSigner
{
    public function __construct(
        protected string $defaultSignatureKey,
        protected string $expiresParameterName = 'expires',
        protected string $signatureParameterName = 'signature'
    ) {
        if ($this->defaultSignatureKey == '') {
            throw InvalidSignatureKey::signatureEmpty();
        }
    }

    abstract protected function createSignature(
        string $url,
        string $expiration,
        string $signatureKey,
    ): string;

    #[Deprecated]
    public function sign(
        string $url,
        int|DateTime $expiration,
        string $signatureKey = null,
    ): string {
        $signatureKey ??= $this->defaultSignatureKey;

        $expiration = $this->getExpirationTimestamp($expiration);

        $signature = $this->createSignature($url, $expiration, $signatureKey);

        return $this->signUrl($url, $expiration, $signature);
    }

    public function signWithDateTimeInterface(
        string $url,
        int|\DateTimeInterface $expiration,
        string $signatureKey = null,
    ): string {
        $signatureKey ??= $this->defaultSignatureKey;

        $expiration = $this->getExpirationTimestampWithDateTimeInterface($expiration);

        $signature = $this->createSignature($url, $expiration, $signatureKey);

        return $this->signUrl($url, $expiration, $signature);
    }

    protected function signUrl(string $url, string $expiration, $signature): string
    {
        return Url::addQueryParameters($url, [
            $this->expiresParameterName => $expiration,
            $this->signatureParameterName => $signature,
        ]);
    }

    public function validate(string $url, string $signatureKey = null): bool
    {
        $signatureKey ??= $this->defaultSignatureKey;

        $queryParameters = Url::queryParameters($url);
        if ($this->isMissingAQueryParameter($queryParameters)) {
            return false;
        }

        $expiration = $queryParameters[$this->expiresParameterName];

        if (! $this->isFuture($expiration)) {
            return false;
        }

        if (! $this->hasValidSignature($url, $signatureKey)) {
            return false;
        }

        return true;
    }

    protected function isMissingAQueryParameter(array $query): bool
    {
        if (! isset($query[$this->expiresParameterName])) {
            return true;
        }

        if (! isset($query[$this->signatureParameterName])) {
            return true;
        }

        return false;
    }

    protected function isFuture(int $timestamp): bool
    {
        return $timestamp >= (new DateTime())->getTimestamp();
    }

    protected function getIntendedUrl(string $url): string
    {
        return Url::withoutParameters($url, [
            $this->expiresParameterName,
            $this->signatureParameterName,
        ]);
    }

    protected function getExpirationTimestamp(DateTime|int $expirationInSeconds): string
    {
        if (is_int($expirationInSeconds)) {
            $expirationInSeconds = (new DateTime())->modify($expirationInSeconds.' seconds');
        }

        if (! $expirationInSeconds instanceof DateTime) {
            throw InvalidExpiration::wrongType();
        }

        if (! $this->isFuture($expirationInSeconds->getTimestamp())) {
            throw InvalidExpiration::isInPast();
        }

        return (string) $expirationInSeconds->getTimestamp();
    }

    protected function getExpirationTimestampWithDateTimeInterface(\DateTimeInterface|int $expirationInSeconds): string
    {
        if (is_int($expirationInSeconds)) {
            $expirationInSeconds = (new DateTime())->modify($expirationInSeconds.' seconds');
        }

        if (! $expirationInSeconds instanceof \DateTimeInterface) {
            throw InvalidExpiration::wrongType();
        }

        if (! $this->isFuture($expirationInSeconds->getTimestamp())) {
            throw InvalidExpiration::isInPast();
        }

        return (string) $expirationInSeconds->getTimestamp();
    }

    protected function hasValidSignature(
        string $url,
        string $signatureKey,
    ): bool {
        $queryParameters = Url::queryParameters($url);

        $expiration = $queryParameters[$this->expiresParameterName];
        $providedSignature = $queryParameters[$this->signatureParameterName];

        $intendedUrl = $this->getIntendedUrl($url);

        $validSignature = $this->createSignature($intendedUrl, $expiration, $signatureKey);

        return hash_equals($validSignature, $providedSignature);
    }
}
