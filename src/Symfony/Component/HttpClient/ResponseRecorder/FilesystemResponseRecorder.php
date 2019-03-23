<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\ResponseRecorder;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseRecorderInterface;

/**
 * Saves responses in a defined directory.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class FilesystemResponseRecorder implements ResponseRecorderInterface
{
    /**
     * @var string
     */
    private $directory;

    /**
     * @var Filesystem
     */
    private $fs;

    public function __construct(string $directory, ?Filesystem $fs = null)
    {
        $this->fs = $fs ?? new Filesystem();

        if (!$this->fs->exists($directory)) {
            $this->fs->mkdir($directory);
        }

        $this->directory = realpath($directory);
    }

    public function record(string $name, ResponseInterface $response): void
    {
        $this->fs->dumpFile($this->getFilename($name), serialize(new MockResponse($response->getContent(), $response->getInfo())));
    }

    public function replay(string $name): ?ResponseInterface
    {
        $filename = $this->getFilename($name);

        if (!$this->fs->exists($filename)) {
            return null;
        }

        return unserialize(file_get_contents($filename));
    }

    private function getFilename(string $name): string
    {
        $sep = \DIRECTORY_SEPARATOR;

        return "{$this->directory}{$sep}{$name}.txt";
    }
}
