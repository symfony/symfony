<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Exception;

use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\Debug\Exception\FlattenException as LegacyFlattenException;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * FlattenException wraps a PHP Error or Exception to be able to serialize it.
 *
 * Basically, this class removes all objects from the trace.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FlattenException extends LegacyFlattenException
{
    private $message;
    private $code;
    private $previous;
    private $trace;
    private $traceAsString;
    private $class;
    private $statusCode;
    private $statusText;
    private $headers;
    private $file;
    private $line;
    private $asString;

    public static function create(\Exception $exception, $statusCode = null, array $headers = []): self
    {
        return static::createFromThrowable($exception, $statusCode, $headers);
    }

    public static function createFromThrowable(\Throwable $exception, int $statusCode = null, array $headers = []): self
    {
        $e = new static();
        $e->setMessage($exception->getMessage());
        $e->setCode($exception->getCode());

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $headers = array_merge($headers, $exception->getHeaders());
        } elseif ($exception instanceof RequestExceptionInterface) {
            $statusCode = 400;
        }

        if (null === $statusCode) {
            $statusCode = 500;
        }

        if (class_exists(Response::class) && isset(Response::$statusTexts[$statusCode])) {
            $statusText = Response::$statusTexts[$statusCode];
        } else {
            $statusText = 'Whoops, looks like something went wrong.';
        }

        $e->setStatusText($statusText);
        $e->setStatusCode($statusCode);
        $e->setHeaders($headers);
        $e->setTraceFromThrowable($exception);
        $e->setClass($exception instanceof FatalThrowableError ? $exception->getOriginalClassName() : \get_class($exception));
        $e->setFile($exception->getFile());
        $e->setLine($exception->getLine());

        $previous = $exception->getPrevious();

        if ($previous instanceof \Throwable) {
            $e->setPrevious(static::createFromThrowable($previous));
        }

        return $e;
    }

    public function toArray(): array
    {
        $exceptions = [];
        foreach (array_merge([$this], $this->getAllPrevious()) as $exception) {
            $exceptions[] = [
                'message' => $exception->getMessage(),
                'class' => $exception->getClass(),
                'trace' => $exception->getTrace(),
            ];
        }

        return $exceptions;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return $this
     */
    public function setStatusCode($code): self
    {
        $this->statusCode = $code;

        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return $this
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return $this
     */
    public function setClass($class): self
    {
        $this->class = 'c' === $class[0] && 0 === strpos($class, "class@anonymous\0") ? get_parent_class($class).'@anonymous' : $class;

        return $this;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * @return $this
     */
    public function setFile($file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * @return $this
     */
    public function setLine($line): self
    {
        $this->line = $line;

        return $this;
    }

    public function getStatusText(): string
    {
        return $this->statusText;
    }

    public function setStatusText(string $statusText): self
    {
        $this->statusText = $statusText;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return $this
     */
    public function setMessage($message): self
    {
        if (false !== strpos($message, "class@anonymous\0")) {
            $message = preg_replace_callback('/class@anonymous\x00.*?\.php0x?[0-9a-fA-F]++/', function ($m) {
                return class_exists($m[0], false) ? get_parent_class($m[0]).'@anonymous' : $m[0];
            }, $message);
        }

        $this->message = $message;

        return $this;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return $this
     */
    public function setCode($code): self
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return self|null
     */
    public function getPrevious()
    {
        return $this->previous;
    }

    /**
     * @return $this
     */
    final public function setPrevious(LegacyFlattenException $previous): self
    {
        $this->previous = $previous;

        return $this;
    }

    /**
     * @return self[]
     */
    public function getAllPrevious(): array
    {
        $exceptions = [];
        $e = $this;
        while ($e = $e->getPrevious()) {
            $exceptions[] = $e;
        }

        return $exceptions;
    }

    public function getTrace(): array
    {
        return $this->trace;
    }

    /**
     * @deprecated since 4.1, use {@see setTraceFromThrowable()} instead.
     */
    public function setTraceFromException(\Exception $exception)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.1, use "setTraceFromThrowable()" instead.', __METHOD__), E_USER_DEPRECATED);

        $this->setTraceFromThrowable($exception);
    }

    /**
     * @return $this
     */
    public function setTraceFromThrowable(\Throwable $throwable): self
    {
        $this->traceAsString = $throwable->getTraceAsString();

        return $this->setTrace($throwable->getTrace(), $throwable->getFile(), $throwable->getLine());
    }

    /**
     * @return $this
     */
    public function setTrace($trace, $file, $line): self
    {
        $this->trace = [];
        $this->trace[] = [
            'namespace' => '',
            'short_class' => '',
            'class' => '',
            'type' => '',
            'function' => '',
            'file' => $file,
            'line' => $line,
            'args' => [],
        ];
        foreach ($trace as $entry) {
            $class = '';
            $namespace = '';
            if (isset($entry['class'])) {
                $parts = explode('\\', $entry['class']);
                $class = array_pop($parts);
                $namespace = implode('\\', $parts);
            }

            $this->trace[] = [
                'namespace' => $namespace,
                'short_class' => $class,
                'class' => isset($entry['class']) ? $entry['class'] : '',
                'type' => isset($entry['type']) ? $entry['type'] : '',
                'function' => isset($entry['function']) ? $entry['function'] : null,
                'file' => isset($entry['file']) ? $entry['file'] : null,
                'line' => isset($entry['line']) ? $entry['line'] : null,
                'args' => isset($entry['args']) ? $this->flattenArgs($entry['args']) : [],
            ];
        }

        return $this;
    }

    private function flattenArgs(array $args, int $level = 0, int &$count = 0): array
    {
        $result = [];
        foreach ($args as $key => $value) {
            if (++$count > 1e4) {
                return ['array', '*SKIPPED over 10000 entries*'];
            }
            if ($value instanceof \__PHP_Incomplete_Class) {
                // is_object() returns false on PHP<=7.1
                $result[$key] = ['incomplete-object', $this->getClassNameFromIncomplete($value)];
            } elseif (\is_object($value)) {
                $result[$key] = ['object', \get_class($value)];
            } elseif (\is_array($value)) {
                if ($level > 10) {
                    $result[$key] = ['array', '*DEEP NESTED ARRAY*'];
                } else {
                    $result[$key] = ['array', $this->flattenArgs($value, $level + 1, $count)];
                }
            } elseif (null === $value) {
                $result[$key] = ['null', null];
            } elseif (\is_bool($value)) {
                $result[$key] = ['boolean', $value];
            } elseif (\is_int($value)) {
                $result[$key] = ['integer', $value];
            } elseif (\is_float($value)) {
                $result[$key] = ['float', $value];
            } elseif (\is_resource($value)) {
                $result[$key] = ['resource', get_resource_type($value)];
            } else {
                $result[$key] = ['string', (string) $value];
            }
        }

        return $result;
    }

    private function getClassNameFromIncomplete(\__PHP_Incomplete_Class $value): string
    {
        $array = new \ArrayObject($value);

        return $array['__PHP_Incomplete_Class_Name'];
    }

    public function getTraceAsString(): string
    {
        return $this->traceAsString;
    }

    /**
     * @return $this
     */
    public function setAsString(?string $asString): self
    {
        $this->asString = $asString;

        return $this;
    }

    public function getAsString(): string
    {
        if (null !== $this->asString) {
            return $this->asString;
        }

        $message = '';
        $next = false;

        foreach (array_reverse(array_merge([$this], $this->getAllPrevious())) as $exception) {
            if ($next) {
                $message .= 'Next ';
            } else {
                $next = true;
            }
            $message .= $exception->getClass();

            if ('' != $exception->getMessage()) {
                $message .= ': '.$exception->getMessage();
            }

            $message .= ' in '.$exception->getFile().':'.$exception->getLine().
                "\nStack trace:\n".$exception->getTraceAsString()."\n\n";
        }

        return rtrim($message);
    }
}
