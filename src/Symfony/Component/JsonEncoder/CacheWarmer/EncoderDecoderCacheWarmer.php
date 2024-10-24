<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\CacheWarmer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\JsonEncoder\Decode\DecoderGenerator;
use Symfony\Component\JsonEncoder\Encode\EncoderGenerator;
use Symfony\Component\JsonEncoder\Exception\ExceptionInterface;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\TypeInfo\Type;

/**
 * Generates encoders and decoders PHP files.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class EncoderDecoderCacheWarmer implements CacheWarmerInterface
{
    private EncoderGenerator $encoderGenerator;
    private DecoderGenerator $decoderGenerator;

    /**
     * @param iterable<class-string> $encodableClassNames
     */
    public function __construct(
        private iterable $encodableClassNames,
        PropertyMetadataLoaderInterface $encodePropertyMetadataLoader,
        PropertyMetadataLoaderInterface $decodePropertyMetadataLoader,
        string $encodersDir,
        string $decodersDir,
        bool $forceEncodeChunks = false,
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->encoderGenerator = new EncoderGenerator($encodePropertyMetadataLoader, $encodersDir, $forceEncodeChunks);
        $this->decoderGenerator = new DecoderGenerator($decodePropertyMetadataLoader, $decodersDir);
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        foreach ($this->encodableClassNames as $className) {
            $type = Type::object($className);

            $this->warmUpEncoder($type);
            $this->warmUpDecoders($type);
        }

        return [];
    }

    public function isOptional(): bool
    {
        return true;
    }

    private function warmUpEncoder(Type $type): void
    {
        try {
            $this->encoderGenerator->generate($type);
        } catch (ExceptionInterface $e) {
            $this->logger->debug('Cannot generate "json" encoder for "{type}": {exception}', ['type' => (string) $type, 'exception' => $e]);
        }
    }

    private function warmUpDecoders(Type $type): void
    {
        try {
            $this->decoderGenerator->generate($type, decodeFromStream: false);
        } catch (ExceptionInterface $e) {
            $this->logger->debug('Cannot generate "json" decoder for "{type}": {exception}', ['type' => (string) $type, 'exception' => $e]);
        }

        try {
            $this->decoderGenerator->generate($type, decodeFromStream: true);
        } catch (ExceptionInterface $e) {
            $this->logger->debug('Cannot generate "json" streaming decoder for "{type}": {exception}', ['type' => (string) $type, 'exception' => $e]);
        }
    }
}
