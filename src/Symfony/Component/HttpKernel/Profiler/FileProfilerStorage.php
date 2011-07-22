<?php

namespace Symfony\Component\HttpKernel\Profiler;

/**
 * Storage for profiler using files
 *
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class FileProfilerStorage implements ProfilerStorageInterface
{
    protected $folder;

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

    public function find($ip, $url, $limit)
    {
        throw new \LogicException("Cannot use find-function with file storage");
    }

    public function purge()
    {
        $flags = \FilesystemIterator::SKIP_DOTS;
        $iterator = new \RecursiveDirectoryIterator($this->folder, $flags);

        foreach ($iterator as $file)
        {
            die('ON VA SUPPRIMER ' . $file);
        }
    }

    public function read($token)
    {
        return unserialize(file_get_contents($this->getFilename($token)));
    }

    public function write(Profile $profile)
    {
        file_put_contents($this->getFilename($profile->getToken()), serialize($profile));
    }

    protected function getFilename($token)
    {
        return $this->folder . '/' . $token;
    }
}
