<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process;

use Symfony\Component\Process\Exception\RuntimeException;

/**
 * ProcessPipes manages descriptors and pipes for the use of proc_open.
 */
class ProcessPipes
{
    /** @var array */
    public $pipes = array();
    /** @var array */
    private $fileHandles = array();
    /** @var array */
    private $readBytes = array();
    /** @var Boolean */
    private $useFiles;

    public function __construct($useFiles = false)
    {
        $this->useFiles = (Boolean) $useFiles;

        // Fix for PHP bug #51800: reading from STDOUT pipe hangs forever on Windows if the output is too big.
        // Workaround for this problem is to use temporary files instead of pipes on Windows platform.
        //
        // Please note that this work around prevents hanging but
        // another issue occurs : In some race conditions, some data may be
        // lost or corrupted.
        //
        // @see https://bugs.php.net/bug.php?id=51800
        if ($this->useFiles) {
            $this->fileHandles = array(
                Process::STDOUT => tmpfile(),
                Process::STDERR => tmpfile(),
            );
            if (false === $this->fileHandles[Process::STDOUT]) {
                throw new RuntimeException('A temporary file could not be opened to write the process output to, verify that your TEMP environment variable is writable');
            }
            if (false === $this->fileHandles[Process::STDERR]) {
                throw new RuntimeException('A temporary file could not be opened to write the process output to, verify that your TEMP environment variable is writable');
            }
            $this->readBytes = array(
                Process::STDOUT => 0,
                Process::STDERR => 0,
            );
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Sets non-blocking mode on pipes.
     */
    public function unblock()
    {
        foreach ($this->pipes as $pipe) {
            stream_set_blocking($pipe, 0);
        }
    }

    /**
     * Closes file handles and pipes.
     */
    public function close()
    {
        foreach ($this->pipes as $offset => $pipe) {
            fclose($pipe);
        }

        foreach ($this->fileHandles as $offset => $handle) {
            fclose($handle);
        }
        $this->fileHandles = $this->pipes = array();
    }

    /**
     * Returns an array of descriptors for the use of proc_open.
     *
     * @return array
     */
    public function getDescriptors()
    {
        if ($this->useFiles) {
            return array(
                array('pipe', 'r'),
                $this->fileHandles[Process::STDOUT],
                $this->fileHandles[Process::STDERR],
            );
        }

        return array(
            array('pipe', 'r'), // stdin
            array('pipe', 'w'), // stdout
            array('pipe', 'w'), // stderr
        );
    }

    /**
     * Reads data in file handles and pipes.
     *
     * @param Boolean $blocking Whether to use blocking calls or not.
     *
     * @return array An array of read data indexed by their fd.
     */
    public function read($blocking)
    {
        return array_replace($this->readStreams($blocking), $this->readFileHandles());
    }

    /**
     * Writes stdin data.
     *
     * @param Boolean $blocking Whether to use blocking calls or not.
     * @param string  $stdin    The data to write.
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
     * Reads data in file handles.
     *
     * @return array An array of read data indexed by their fd.
     */
    private function readFileHandles()
    {
        $read = array();

        foreach ($this->fileHandles as $type => $fileHandle) {
            fseek($fileHandle, $this->readBytes[$type]);
            $data = '';
            while (!feof($fileHandle)) {
                $data .= fread($fileHandle, 8192);
            }
            if (0 < $length = strlen($data)) {
                $this->readBytes[$type] += $length;
                $read[$type] = $data;
            }
        }

        return $read;
    }

    /**
     * Reads data in file pipes streams.
     *
     * @param Boolean $blocking Whether to use blocking calls or not.
     *
     * @return array An array of read data indexed by their fd.
     */
    private function readStreams($blocking)
    {
        $read = array();

        $r = $this->pipes;
        $w = null;
        $e = null;

        // let's have a look if something changed in streams
        if (false === $n = @stream_select($r, $w, $e, 0, $blocking ? ceil(Process::TIMEOUT_PRECISION * 1E6) : 0)) {
            // if a system call has been interrupted, forget about it, let's try again
            // otherwise, an error occured, let's reset pipes
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
            $data = fread($pipe, 8192);

            if (strlen($data) > 0) {
                $read[$type] = $data;
            }
        }

        return $read;
    }

    /**
     * Returns true if a system call has been interrupted.
     *
     * @return Boolean
     */
    private function hasSystemCallBeenInterrupted()
    {
        $lastError = error_get_last();

        // stream_select returns false when the `select` system call is interrupted by an incoming signal
        return isset($lastError['message']) && false !== stripos($lastError['message'], 'interrupted system call');
    }
}
