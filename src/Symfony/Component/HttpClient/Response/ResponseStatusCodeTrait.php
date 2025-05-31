<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Response;

/**
 * Checking HTTP response status codes explicitly.
 *
 * @author Jimmy Martin <jimmy.martin952@gmail.com>
 */
trait ResponseStatusCodeTrait
{
    public function isOk(): bool
    {
        return 200 === $this->getHttpCode();
    }

    public function isCreated(): bool
    {
        return 201 === $this->getHttpCode();
    }

    public function isAccepted(): bool
    {
        return 202 === $this->getHttpCode();
    }

    public function isNoContent(int $statusCode = 204): bool
    {
        return $statusCode === $this->getHttpCode() && '' === $this->getContent(false);
    }

    public function isMovedPermanently(): bool
    {
        return 301 === $this->getHttpCode();
    }

    public function isFound(): bool
    {
        return 302 === $this->getHttpCode();
    }

    public function isNotModified(): bool
    {
        return 304 === $this->getHttpCode();
    }

    public function isBadRequest(): bool
    {
        return 400 === $this->getHttpCode();
    }

    public function isUnauthorized(): bool
    {
        return 401 === $this->getHttpCode();
    }

    public function isPaymentRequired(): bool
    {
        return 402 === $this->getHttpCode();
    }

    public function isForbidden(): bool
    {
        return 403 === $this->getHttpCode();
    }

    public function isNotFound(): bool
    {
        return 404 === $this->getHttpCode();
    }

    public function isMethodNotAllowed(): bool
    {
        return 405 === $this->getHttpCode();
    }

    public function isNotAcceptable(): bool
    {
        return 406 === $this->getHttpCode();
    }

    public function isRequestTimeout(): bool
    {
        return 408 === $this->getHttpCode();
    }

    public function isConflict(): bool
    {
        return 409 === $this->getHttpCode();
    }

    public function isGone(): bool
    {
        return 410 === $this->getHttpCode();
    }

    public function isUnprocessableEntity(): bool
    {
        return 422 === $this->getHttpCode();
    }

    public function isTooManyRequests(): bool
    {
        return 429 === $this->getHttpCode();
    }

    private function getHttpCode(): mixed
    {
        return $this->getInfo('http_code');
    }
}
