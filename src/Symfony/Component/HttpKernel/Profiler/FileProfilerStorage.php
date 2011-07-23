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
 * Storage for profiler using files
 *
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class FileProfilerStorage implements ProfilerStorageInterface
{
    protected $folder;

    /**
     * Construct the file storage using a "dsn-like" path :
     *
     * "file:/path/to/the/storage/folder"
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

        $file   = fopen($file, 'r');
        $result = array();

        while (!feof($file) && $limit > 0) {
            list($csvToken, $csvIp, $csvUrl, $csvTime, $csvParent) = fgetcsv($file);
            $row = array(
                'token'  => $csvToken,
                'ip'     => $csvIp,
                'url'    => $csvUrl,
                'time'   => $csvTime,
                'parent' => $csvParent
            );

            if ($ip && false === strpos($csvIp, $ip) || $url && false === strpos($csvUrl, $url)) {
                continue;
            }

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

        foreach ($iterator as $file)
        {
            unlink($file);
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

        if (file_exists($file)) {
            return false;
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

        return true;
    }

    /**
     * Get filename to store data, associated to the token
     *
     * @return string The profile filename
     */
    protected function getFilename($token)
    {
        return $this->folder . '/' . $token;
    }

    /**
     * Get the index filename
     *
     * @return string The index filename
     */
    protected function getIndexFilename()
    {
        return $this->folder . '/' . '_index.csv';
    }
}
