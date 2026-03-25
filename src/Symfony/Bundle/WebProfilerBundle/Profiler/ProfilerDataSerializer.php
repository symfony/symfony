<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Profiler;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * Converts profiler data into JSON-serializable arrays.
 *
 * @internal
 */
final class ProfilerDataSerializer
{
    private const SUPPORTED_COLLECTORS = ['request', 'exception', 'memory'];

    /**
     * Returns the list of collectors supported for JSON serialization.
     *
     * @return list<string>
     */
    public static function getSupportedCollectors(): array
    {
        return self::SUPPORTED_COLLECTORS;
    }

    /**
     * Returns the profile overview as a JSON-serializable array.
     *
     * @return array<string, mixed>
     */
    public static function buildOverview(Profile $profile): array
    {
        $statusCode = $profile->getStatusCode();

        $data = [
            'token' => $profile->getToken(),
            'method' => $profile->getMethod(),
            'url' => $profile->getUrl(),
            'status_code' => $statusCode,
            'ip' => $profile->getIp(),
            'time' => $profile->getTime(),
            'collectors' => array_keys($profile->getCollectors()),
        ];

        if (method_exists($profile, 'hasErrors')) {
            $data['has_errors'] = $profile->hasErrors();
        }

        if (null !== $statusCode) {
            $data['status_text'] = Response::$statusTexts[$statusCode] ?? '';
        }

        $metrics = [];

        if ($profile->hasCollector('time')) {
            /** @var TimeDataCollector $time */
            $time = $profile->getCollector('time');
            $metrics['duration_ms'] = $time->getDuration();
        }

        if ($profile->hasCollector('memory')) {
            /** @var MemoryDataCollector $memory */
            $memory = $profile->getCollector('memory');
            $metrics['memory_bytes'] = $memory->getMemory();
        }

        if ($profile->hasCollector('exception')) {
            /** @var ExceptionDataCollector $exception */
            $exception = $profile->getCollector('exception');
            $metrics['has_exception'] = $exception->hasException();
        }

        if ($profile->hasCollector('logger')) {
            /** @var LoggerDataCollector $logger */
            $logger = $profile->getCollector('logger');
            $metrics['error_count'] = $logger->countErrors();
        }

        if ($metrics) {
            $data['metrics'] = $metrics;
        }

        return $data;
    }

    /**
     * Returns the collector data as a JSON-serializable array.
     *
     * @return array<string, mixed>
     */
    public static function buildCollectorData(Profile $profile, string $collector): array
    {
        return match ($collector) {
            'memory' => self::collectMemoryData($profile),
            'exception' => self::collectExceptionData($profile),
            'request' => self::collectRequestData($profile),
            default => throw new \InvalidArgumentException(\sprintf('Collector "%s" is not supported for JSON serialization.', $collector)),
        };
    }

    private static function collectMemoryData(Profile $profile): array
    {
        /** @var MemoryDataCollector $collector */
        $collector = $profile->getCollector('memory');

        return [
            'memory' => $collector->getMemory(),
            'memory_limit' => $collector->getMemoryLimit(),
        ];
    }

    private static function collectExceptionData(Profile $profile): array
    {
        /** @var ExceptionDataCollector $collector */
        $collector = $profile->getCollector('exception');

        if (!$collector->hasException()) {
            return ['has_exception' => false];
        }

        $trace = $collector->getTrace();
        $trace = \array_slice($trace, 0, 50);
        foreach ($trace as &$frame) {
            unset($frame['args']);
        }

        return [
            'has_exception' => true,
            'message' => $collector->getMessage(),
            'code' => $collector->getCode(),
            'status_code' => $collector->getStatusCode(),
            'trace' => $trace,
        ];
    }

    private static function collectRequestData(Profile $profile): array
    {
        /** @var RequestDataCollector $collector */
        $collector = $profile->getCollector('request');

        // getController() returns array|string|Data — the JSON field may be a string
        // (e.g., "App\Controller::index") or an array (e.g., {"class", "method", "file", "line"})
        $controller = $collector->getController();
        if ($controller instanceof Data) {
            $controller = $controller->getValue(true) ?? '';
        }

        return [
            'method' => $collector->getMethod(),
            'path_info' => $collector->getPathInfo(),
            'route' => $collector->getRoute(),
            'route_params' => self::resolveDataValues($collector->getRouteParams()),
            'status_code' => $collector->getStatusCode(),
            'status_text' => $collector->getStatusText(),
            'content_type' => $collector->getContentType(),
            'format' => $collector->getFormat(),
            'locale' => $collector->getLocale(),
            'controller' => $controller,
            'request_query' => ProfilerJsonRedactor::redactByKeyPattern(self::resolveDataValues($collector->getRequestQuery()->all())),
            'request_request' => ProfilerJsonRedactor::redactByKeyPattern(self::resolveDataValues($collector->getRequestRequest()->all())),
            'request_headers' => ProfilerJsonRedactor::redactHeaders(self::resolveDataValues($collector->getRequestHeaders()->all())),
            'request_cookies' => ProfilerJsonRedactor::redactAll(self::resolveDataValues($collector->getRequestCookies()->all())),
            'request_server' => ProfilerJsonRedactor::redactByKeyPattern(self::resolveDataValues($collector->getRequestServer()->all())),
            'response_headers' => ProfilerJsonRedactor::redactHeaders(self::resolveDataValues($collector->getResponseHeaders()->all())),
            'response_cookies' => ProfilerJsonRedactor::redactAll(self::resolveDataValues($collector->getResponseCookies()->all())),
            'session_attributes' => ProfilerJsonRedactor::redactAll(self::resolveDataValues($collector->getSessionAttributes())),
            'dotenv_vars' => ProfilerJsonRedactor::redactByKeyPattern(self::resolveDataValues($collector->getDotenvVars()->all())),
        ];
    }

    /**
     * Recursively resolves Data objects to plain values suitable for JSON serialization.
     */
    private static function resolveDataValues(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof Data) {
                $data[$key] = $value->getValue(true);
            } elseif (\is_array($value)) {
                $data[$key] = self::resolveDataValues($value);
            }
        }

        return $data;
    }
}
