<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Compressor;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
trait CompressorTrait
{
    private ?\Closure $method = null;
    private ?string $executable = null;
    /**
     * @var ?resource
     */
    private $streamContext;
    private ?string $unsupportedReason = null;

    private function initialize(): void
    {
        if ('' !== self::WRAPPER && \in_array(self::WRAPPER, stream_get_wrappers(), true)) {
            $this->method = $this->compressWithExtension(...);

            return;
        }

        if (!class_exists(Process::class)) {
            if ('' === self::WRAPPER) {
                $this->unsupportedReason = \sprintf('%s compression is unsupported. Run "composer require symfony/process" and install the "%s" command.', self::COMMAND, self::COMMAND);
            } else {
                $this->unsupportedReason = \sprintf('%s compression is unsupported. Install the "%s" extension or run "composer require symfony/process" and install the "%s" command.', self::COMMAND, self::PHP_EXTENSION, self::COMMAND);
            }

            return;
        }

        if (null === $this->executable) {
            $executableFinder = new ExecutableFinder();
            $this->executable = $executableFinder->find(self::COMMAND);

            if (null === $this->executable) {
                if (self::WRAPPER === '') {
                    $this->unsupportedReason = \sprintf('%s compression is unsupported. Install the "%s" command.', self::COMMAND, self::COMMAND);
                } else {
                    $this->unsupportedReason = \sprintf('%s compression is unsupported. Install the "%s" extension or the "%s" command.', self::COMMAND, self::PHP_EXTENSION, self::COMMAND);
                }

                return;
            }
        }

        $this->method = $this->compressWithBinary(...);
    }

    public function compress(string $path): void
    {
        if (null === $this->method && null === $this->unsupportedReason) {
            $this->initialize();
        }
        if (null !== $this->unsupportedReason) {
            throw new \RuntimeException($this->unsupportedReason);
        }

        ($this->method)($path);
    }

    public function getUnsupportedReason(): ?string
    {
        if (null !== $this->method) {
            return null;
        }

        $this->initialize();

        return $this->unsupportedReason;
    }

    abstract private function compressWithBinary(string $path): void;

    /**
     * @return resource
     */
    abstract private function createStreamContext();

    private function compressWithExtension(string $path): void
    {
        if (null === $this->streamContext) {
            $this->streamContext = $this->createStreamContext();
        }

        if (!copy($path, \sprintf('%s://%s.%s', self::WRAPPER, $path, self::FILE_EXTENSION), $this->streamContext)) {
            throw new \RuntimeException(\sprintf('The compressed file "%s.%s" could not be written.', $path, self::FILE_EXTENSION));
        }
    }
}
