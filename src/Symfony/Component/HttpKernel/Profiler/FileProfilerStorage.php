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
            throw new \RuntimeException(sprintf('Please check your configuration. You are trying to use FileStorage with an invalid dsn "%s". The expected format is "file:/path/to/the/storage/folder".', $dsn));
        }
        $this->folder = substr($dsn, 5);

        if (!is_dir($this->folder)) {
            mkdir($this->folder);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function find($ip, $url, $limit, $method)
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

            if ($line === '') {
                continue;
            }

            list($csvToken, $csvIp, $csvMethod, $csvUrl, $csvTime, $csvParent) = str_getcsv($line);

            if ($ip && false === strpos($csvIp, $ip) || $url && false === strpos($csvUrl, $url) || $method && false === strpos($csvMethod, $method)) {
                continue;
            }

            $result[$csvToken] = array(
                'token'  => $csvToken,
                'ip'     => $csvIp,
                'method' => $csvMethod,
                'url'    => $csvUrl,
                'time'   => $csvTime,
                'parent' => $csvParent,
            );
            --$limit;
        }

        fclose($file);

        return array_values($result);
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
        if (!$token || !file_exists($file = $this->getFilename($token))) {
            return null;
        }

        return $this->createProfileFromData($token, unserialize(file_get_contents($file)));
    }

    /**
     * {@inheritdoc}
     */
    public function write(Profile $profile)
    {
        $file = $this->getFilename($profile->getToken());

        $profileIndexed = is_file($file);
        if (!$profileIndexed) {
            // Create directory
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }

        // Store profile
        $data = array(
            'token'    => $profile->getToken(),
            'parent'   => $profile->getParentToken(),
            'children' => array_map(function ($p) { return $p->getToken(); }, $profile->getChildren()),
            'data'     => $profile->getCollectors(),
            'ip'       => $profile->getIp(),
            'method'   => $profile->getMethod(),
            'url'      => $profile->getUrl(),
            'time'     => $profile->getTime(),
        );

        if (false === file_put_contents($file, serialize($data))) {
            return false;
        }

        if (!$profileIndexed) {
            // Add to index
            if (false === $file = fopen($this->getIndexFilename(), 'a')) {
                return false;
            }

            fputcsv($file, array(
                $profile->getToken(),
                $profile->getIp(),
                $profile->getMethod(),
                $profile->getUrl(),
                $profile->getTime(),
                $profile->getParentToken(),
            ));
            fclose($file);
        }

        return true;
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
        return $this->folder.'/index.csv';
    }

    /**
     * Reads a line in the file, ending with the current position.
     *
     * This function automatically skips the empty lines and do not include the line return in result value.
     *
     * @param resource $file The file resource, with the pointer placed at the end of the line to read
     *
     * @return mixed A string representing the line or FALSE if beginning of file is reached
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

            $str = $char.$str;

            if (ftell($file) === 1) {
                // All file is read, so we move cursor to the position 0
                fseek($file, -1, SEEK_CUR);
                break;
            }

            fseek($file, -2, SEEK_CUR);
        }

        return $str === '' ? $this->readLineFromFile($file) : $str;
    }

    protected function createProfileFromData($token, $data, $parent = null)
    {
        $profile = new Profile($token);
        $profile->setIp($data['ip']);
        $profile->setMethod($data['method']);
        $profile->setUrl($data['url']);
        $profile->setTime($data['time']);
        $profile->setCollectors($data['data']);

        if (!$parent && $data['parent']) {
            $parent = $this->read($data['parent']);
        }

        if ($parent) {
            $profile->setParent($parent);
        }

        foreach ($data['children'] as $token) {
            if (!$token || !file_exists($file = $this->getFilename($token))) {
                continue;
            }

            $profile->addChild($this->createProfileFromData($token, unserialize(file_get_contents($file)), $profile));
        }

        return $profile;
    }
}
