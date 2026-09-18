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

final class RecorderConfiguration implements RecorderConfigurationInterface
{
    public function __construct(
        private readonly RecorderMode $mode = RecorderMode::Passthrough,
        private readonly string $harFilePath = '',
        private readonly bool $recordIfMissing = false,
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

    public function shouldRecordIfMissing(): bool
    {
        return $this->recordIfMissing;
    }
}
