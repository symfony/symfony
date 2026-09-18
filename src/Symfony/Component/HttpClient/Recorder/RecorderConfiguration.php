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
 * A recording session on one HAR file, for the whole lifetime of the instance.
 */
final class RecorderConfiguration implements RecorderConfigurationInterface
{
    private array $consumedEntries = [];

    public function __construct(
        private readonly RecorderMode $mode = RecorderMode::Passthrough,
        private readonly string $harFilePath = '',
    ) {
    }

    public function getMode(): RecorderMode
    {
        return $this->mode;
    }

    public function getHarFilePath(): string
    {
        return $this->harFilePath;
    }

    public function getConsumedEntries(): array
    {
        return $this->consumedEntries;
    }

    public function consumeEntry(int $index): void
    {
        $this->consumedEntries[] = $index;
    }

    public function reportMiss(string $method, string $url): void
    {
    }
}
