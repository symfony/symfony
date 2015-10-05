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
use Symfony\Component\Process\Exception\RuntimeException;

/**
 * WindowsPipes implementation uses temporary files as handles.
 *
 * @see https://bugs.php.net/bug.php?id=51800
 * @see https://bugs.php.net/bug.php?id=65650
 *
 * @author Romain Neutron <imprec@gmail.com>
 *
 * @internal
 */
class WindowsPipes extends AbstractPipes
{
    /** @var array */
    private $files = array();
    /** @var array */
    private $fileHandles = array();
    /** @var array */
    private $readBytes = array(
        Process::STDOUT => 0,
        Process::STDERR => 0,
    );
    /** @var bool */
    private $disableOutput;

    public function __construct($disableOutput, $input)
    {
        $this->disableOutput = (bool) $disableOutput;

        if (!$this->disableOutput) {
            // Fix for PHP bug #51800: reading from STDOUT pipe hangs forever on Windows if the output is too big.
            // Workaround for this problem is to use temporary files instead of pipes on Windows platform.
            //
            // @see https://bugs.php.net/bug.php?id=51800
            $this->files = array(
                Process::STDOUT => tempnam(sys_get_temp_dir(), 'out_sf_proc'),
                Process::STDERR => tempnam(sys_get_temp_dir(), 'err_sf_proc'),
            );
            foreach ($this->files as $offset => $file) {
                if (false === $file || false === $this->fileHandles[$offset] = fopen($file, 'rb')) {
                    throw new RuntimeException('A temporary file could not be opened to write the process output to, verify that your TEMP environment variable is writable');
                }
            }
        }

        if (is_resource($input)) {
            $this->input = $input;
        } else {
            $this->inputBuffer = $input;
        }
    }

    public function __destruct()
    {
        $this->close();
        $this->removeFiles();
    }

    /**
     * {@inheritdoc}
     */
    public function getDescriptors()
    {
        if ($this->disableOutput) {
            $nullstream = fopen('NUL', 'c');

            return array(
                array('pipe', 'r'),
                $nullstream,
                $nullstream,
            );
        }

        // We're not using pipe on Windows platform as it hangs (https://bugs.php.net/bug.php?id=51800)
        // We're not using file handles as it can produce corrupted output https://bugs.php.net/bug.php?id=65650
        // So we redirect output within the commandline and pass the nul device to the process
        return array(
            array('pipe', 'r'),
            array('file', 'NUL', 'w'),
            array('file', 'NUL', 'w'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * {@inheritdoc}
     */
    public function readAndWrite($blocking, $close = false)
    {
        $this->write($blocking, $close);

        $read = array();
        $fh = $this->fileHandles;
        foreach ($fh as $type => $fileHandle) {
            if (0 !== fseek($fileHandle, $this->readBytes[$type])) {
                continue;
            }
            $data = '';
            $dataread = null;
            while (!feof($fileHandle)) {
                if (false !== $dataread = fread($fileHandle, self::CHUNK_SIZE)) {
                    $data .= $dataread;
                }
            }
            if (0 < $length = strlen($data)) {
                $this->readBytes[$type] += $length;
                $read[$type] = $data;
            }

            if (false === $dataread || (true === $close && feof($fileHandle) && '' === $data)) {
                fclose($this->fileHandles[$type]);
                unset($this->fileHandles[$type]);
            }
        }

        return $read;
    }

    /**
     * {@inheritdoc}
     */
    public function areOpen()
    {
        return (bool) $this->pipes && (bool) $this->fileHandles;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        parent::close();
        foreach ($this->fileHandles as $handle) {
            fclose($handle);
        }
        $this->fileHandles = array();
    }

    /**
     * Creates a new WindowsPipes instance.
     *
     * @param Process $process The process
     * @param $input
     *
     * @return WindowsPipes
     */
    public static function create(Process $process, $input)
    {
        return new static($process->isOutputDisabled(), $input);
    }

    /**
     * Removes temporary files.
     */
    private function removeFiles()
    {
        foreach ($this->files as $filename) {
            if (file_exists($filename)) {
                @unlink($filename);
            }
        }
        $this->files = array();
    }

    /**
     * Writes input to stdin.
     *
     * @param bool $blocking
     * @param bool $close
     */
    private function write($blocking, $close)
    {
        if (empty($this->pipes)) {
            return;
        }

        $this->unblock();

        $r = null !== $this->input ? array('input' => $this->input) : null;
        $w = isset($this->pipes[0]) ? array($this->pipes[0]) : null;
        $e = null;

        // let's have a look if something changed in streams
        if (false === $n = @stream_select($r, $w, $e, 0, $blocking ? Process::TIMEOUT_PRECISION * 1E6 : 0)) {
            // if a system call has been interrupted, forget about it, let's try again
            // otherwise, an error occurred, let's reset pipes
            if (!$this->hasSystemCallBeenInterrupted()) {
                $this->pipes = array();
            }

            return;
        }

        // nothing has changed
        if (0 === $n) {
            return;
        }

        if (null !== $w && 0 < count($r)) {
            $data = '';
            while ($dataread = fread($r['input'], self::CHUNK_SIZE)) {
                $data .= $dataread;
            }

            $this->inputBuffer .= $data;

            if (false === $data || (true === $close && feof($r['input']) && '' === $data)) {
                // no more data to read on input resource
                // use an empty buffer in the next reads
                $this->input = null;
            }
        }

        if (null !== $w && 0 < count($w)) {
            while (strlen($this->inputBuffer)) {
                $written = fwrite($w[0], $this->inputBuffer, 2 << 18);
                if ($written > 0) {
                    $this->inputBuffer = (string) substr($this->inputBuffer, $written);
                } else {
                    break;
                }
            }
        }

        // no input to read on resource, buffer is empty and stdin still open
        if ('' === $this->inputBuffer && null === $this->input && isset($this->pipes[0])) {
            fclose($this->pipes[0]);
            unset($this->pipes[0]);
        }
    }
}
