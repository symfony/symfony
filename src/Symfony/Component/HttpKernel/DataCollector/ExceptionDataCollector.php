<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class ExceptionDataCollector extends DataCollector implements JsonAwareDataCollectorInterface
{
    private const MAX_TRACE_FRAMES = 50;

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        if (null !== $exception) {
            $this->data = [
                'exception' => FlattenException::createWithDataRepresentation($exception),
            ];
        }
    }

    public function hasException(): bool
    {
        return isset($this->data['exception']);
    }

    public function getException(): \Exception|FlattenException
    {
        return $this->data['exception'];
    }

    public function getMessage(): string
    {
        return $this->data['exception']->getMessage();
    }

    public function getCode(): int|string
    {
        return $this->data['exception']->getCode();
    }

    public function getStatusCode(): int
    {
        return $this->data['exception']->getStatusCode();
    }

    public function getTrace(): array
    {
        return $this->data['exception']->getTrace();
    }

    public function toJsonArray(bool $verbose = false): array
    {
        if (!$this->hasException()) {
            return ['has_exception' => false];
        }

        $exception = $this->getException();

        $data = [
            'has_exception' => true,
            'class' => $exception->getClass(),
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'status_code' => $this->getStatusCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        if ($verbose) {
            $trace = \array_slice($this->getTrace(), 0, self::MAX_TRACE_FRAMES);
            foreach ($trace as &$frame) {
                unset($frame['args']);
            }
            $data['trace'] = $trace;
        }

        return $data;
    }

    public function getName(): string
    {
        return 'exception';
    }
}
