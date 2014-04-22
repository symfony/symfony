<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Pipes;

/**
 * @author Romain Neutron <imprec@gmail.com>
 */
abstract class AbstractPipes implements PipesInterface
{
    /** @var array */
    public $pipes = array();

    /**
     * {@inheritdoc}
     */
    public function unblock()
    {
        foreach ($this->pipes as $pipe) {
            stream_set_blocking($pipe, 0);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write($blocking, $stdin)
    {
        if (null === $stdin) {
            fclose($this->pipes[0]);
            unset($this->pipes[0]);

            return;
        }

        $writePipes = array($this->pipes[0]);
        unset($this->pipes[0]);
        $stdinLen = strlen($stdin);
        $stdinOffset = 0;

        while ($writePipes) {
            $r = null;
            $w = $writePipes;
            $e = null;

            if (false === $n = @stream_select($r, $w, $e, 0, $blocking ? ceil(Process::TIMEOUT_PRECISION * 1E6) : 0)) {
                // if a system call has been interrupted, forget about it, let's try again
                if ($this->hasSystemCallBeenInterrupted()) {
                    continue;
                }
                break;
            }

            // nothing has changed, let's wait until the process is ready
            if (0 === $n) {
                continue;
            }

            if ($w) {
                $written = fwrite($writePipes[0], (binary) substr($stdin, $stdinOffset), 8192);
                if (false !== $written) {
                    $stdinOffset += $written;
                }
                if ($stdinOffset >= $stdinLen) {
                    fclose($writePipes[0]);
                    $writePipes = null;
                }
            }
        }
    }

    /**
     * Returns true if a system call has been interrupted.
     *
     * @return bool
     */
    protected function hasSystemCallBeenInterrupted()
    {
        $lastError = error_get_last();

        // stream_select returns false when the `select` system call is interrupted by an incoming signal
        return isset($lastError['message']) && false !== stripos($lastError['message'], 'interrupted system call');
    }
}
