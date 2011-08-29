<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

/**
 * Storage for profiler using files.
 *
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class FileProfilerStorage implements ProfilerStorageInterface
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
     */
    public function __construct($dsn)
    {
        if (0 !== strpos($dsn, 'file:')) {
            throw new \InvalidArgumentException("FileStorage DSN must start with file:");
        }
        $this->folder = substr($dsn, 5);

        if (!is_dir($this->folder)) {
            mkdir($this->folder);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function find($ip, $url, $limit)
    {
        $file = $this->getIndexFilename();

        if (!file_exists($file)) {
            return array();
        }

        $file = fopen($file, 'r');
        fseek($file, 0, SEEK_END);

        $result = array();

        while ($limit > 0) {
            $line = $this->readLineFromFile($file);

            if (false === $line) {
                break;
            }

            if ($line === "") {
                continue;
            }

            list($csvToken, $csvIp, $csvUrl, $csvTime, $csvParent) = str_getcsv($line);

            if ($ip && false === strpos($csvIp, $ip) || $url && false === strpos($csvUrl, $url)) {
                continue;
            }

            $row = array(
                'token'  => $csvToken,
                'ip'     => $csvIp,
                'url'    => $csvUrl,
                'time'   => $csvTime,
                'parent' => $csvParent
            );

            $result[] = $row;
            $limit--;
        }

        fclose($file);

        return $result;
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
    public function read($token)
    {
        $file = $this->getFilename($token);

        if (!file_exists($file)) {
            return null;
        }

        return unserialize(file_get_contents($file));
    }

    /**
     * {@inheritdoc}
     */
    public function write(Profile $profile)
    {
        $file = $this->getFilename($profile->getToken());
        $exists = file_exists($file);

        // Create directory
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // Store profile
        file_put_contents($file, serialize($profile));

        // Add to index
        $file = fopen($this->getIndexFilename(), 'a');
        fputcsv($file, array(
            $profile->getToken(),
            $profile->getIp(),
            $profile->getUrl(),
            $profile->getTime(),
            $profile->getParent() ? $profile->getParent()->getToken() : null
        ));
        fclose($file);

        return ! $exists;
    }

    /**
     * Gets filename to store data, associated to the token.
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
        return $this->folder.'/'.'index.csv';
    }

    /**
     * Reads a line in the file, ending with the current position.
     *
     * This function automatically skips the empty lines and do not include the line return in result value.
     *
     * @param resource $file The file resource, with the pointer placed at the end of the line to read
     *
     * @return mixed A string representating the line or FALSE if beginning of file is reached
     */
    protected function readLineFromFile($file)
    {
        if (ftell($file) === 0) {
            return false;
        }

        fseek($file, -1, SEEK_CUR);
        $str = '';

        while (true) {
            $char = fgetc($file);

            if ($char === "\n") {
                // Leave the file with cursor before the line return
                fseek($file, -1, SEEK_CUR);
                break;
            }

            $str = $char . $str;

            if (ftell($file) === 1) {
                // All file is read, so we move cursor to the position 0
                fseek($file, -1, SEEK_CUR);
                break;
            }

            fseek($file, -2, SEEK_CUR);
        }

        return $str === "" ? $this->readLineFromFile($file) : $str;
    }
}
