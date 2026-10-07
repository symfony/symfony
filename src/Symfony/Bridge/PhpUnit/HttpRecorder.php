<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

use Symfony\Component\HttpClient\Recorder\RecorderConfigurationInterface;
use Symfony\Component\HttpClient\Recorder\RecorderMode;

/**
 * Process-wide recorder configuration driven by #[UseRecord], read by RecorderHttpClient at each request.
 */
final class HttpRecorder implements RecorderConfigurationInterface
{
    private static RecorderMode $mode = RecorderMode::Passthrough;
    private static string $harFilePath = '';
    private static array $consumedEntries = [];
    private static array $misses = [];
    private static array $recordedEntries = [];

    /**
     * Starts a recording session, which ends with the next call to this method or to reset().
     *
     * In record mode, a session continues the ones recorded earlier in the process into the same file, so that the tests sharing a file all add their entries to it.
     */
    public static function configure(RecorderMode $mode, string $harFilePath): void
    {
        self::$mode = $mode;
        self::$harFilePath = $harFilePath;
        self::$consumedEntries = RecorderMode::Record === $mode ? self::$recordedEntries[$harFilePath] ?? [] : [];
        self::$misses = [];
    }

    public static function reset(): void
    {
        self::$mode = RecorderMode::Passthrough;
        self::$harFilePath = '';
        self::$consumedEntries = [];
        self::$misses = [];
    }

    /**
     * @return list<string> The requests of the current session that had no recorded response, as "METHOD url"
     *
     * @internal
     */
    public static function getMisses(): array
    {
        return self::$misses;
    }

    public function getMode(): RecorderMode
    {
        return self::$mode;
    }

    public function getHarFilePath(): string
    {
        return self::$harFilePath;
    }

    public function getConsumedEntries(): array
    {
        return self::$consumedEntries;
    }

    public function consumeEntry(int $index): void
    {
        self::$consumedEntries[] = $index;

        if (RecorderMode::Record === self::$mode) {
            self::$recordedEntries[self::$harFilePath][] = $index;
        }
    }

    public function reportMiss(string $method, string $url): void
    {
        self::$misses[] = $method.' '.$url;
    }
}
