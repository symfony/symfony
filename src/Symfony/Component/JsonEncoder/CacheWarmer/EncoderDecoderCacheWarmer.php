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
use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

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
     * @param iterable<string> $encodableTypes
     */
    public function __construct(
        private iterable $encodableTypes,
        private ?TypeResolverInterface $stringTypeResolver,
        PropertyMetadataLoaderInterface $encodePropertyMetadataLoader,
        PropertyMetadataLoaderInterface $decodePropertyMetadataLoader,
        string $encodersDir,
        string $decodersDir,
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->encoderGenerator = new EncoderGenerator($encodePropertyMetadataLoader, $encodersDir);
        $this->decoderGenerator = new DecoderGenerator($decodePropertyMetadataLoader, $decodersDir);
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        foreach ($this->encodableTypes as $typeString) {
            if ($this->stringTypeResolver) {
                $type = $this->stringTypeResolver->resolve($typeString);
            } else {
                if (!class_exists($typeString)) {
                    throw new InvalidArgumentException(\sprintf('Unable to parse "%s" as string type resolver is not available. Try running "composer require phpstan/phpoc-parser".', $typeString));
                }

                $type = Type::object($typeString);
            }

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
