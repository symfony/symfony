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

use Symfony\Component\Process\Process;

/**
 * UnixPipes implementation uses unix pipes as handles.
 *
 * @author Romain Neutron <imprec@gmail.com>
 *
 * @internal
 */
class UnixPipes extends AbstractPipes
{
    private $ttyMode;
    private $ptyMode;
    private $haveReadSupport;

    public function __construct(?bool $ttyMode, bool $ptyMode, $input, bool $haveReadSupport)
    {
        $this->ttyMode = $ttyMode;
        $this->ptyMode = $ptyMode;
        $this->haveReadSupport = $haveReadSupport;

        parent::__construct($input);
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * {@inheritdoc}
     */
    public function getDescriptors()
    {
        if (!$this->haveReadSupport) {
            $nullstream = fopen('/dev/null', 'c');

            return array(
                array('pipe', 'r'),
                $nullstream,
                $nullstream,
            );
        }

        if ($this->ttyMode) {
            return array(
                array('file', '/dev/tty', 'r'),
                array('file', '/dev/tty', 'w'),
                array('file', '/dev/tty', 'w'),
            );
        }

        if ($this->ptyMode && Process::isPtySupported()) {
            return array(
                array('pty'),
                array('pty'),
                array('pty'),
            );
        }

        return array(
            array('pipe', 'r'),
            array('pipe', 'w'), // stdout
            array('pipe', 'w'), // stderr
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFiles()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function readAndWrite($blocking, $close = false)
    {
        $this->unblock();
        $w = $this->write();

        $read = $e = array();
        $r = $this->pipes;
        unset($r[0]);

        // let's have a look if something changed in streams
        if (($r || $w) && false === @stream_select($r, $w, $e, 0, $blocking ? Process::TIMEOUT_PRECISION * 1E6 : 0)) {
            // if a system call has been interrupted, forget about it, let's try again
            // otherwise, an error occurred, let's reset pipes
            if (!$this->hasSystemCallBeenInterrupted()) {
                $this->pipes = array();
            }

            return $read;
        }

        foreach ($r as $pipe) {
            // prior PHP 5.4 the array passed to stream_select is modified and
            // lose key association, we have to find back the key
            $read[$type = array_search($pipe, $this->pipes, true)] = '';

            do {
                $data = fread($pipe, self::CHUNK_SIZE);
                $read[$type] .= $data;
            } while (isset($data[0]) && ($close || isset($data[self::CHUNK_SIZE - 1])));

            if (!isset($read[$type][0])) {
                unset($read[$type]);
            }

            if ($close && feof($pipe)) {
                fclose($pipe);
                unset($this->pipes[$type]);
            }
        }

        return $read;
    }

    /**
     * {@inheritdoc}
     */
    public function haveReadSupport()
    {
        return $this->haveReadSupport;
    }

    /**
     * {@inheritdoc}
     */
    public function areOpen()
    {
        return (bool) $this->pipes;
    }
}
