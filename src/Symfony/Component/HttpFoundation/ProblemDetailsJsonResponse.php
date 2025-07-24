<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\Exception\ProblemDetailsJsonResponseException;

/**
 * Represents a JSON response with a Problem Details object.
 *
 * @author Abdellah Ramadan <ramadanabdel24@gmail.com>
 */
class ProblemDetailsJsonResponse extends Response
{
    public function __construct(
        protected ?int $status = null,
        protected ?string $title = null,
        protected ?string $type = null,
        protected ?string $detail = null,
        protected ?string $instance = null,
        protected ?array $extensions = [],
    ) {
        parent::__construct();

        if ($this->title && null === $this->type) {
            $this->title = Response::$statusTexts[$this->status];
        }

        if (null === $this->title && null === $this->detail) {
            $this->title = Response::$statusTexts[$this->status];
        }

        $this->setProblemContent();
    }

    private function setHeaders(): void
    {
        $this->headers->set('Content-Type', 'application/problem+json');
    }

    /**
     * @throws ProblemDetailsJsonResponseException
     */
    private function checkStatusCode(): void
    {
        if ($this->status < 400 || $this->status > 599) {
            throw new ProblemDetailsJsonResponseException(\sprintf('The status code "%s" is not a valid HTTP Status Code error.', $this->statusCode));
        }
    }

    private function setStatus(): void
    {
        $this->status = $this->status ?? 520;
        $this->statusCode = $this->status;
    }

    /**
     * @throws ProblemDetailsJsonResponseException
     * @throws \JsonException
     */
    protected function setProblemContent(): string
    {
        $this->setStatus();
        $this->checkStatusCode();
        $this->setHeaders();

        if (null !== $this->type) {
            $scheme = parse_url($this->type, \PHP_URL_SCHEME);
            if (null === $scheme) {
                throw new ProblemDetailsJsonResponseException("Invalid url type: $this->type.");
            }
        }

        $problemDetails = [
            'type' => $this->type ?? 'about:blank',
            'title' => $this->title ?? Response::$statusTexts[$this->statusCode] ?? 'Unknown Error',
            'detail' => $this->detail,
            'status' => $this->status,
            'instance' => $this->instance,
            ...$this->extensions,
        ];

        $problemDetails = array_filter($problemDetails, function ($value) {
            return null !== $value;
        });

        $content = json_encode($problemDetails, \JSON_FORCE_OBJECT | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);

        return $this->setContent($content);
    }
}
