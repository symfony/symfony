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
    private static bool $recordIfMissing = false;

    public static function configure(RecorderMode $mode, string $harFilePath, bool $recordIfMissing = false): void
    {
        self::$mode = $mode;
        self::$harFilePath = $harFilePath;
        self::$recordIfMissing = $recordIfMissing;
    }

    public static function reset(): void
    {
        self::$mode = RecorderMode::Passthrough;
        self::$harFilePath = '';
        self::$recordIfMissing = false;
    }

    public function getMode(): RecorderMode
    {
        return self::$mode;
    }

    public function getHarFilePath(): string
    {
        return self::$harFilePath;
    }

    public function shouldRecordIfMissing(): bool
    {
        return self::$recordIfMissing;
    }
}
