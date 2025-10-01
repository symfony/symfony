<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\CacheWarmer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\JsonStreamer\Exception\ExceptionInterface;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\Read\StreamReaderGenerator;
use Symfony\Component\JsonStreamer\Write\StreamWriterGenerator;
use Symfony\Component\TypeInfo\Type;

/**
 * Generates stream readers and stream writers PHP files.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class StreamerCacheWarmer implements CacheWarmerInterface
{
    private StreamWriterGenerator $streamWriterGenerator;
    private StreamReaderGenerator $streamReaderGenerator;

    /**
     * @param iterable<class-string, array{object: bool, list: bool}> $streamable
     */
    public function __construct(
        private iterable $streamable,
        PropertyMetadataLoaderInterface $streamWriterPropertyMetadataLoader,
        PropertyMetadataLoaderInterface $streamReaderPropertyMetadataLoader,
        string $streamWritersDir,
        string $streamReadersDir,
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->streamWriterGenerator = new StreamWriterGenerator($streamWriterPropertyMetadataLoader, $streamWritersDir);
        $this->streamReaderGenerator = new StreamReaderGenerator($streamReaderPropertyMetadataLoader, $streamReadersDir);
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        foreach ($this->streamable as $className => $streamable) {
            if ($streamable['object']) {
                $type = Type::object($className);

                $this->warmUpStreamWriter($type);
                $this->warmUpStreamReaders($type);
            }

            if ($streamable['list']) {
                $type = Type::list(Type::object($className));

                $this->warmUpStreamWriter($type);
                $this->warmUpStreamReaders($type);
            }
        }

        return [];
    }

    public function isOptional(): bool
    {
        return true;
    }

    private function warmUpStreamWriter(Type $type): void
    {
        try {
            $this->streamWriterGenerator->generate($type);
        } catch (ExceptionInterface $e) {
            $this->logger->debug('Cannot generate "json" stream writer for "{type}": {exception}', ['type' => (string) $type, 'exception' => $e]);
        }
    }

    private function warmUpStreamReaders(Type $type): void
    {
        try {
            $this->streamReaderGenerator->generate($type, false);
        } catch (ExceptionInterface $e) {
            $this->logger->debug('Cannot generate "json" string stream reader for "{type}": {exception}', ['type' => (string) $type, 'exception' => $e]);
        }

        try {
            $this->streamReaderGenerator->generate($type, true);
        } catch (ExceptionInterface $e) {
            $this->logger->debug('Cannot generate "json" resource stream reader for "{type}": {exception}', ['type' => (string) $type, 'exception' => $e]);
        }
    }
}
