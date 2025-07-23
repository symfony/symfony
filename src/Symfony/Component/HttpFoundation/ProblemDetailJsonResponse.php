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

use Symfony\Component\HttpFoundation\Exception\ProblemDetailJsonResponseException;

/**
 * Problem Detail Response represents a JSON response with a Problem Details object.
 *
 * @author Abdellah Ramadan <ramadanabdel24@gmail.com>
 */
class ProblemDetailJsonResponse extends Response
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
     * @throws ProblemDetailJsonResponseException
     */
    private function checkStatusCode(): void
    {
        if ($this->status < 400 || $this->status > 599) {
            throw new ProblemDetailJsonResponseException(\sprintf('The status code "%s" is not valid a valid HTTP Statuc code error.', $this->statusCode));
        }
    }

    private function setStatus(): void
    {
        $this->status = $this->status ?? 520;
        $this->statusCode = $this->status;
    }

    /**
     * @throws ProblemDetailJsonResponseException
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
                throw new ProblemDetailJsonResponseException("Invalid url type: $this->type.");
            }
        }

        $problemDetail = [
            'type' => $this->type ?? 'about:blank',
            'title' => $this->title ?? Response::$statusTexts[$this->statusCode] ?? 'Unknown Error',
            'detail' => $this->detail,
            'status' => $this->status,
            'instance' => $this->instance,
            ...$this->extensions,
        ];

        $problemDetail = array_filter($problemDetail, function ($value) {
            return null !== $value;
        });

        $content = json_encode($problemDetail, \JSON_FORCE_OBJECT | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);

        return $this->setContent($content);
    }
}
