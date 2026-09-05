<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Recorder;

/**
 * Tells a RecorderHttpClient what to do. It is read on every request, so implementations may change
 * their answers over time (e.g. between two tests).
 */
interface RecorderConfigurationInterface
{
    public function getMode(): RecorderMode;

    /**
     * Absolute path of the HAR file to replay from or record into.
     */
    public function getHarFilePath(): string;

    /**
     * Whether a replay miss may fall back to a real request that gets recorded (explicit opt-in).
     */
    public function shouldRecordIfMissing(): bool;
}
