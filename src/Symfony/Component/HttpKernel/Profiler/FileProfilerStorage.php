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
     * File where profiler data are stored.
     *
     * @var string
     */
    private $storageFile;

    /**
     * Storage index file.
     *
     * @var string
     */
    private $indexFile;

    /**
     * @var \PharData
     */
    private $storage;

    /**
     * @var integer
     */
    private $storageFormat;

    /**
     * Constructs the file storage using a "dsn-like" path.
     *
     * Example : "file:/path/to/the/storage/folder"
     *
     * @param string $dsn The DSN
     *
     * @throws \RuntimeException When the dsn is not valid
     * @throws \RuntimeException When the storage folder could not be created
     */
    public function __construct($dsn)
    {
        if (0 !== strpos($dsn, 'file:')) {
            throw new \RuntimeException(sprintf('Please check your configuration. You are trying to use FileStorage with an invalid dsn "%s". The expected format is "file:/path/to/the/storage/folder".', $dsn));
        }
        $this->folder = substr($dsn, 5);

        if (!is_dir($this->folder)) {
            if (false == mkdir($this->folder)) {
                throw new \RuntimeException(sprintf('Could not create the profiler storage folder "%s".', $this->folder));
            }
        }

        $this->indexFile = realpath($this->folder).'/index.csv';
        $this->storageFile = realpath($this->folder).'/profile';
        $this->attachStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function find($ip, $url, $limit, $method, $start = null, $end = null)
    {
        if (!file_exists($this->indexFile)) {
            return array();
        }

        $file = fopen($this->indexFile, 'r');
        fseek($file, 0, SEEK_END);

        $result = array();

        for (;$limit > 0; $limit--) {
            $line = $this->readLineFromIndexFile($file);

            if (null === $line) {
                break;
            }

            list($csvToken, $csvIp, $csvMethod, $csvUrl, $csvTime, $csvParent) = str_getcsv($line);

            $csvTime = (int) $csvTime;

            if ($ip && false === strpos($csvIp, $ip) || $url && false === strpos($csvUrl, $url) || $method && false === strpos($csvMethod, $method)) {
                continue;
            }

            if (!empty($start) && $csvTime < $start) {
               continue;
            }

            if (!empty($end) && $csvTime > $end) {
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
        }

        fclose($file);

        return array_values($result);
    }

    /**
     * {@inheritdoc}
     */
    public function purge()
    {

        $storageFile = $this->getStorageFileName();
        if (is_file($storageFile)) {
            $iterator = new \RecursiveIteratorIterator($this->storage, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                if (is_file($file)) {
                    unlink($file);
                } else {
                    rmdir($file);
                }
            }
            \PharData::unlinkArchive($storageFile);
            $this->attachStorage();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read($token)
    {
        if (!$token || null === ($profile = $this->getProfileFromToken($token))) {
            return null;
        }

        return $this->createProfileFromData($token, $profile);
    }

    /**
     * {@inheritdoc}
     */
    public function write(Profile $profile)
    {
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

        $file = $this->getFilename($profile->getToken());
        $profileIndexed = isset($this->storage[$file]);

        try {
            $this->storage[$file] = serialize($data);
            if (\Phar::ZIP == $this->storageFormat) {
                $this->storage[$file]->compress(\Phar::GZ);
            }
        } catch (\PharException $e) {
            return false;
        }

        if (!$profileIndexed) {
            // Add to index
            if (false === $file = fopen($this->indexFile, 'a')) {
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
     * @param string $token The profile token
     *
     * @return string The profile filename
     */
    protected function getFilename($token)
    {
        return substr($token, -2, 2).''.substr($token, -4, 2).'/'.$token;
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
    protected function readLineFromIndexFile($file)
    {
        $line = '';
        $position = ftell($file);

        if (0 === $position) {
            return null;
        }

        while(true) {
            $chunkSize = min($position, 1024);
            $position -= $chunkSize;
            fseek($file, $position);

            if (0 === $chunkSize) {
                // bof reached
                break;
            }

            $buffer = fread($file, $chunkSize);

            if (false === ($upTo = strrpos($buffer, "\n"))) {
                $line = $buffer . $line;
                continue;
            }

            $position += $upTo;
            $line = substr($buffer, $upTo + 1) . $line;
            fseek($file, max(0, $position), SEEK_SET);

            if ('' !== $line) {
                break;
            }
        }

        return '' === $line ? null : $line;
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
            if (!$token || null === ($childProfile = $this->getProfileFromToken($token))) {
                continue;
            }

            $profile->addChild($this->createProfileFromData($token, $childProfile, $profile));
        }

        return $profile;
    }

    /**
     * Return the Profile associated with the given token if any.
     *
     * @param string $token
     *
     * @return Profile|null
     */
    protected function getProfileFromToken($token)
    {
        $file = $this->getFilename($token);
        return isset($this->storage[$file]) ? unserialize(file_get_contents($this->storage[$file])) : null;
    }

    /**
     * Attach to the PharData storage
     */
    protected function attachStorage()
    {
        $this->storageFormat = \PharData::canCompress(\Phar::GZ) ? \Phar::ZIP : \Phar::TAR;
        $this->storage = new \PharData(
            $this->getStorageFileName(),
            \PharData::CURRENT_AS_FILEINFO | \PharData::KEY_AS_FILENAME,
            'profile',
            $this->storageFormat
        );
    }

    /**
     * @return string The name of the storage file
     */
    protected function getStorageFileName()
    {
        return $this->storageFile.(\Phar::ZIP === $this->storageFormat ? '.zip' : '.tar');
    }
}
