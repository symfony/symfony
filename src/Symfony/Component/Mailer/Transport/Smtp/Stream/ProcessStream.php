<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Transport\Smtp\Stream;

use Symfony\Component\Mailer\Exception\TransportException;

/**
 * A stream supporting local processes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Chris Corbyn
 *
 * @internal
 */
final class ProcessStream extends AbstractStream
{
    private string $command;

    public function setCommand(string $command): void
    {
        $this->command = $command;
    }

    public function initialize(): void
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', '\\' === \DIRECTORY_SEPARATOR ? 'a' : 'w'],
        ];
        $pipes = [];
        $this->stream = proc_open($this->command, $descriptorSpec, $pipes);
        stream_set_blocking($pipes[2], false);
        if ($err = stream_get_contents($pipes[2])) {
            throw new TransportException('Process could not be started: '.$err);
        }
        $this->in = &$pipes[0];
        $this->out = &$pipes[1];
    }

    public function terminate(): void
    {
        if (null !== $this->stream) {
            fclose($this->in);
            fclose($this->out);
            proc_close($this->stream);
        }

        parent::terminate();
    }

    protected function getReadConnectionDescription(): string
    {
        return 'process '.$this->command;
    }
}
