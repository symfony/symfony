<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\SignalRegistry;

final class SignalRegistry
{
    private $registeredSignals = [];

    private $handlingSignals = [];

    public function __construct()
    {
        pcntl_async_signals(true);
    }

    public function register(int $signal, callable $signalHandler): void
    {
        if (!isset($this->registeredSignals[$signal])) {
            $previousCallback = pcntl_signal_get_handler($signal);

            if (\is_callable($previousCallback)) {
                $this->registeredSignals[$signal][] = $previousCallback;
            }
        }

        $this->registeredSignals[$signal][] = $signalHandler;
        pcntl_signal($signal, [$this, 'handle']);
    }

    /**
     * @internal
     */
    public function handle(int $signal): void
    {
        foreach ($this->registeredSignals[$signal] as $signalHandler) {
            $signalHandler($signal);
        }
    }

    public function addHandlingSignals(int ...$signals): void
    {
        foreach ($signals as $signal) {
            $this->handlingSignals[$signal] = true;
        }
    }

    public function getHandlingSignals(): array
    {
        return array_keys($this->handlingSignals);
    }
}
