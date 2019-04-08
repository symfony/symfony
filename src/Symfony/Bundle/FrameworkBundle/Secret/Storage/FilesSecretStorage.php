<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Secret\Storage;

use Symfony\Bundle\FrameworkBundle\Exception\SecretNotFoundException;
use Symfony\Bundle\FrameworkBundle\Secret\Encoder\EncoderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @author Tobias Schultze <http://tobion.de>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class FilesSecretStorage implements MutableSecretStorageInterface
{
    private const FILE_SUFFIX = '.bin';

    private $secretsFolder;
    private $encoder;
    private $filesystem;

    public function __construct(string $secretsFolder, EncoderInterface $encoder)
    {
        $this->secretsFolder = rtrim($secretsFolder, '\\/');
        $this->encoder = $encoder;
        $this->filesystem = new Filesystem();
    }

    /**
     * {@inheritdoc}
     */
    public function listSecrets(bool $reveal = false): iterable
    {
        if (!$this->filesystem->exists($this->secretsFolder)) {
            return;
        }

        foreach ((new Finder())->in($this->secretsFolder)->depth(0)->name('*'.self::FILE_SUFFIX)->files() as $file) {
            $name = $file->getBasename(self::FILE_SUFFIX);
            yield $name => $reveal ? $this->getSecret($name) : null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSecret(string $name): string
    {
        $filePath = $this->getFilePath($name);

        if (!is_file($filePath) || false === $content = file_get_contents($filePath)) {
            throw new SecretNotFoundException($name);
        }

        return $this->encoder->decrypt($content);
    }

    /**
     * {@inheritdoc}
     */
    public function setSecret(string $name, string $secret): void
    {
        $this->filesystem->dumpFile($this->getFilePath($name), $this->encoder->encrypt($secret));
    }

    /**
     * {@inheritdoc}
     */
    public function removeSecret(string $name): void
    {
        $filePath = $this->getFilePath($name);

        if (!is_file($filePath)) {
            throw new SecretNotFoundException($name);
        }

        $this->filesystem->remove($this->getFilePath($name));
    }

    private function getFilePath(string $name): string
    {
        if (!preg_match('/^[\w\-]++$/', $name)) {
            throw new \InvalidArgumentException(sprintf('The secret name "%s" is not valid.', $name));
        }

        return $this->secretsFolder.\DIRECTORY_SEPARATOR.$name.self::FILE_SUFFIX;
    }
}
