<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\Storage;

/**
 * Storage for profiler using files.
 *
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class FileProfilerStorage extends AbstractProfilerStorage
{
    /**
     * Folder where profiler data are stored.
     *
     * @var string
     */
    private $folder;

    /**
     * Constructs the file storage using a "dsn-like" path.
     *
     * Example : "file:/path/to/the/storage/folder"
     *
     * @param string $dsn The DSN
     *
     * @throws \RuntimeException
     */
    public function __construct($dsn)
    {
        if (0 !== strpos($dsn, 'file:')) {
            throw new \RuntimeException(sprintf('Please check your configuration. You are trying to use FileStorage with an invalid dsn "%s". The expected format is "file:/path/to/the/storage/folder".', $dsn));
        }
        $this->folder = substr($dsn, 5);

        if (!is_dir($this->folder)) {
            mkdir($this->folder, 0777, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(array $criteria, $limit, $start = null, $end = null)
    {
        $file = $this->getIndexFilename();

        if (!file_exists($file)) {
            return array();
        }

        $file = fopen($file, 'r');
        fseek($file, 0, SEEK_END);

        $result = array();
        while (count($result) < $limit && $line = $this->readLineFromFile($file)) {
            $values = json_decode($line, true);
            $time = (int) $values['time'];

            if (!empty($start) && $time < $start) {
                continue;
            }

            if (!empty($end) && $time > $end) {
                continue;
            }

            if (!$this->validateCriteria($values, $criteria)) {
                continue;
            }

            $result[$values['token']] = $values;
        }

        fclose($file);

        return array_values($result);
    }

    private function validateCriteria($values, $criteria)
    {
        foreach ($criteria as $key => $value) {
            if (null !== $value && false === strpos($values[$key], $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function purge()
    {
        $flags = \FilesystemIterator::SKIP_DOTS;
        $iterator = new \RecursiveDirectoryIterator($this->folder, $flags);
        $iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $file) {
            if (is_file($file)) {
                unlink($file);
            } else {
                rmdir($file);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function doRead($token)
    {
        if (!$token || !file_exists($file = $this->getFilename($token))) {
            return;
        }

        return unserialize(file_get_contents($file));
    }

    /**
     * {@inheritdoc}
     */
    public function doWrite($token, array $data, array $indexedData)
    {
        $file = $this->getFilename($token);

        $profileIndexed = is_file($file);
        if (!$profileIndexed) {
            // Create directory
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }

        if (false === file_put_contents($file, serialize($data))) {
            return false;
        }

        if (!$profileIndexed) {
            // Add to index
            if (false === $file = fopen($this->getIndexFilename(), 'a')) {
                return false;
            }

            fputs($file, json_encode($indexedData)."\n");
            fclose($file);
        }

        return true;
    }

    /**
     * Gets filename to store data, associated to the token.
     *
     * @param string $token
     *
     * @return string The profile filename
     */
    protected function getFilename($token)
    {
        // Uses 4 last characters, because first are mostly the same.
        $folderA = substr($token, -2, 2);
        $folderB = substr($token, -4, 2);

        return $this->folder.'/'.$folderA.'/'.$folderB.'/'.$token;
    }

    /**
     * Gets the index filename.
     *
     * @return string The index filename
     */
    protected function getIndexFilename()
    {
        return $this->folder.'/index.csv';
    }

    /**
     * Reads a line in the file, backward.
     *
     * This function automatically skips the empty lines and do not include the line return in result value.
     *
     * @param resource $file The file resource, with the pointer placed at the end of the line to read
     *
     * @return mixed A string representing the line or null if beginning of file is reached
     */
    protected function readLineFromFile($file)
    {
        $line = '';
        $position = ftell($file);

        if (0 === $position) {
            return;
        }

        while (true) {
            $chunkSize = min($position, 1024);
            $position -= $chunkSize;
            fseek($file, $position);

            if (0 === $chunkSize) {
                // bof reached
                break;
            }

            $buffer = fread($file, $chunkSize);

            if (false === ($upTo = strrpos($buffer, "\n"))) {
                $line = $buffer.$line;
                continue;
            }

            $position += $upTo;
            $line = substr($buffer, $upTo + 1).$line;
            fseek($file, max(0, $position), SEEK_SET);

            if ('' !== $line) {
                break;
            }
        }

        return '' === $line ? null : $line;
    }
}
