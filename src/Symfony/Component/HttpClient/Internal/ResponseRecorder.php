<?php

/*
 *  This file is part of the Symfony package.
 *
 *  (c) Fabien Potencier <fabien@symfony.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Response\ResponseSerializer;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Stores and extract responses on the filesystem.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 *
 * @internal
 */
class ResponseRecorder
{
    /**
     * @var string
     */
    private $fixtureDir;

    /**
     * @var ResponseSerializer
     */
    private $serializer;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(string $fixtureDir, ResponseSerializer $serializer, ?Filesystem $filesystem = null)
    {
        $this->fixtureDir = realpath($fixtureDir);
        $this->serializer = $serializer;
        $this->filesystem = $filesystem ?? new Filesystem();

        if (false === $this->fixtureDir) {
            throw new \InvalidArgumentException(sprintf('Invalid fixture directory "%s" provided.', $fixtureDir));
        }
    }

    public function record(string $key, ResponseInterface $response): void
    {
        $this->filesystem->dumpFile("{$this->fixtureDir}/$key.txt", $this->serializer->serialize($response));
    }

    public function replay(string $key): ?array
    {
        $filename = "{$this->fixtureDir}/$key.txt";

        if (!is_file($filename)) {
            return null;
        }

        return $this->serializer->deserialize(file_get_contents($filename));
    }
}
