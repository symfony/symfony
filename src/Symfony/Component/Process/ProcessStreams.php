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

/**
 * ProcessStreams is a thin wrapper around proc_* functions to ease
 * start independent PHP processes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author hakre <http://hakre.wordpress.com/>
 */
class ProcessStreams
{
    const STDIN           = 0;
    const STDOUT          = 1;
    const STDERR          = 2;
    const DESCRIPTOR_PIPE = 0;
    const DESCRIPTOR_TEMP = 1;
    const FOR_READ        = 'w';
    const FOR_WRITE       = 'r';
    /**
     * @var array
     */
    private $descriptors;
    /**
     * @var resource
     */
    private $process;
    /**
     * @var array
     */
    private $pipes;
    /**
     * @var array  [$descriptorNumber] => array(int $strLen, int $writeOffset, string $string)
     */
    private $writeStrings;
    /**
     * @var array [$descriptorNumber] => array(resource $resource, int $readOffset)
     */
    private $readFiles;

    /**
     * @param $streams
     *
     * @return ProcessStreams
     * @throws \InvalidArgumentException
     */
    public static function create($streams)
    {
        if (!is_array($streams)) {
            $streams = func_get_args();
            if (count($streams) === 1) {
                $streams = array_fill(0, 3, $streams[0]);
            }
        }

        $newStreams = new ProcessStreams();

        foreach ($streams as $descriptorNumber => $type) {
            switch ($type) {
                case 'pipe':
                    $newStreams->setDescriptorPipe($descriptorNumber);
                    break;
                case 'temp':
                    $newStreams->setDescriptorTemp($descriptorNumber);
                    break;
                default:
                    throw new \InvalidArgumentException(
                        sprintf(
                            'Unknown stream type %s for descriptor %s.',
                            var_export($type, true),
                            var_export($descriptorNumber, true)
                        )
                    );
            }
        }

        return $newStreams;
    }

    public function setDescriptorPipe($descriptorNumber, $mode = null)
    {
        $this->validateDescriptorNumber($descriptorNumber);
        $mode = $this->validateOptionalMode($mode, $descriptorNumber);

        $this->descriptors[$descriptorNumber] = array(
            'pipe',
            $mode,
            'type' => self::DESCRIPTOR_PIPE,
        );

        return $this;
    }

    private function validateOptionalMode($mode, $descriptorNumber)
    {
        if (null === $mode) {
            if ($descriptorNumber > 2) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Mode is not optional when using non-standard descriptor numbers %s.',
                        var_export($descriptorNumber, true)
                    )
                );
            }
            $mode = $descriptorNumber === 0 ? 'r' : 'w';
        }
        if (false === strpos('rw', $mode)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Mode %s not support, valid are \'r\' or \'w\' to pass the read or write end',
                    var_export($mode, true)
                )
            );
        }

        return $mode;
    }

    public function setDescriptorTemp($descriptorNumber, $mode = null)
    {
        $this->validateDescriptorNumber($descriptorNumber);
        $mode = $this->validateOptionalMode($mode, $descriptorNumber);

        $resource = tmpfile();

        if (!$resource) {
            throw new \RuntimeException(
                'A temporary file could not be opened to write the process output to, verify that your TEMP' .
                'environment variable is writable'
            );
        }
        $meta = stream_get_meta_data($resource);
        $path = $meta['uri'];

        $this->descriptors[$descriptorNumber] = array(
            'file',
            $path,
            $mode,
            'type'     => self::DESCRIPTOR_TEMP,
            'resource' => $resource,
        );
    }

    /**
     * @param string $cmd
     * @param string $cwd           (optional)
     * @param array  $env           (optional)
     * @param array  $other_options (optional)
     *
     * @throws \RuntimeException When process can't be launch or is stopped
     * @return resource
     */
    public function openProcess($cmd, $cwd = null, array $env = null, array $other_options = null)
    {
        $this->readFiles = array();
        foreach ($this->descriptors as $descriptorNumber => $descriptor) {
            if (!isset($descriptor['type'])
                || $descriptor['type'] !== self::DESCRIPTOR_TEMP
                || $descriptor[2] !== self::FOR_READ
            ) {
                continue;
            }

            $this->readFiles[$descriptorNumber] = array($descriptor['resource'], 0);
        }

        $process = proc_open($cmd, $this->getDescriptors(), $pipes, $cwd, $env, $other_options);

        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to launch a new process.');
        }

        // initialize pipes
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, 0);
        }

        $this->process = $process;
        $this->pipes   = $pipes;

        return $process;
    }

    public function getDescriptors()
    {
        return $this->descriptors;
    }

    /**
     * @param int $tv_sec
     * @param int $tv_uSec
     *
     * @return array($result, $read, $write, $except)
     */
    public function selectAll($tv_sec, $tv_uSec = 0)
    {
        $read   = $this->getPipes(self::FOR_READ);
        $write  = $this->getPipes(self::FOR_WRITE);
        $except = null;
        $result = $this->select($read, $write, $except, $tv_sec, $tv_uSec);

        $read   = $this->mapPipesToDescriptorNumberKeys($read);
        $write  = $this->mapPipesToDescriptorNumberKeys($write);
        $except = $this->mapPipesToDescriptorNumberKeys($except);

        return array($result, $read, $write, $except);
    }

    /**
     * @param $for (optional)
     *
     * @return array;
     */
    public function getPipes($for = null)
    {
        if ($for === null) {
            return $this->pipes;
        }

        $pipes = array();
        foreach ($this->pipes as $descriptorNumber => $pipe) {
            $mode = $this->getDescriptorMode($descriptorNumber);
            if ($mode === null) {
                continue;
            }
            if ($mode === ($for === self::FOR_WRITE ? 'r' : 'w')) {
                $pipes[$descriptorNumber] = $pipe;
            }
        }

        return $pipes;
    }

    /**
     * @param array|null $read
     * @param array|null $write
     * @param array|null $except
     * @param int        $tv_sec
     * @param int        $tv_uSec
     *
     * @return int|bool On success returns the number of stream resources contained in the modified arrays, which may
     *                   be zero if the timeout expires before anything interesting happens. On error FALSE is returned
     *                   (this can happen if the system call is interrupted by an incoming signal).
     */
    public function select(&$read, &$write, &$except, $tv_sec, $tv_uSec = 0)
    {
        $result = @stream_select($read, $write, $except, $tv_sec, $tv_uSec);

        return $result;
    }

    private function mapPipesToDescriptorNumberKeys($mixed)
    {
        if (!$mixed) {
            return $mixed;
        }

        $keys = array_map(array($this, 'getDescriptorNumberByResource'), $mixed);

        return array_combine($keys, $mixed);
    }

    /**
     * @param $descriptorNumber
     * @param $string - null will remove descriptor/pipe/file
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function prepareWrite($descriptorNumber, $string)
    {
        $mode = $this->getDescriptorMode($descriptorNumber);
        if ($mode !== 'r') {
            throw new \InvalidArgumentException(
                sprintf(
                    'Can not prepare a write string for descriptor %s with mode %s.',
                    var_export($descriptorNumber, true),
                    var_export($mode, true)
                )
            );
        }
        $descriptor = $this->descriptors[$descriptorNumber];
        $type       = $descriptor['type'];

        switch ($type) {
            case self::DESCRIPTOR_PIPE:
                $this->prepareWritePipe($descriptorNumber, $string);
                break;
            case self::DESCRIPTOR_TEMP:
                $this->prepareWriteTemp($descriptorNumber, $string);
                break;
        }

        return $this;
    }

    public function getDescriptorMode($descriptorNumber)
    {
        $this->validateDescriptorNumber($descriptorNumber);
        if (!isset($this->descriptors[$descriptorNumber])) {
            return null;
        }
        $descriptor = $this->descriptors[$descriptorNumber];

        return $descriptor[0] === 'pipe' ? $descriptor[1] : $descriptor[2];
    }

    private function validateDescriptorNumber($descriptorNumber)
    {
        if (!is_int($descriptorNumber) || $descriptorNumber < 0) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Process stream class must be either ProcessStreams::STDIN, ProcessStreams::STDOUT, ' .
                    'ProcessStreams::STDERR or any other valid file descriptor number. But %s given.',
                    var_export($descriptorNumber, 1)
                )
            );
        }
    }

    private function prepareWritePipe($descriptorNumber, $string)
    {
        if ($string !== null) {
            $this->writeStrings[$descriptorNumber] = array(strlen($string), 0, $string);

            return;
        }

        unset($this->writeStrings[$descriptorNumber]);

        if (isset($this->pipes[$descriptorNumber])) {
            fclose($this->pipes[$descriptorNumber]);
            unset($this->pipes[$descriptorNumber]);
        }

        unset($this->descriptors[$descriptorNumber]);
    }

    private function prepareWriteTemp($descriptorNumber, $string)
    {
        if ($string === null) {
            fclose($this->descriptors[$descriptorNumber]['resource']);
            unset($this->descriptors[$descriptorNumber]);

            return;
        }
        $written = file_put_contents($this->descriptors[$descriptorNumber][1], $string);
        if ($written === false || $written !== strlen($string)) {
            throw new \RuntimeException('Could not write string into temporary file successfully.');
        }
    }

    public function writePreparedString($descriptorNumber)
    {
        $this->validateDescriptorNumber($descriptorNumber);
        if (!isset($this->pipes[$descriptorNumber])) {
            throw new \InvalidArgumentException(sprintf('No pipe for descriptor %s.'));
        }
        $resource = $this->pipes[$descriptorNumber];

        if (!isset($this->writeStrings[$descriptorNumber])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'No string prepared (any longer) for descriptor %s.',
                    var_export($descriptorNumber, true)
                )
            );
        }

        $prepared = & $this->writeStrings[$descriptorNumber];

        $written = fwrite($resource, substr($prepared[2], $prepared[1]), 8192);

        if (false !== $written) {
            $prepared[1] += $written;
        }
        if ($prepared[1] >= $prepared[0]) {
            $this->closePipe($descriptorNumber);
        }
    }

    public function closePipe($descriptorNumber)
    {
        $this->validateDescriptorNumber($descriptorNumber);
        if (!isset($this->pipes[$descriptorNumber])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Descriptor %s has no pipe to close.',
                    var_export($descriptorNumber, true)
                )
            );
        }
        fclose($this->pipes[$descriptorNumber]);
        unset($this->pipes[$descriptorNumber]);
    }

    /**
     * @return bool has files that can be read
     */
    public function hasFiles()
    {
        return (bool) count($this->readFiles);
    }

    public function readFiles($bytesToRead, $callback, $closeOnEmptyAndEof = false)
    {
        foreach ($this->readFiles as $descriptorNumber => $unused) {
            $this->readFile($descriptorNumber, $bytesToRead, $callback, $closeOnEmptyAndEof);
        }
    }

    public function readFile($descriptorNumber, $bytesToRead, $callback = null, $closeOnEmptyAndEof = false)
    {
        if (!isset($this->readFiles[$descriptorNumber])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Can not read file from descriptor number %s.',
                    var_export($descriptorNumber, true)
                )
            );
        }

        $handle = & $this->readFiles[$descriptorNumber][0];
        $offset = & $this->readFiles[$descriptorNumber][1];

        fseek($handle, $offset);
        $data = fread($handle, $bytesToRead);

        if (strlen($data) > 0) {
            $offset += strlen($data);
            if ($callback) {
                call_user_func($callback, $descriptorNumber, $data);
            }
        }

        if (false === $data || ($closeOnEmptyAndEof && '' === $data && feof($handle))) {
            fclose($handle);
            unset($this->readFiles[$descriptorNumber]);
        }

        return $data;
    }

    public function readPipe($descriptorNumber, $bytesToRead, $callback = null)
    {
        if (!isset($this->pipes[$descriptorNumber]) || $descriptorNumber < 1 || $descriptorNumber > 2) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid descriptor number %s.',
                    var_export($descriptorNumber, true)
                )
            );
        }

        $pipe = $this->pipes[$descriptorNumber];
        $data = fread($pipe, $bytesToRead);
        if (strlen($data) > 0 && $callback) {
            call_user_func($callback, $descriptorNumber, $data);
        }
        if (false === $data || feof($pipe)) {
            $this->closePipe($descriptorNumber);
        }

        return $data;
    }

    /**
     * @return bool
     */
    public function hasOpenPipes()
    {
        return (bool) $this->pipes;
    }

    public function getDescriptorNumberByResource($resource)
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException('Not a resource.');
        }
        $key = array_search($resource, $this->pipes);

        return $key === false ? null : $key;
    }
}
