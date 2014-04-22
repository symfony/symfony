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
 */
class UnixPipes extends AbstractPipes
{
    /** @var bool */
    private $ttyMode;
    /** @var bool */
    private $ptyMode;
    /** @var bool */
    private $disabledOutput;

    public function __construct($ttyMode, $ptyMode, $disabledOutput)
    {
        $this->ttyMode = (bool) $ttyMode;
        $this->ptyMode = (bool) $ptyMode;
        $this->disabledOutput = (bool) $disabledOutput;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        $this->pipes = array();
    }

    /**
     * {@inheritdoc}
     */
    public function getDescriptors()
    {
        if ($disableOutput) {
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
            array('pipe', 'r'), // stdin
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
    public function read($blocking, $close = false)
    {
        if (empty($this->pipes)) {
            return array();
        }

        $read = array();

        $r = $this->pipes;
        $w = null;
        $e = null;

        // let's have a look if something changed in streams
        if (false === $n = @stream_select($r, $w, $e, 0, $blocking ? ceil(Process::TIMEOUT_PRECISION * 1E6) : 0)) {
            // if a system call has been interrupted, forget about it, let's try again
            // otherwise, an error occurred, let's reset pipes
            if (!$this->hasSystemCallBeenInterrupted()) {
                $this->pipes = array();
            }

            return $read;
        }

        // nothing has changed
        if (0 === $n) {
            return $read;
        }

        foreach ($r as $pipe) {
            $type = array_search($pipe, $this->pipes);

            $data = '';
            while ($dataread = fread($pipe, self::CHUNK_SIZE)) {
                $data .= $dataread;
            }

            if ($data) {
                $read[$type] = $data;
            }

            if (false === $data || (true === $close && feof($pipe) && '' === $data)) {
                fclose($this->pipes[$type]);
                unset($this->pipes[$type]);
            }
        }

        return $read;
    }

    /**
     * {@inheritdoc}
     */
    public function areOpen()
    {
        return (bool) $this->pipes;
    }

    /**
     * Creates a new UnixPipes instance
     *
     * @param Process $process
     *
     * @return UnixPipes
     */
    public static function create(Process $process)
    {
        return new static($process->isTty(), $process->isPty(), $process->isOutputDisabled());
    }
}
