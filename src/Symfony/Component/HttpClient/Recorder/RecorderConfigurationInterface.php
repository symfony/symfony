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
 * Tells a RecorderHttpClient what to do and holds the state of the current recording session.
 *
 * It is read on every request, so implementations may change their answers over time (e.g. between two tests).
 * The state belongs to the configuration rather than to the client, so that it survives the client being
 * recreated, like when a kernel reboots between two requests.
 */
interface RecorderConfigurationInterface
{
    public function getMode(): RecorderMode;

    /**
     * Absolute path of the HAR file to replay from or record into.
     */
    public function getHarFilePath(): string;

    /**
     * Indexes of the HAR entries replayed or recorded during the session, so that repeated requests are served in order.
     *
     * @return int[]
     */
    public function getConsumedEntries(): array;

    public function consumeEntry(int $index): void;

    /**
     * Called when the HAR file has no response for a request, so that the miss can be reported even when the calling code catches the exception.
     */
    public function reportMiss(string $method, string $url): void;
}
