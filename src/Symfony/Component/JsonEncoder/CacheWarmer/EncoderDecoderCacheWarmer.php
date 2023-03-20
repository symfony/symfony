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

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\JsonEncoder\DataModel\Decode\DataModelBuilder as DecodeDataModelBuilder;
use Symfony\Component\JsonEncoder\DataModel\Encode\DataModelBuilder as EncodeDataModelBuilder;
use Symfony\Component\JsonEncoder\Decode\DecodeFrom;
use Symfony\Component\JsonEncoder\Decode\DecoderGenerator;
use Symfony\Component\JsonEncoder\Encode\EncodeAs;
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
final readonly class EncoderDecoderCacheWarmer implements CacheWarmerInterface
{
    private EncoderGenerator $encoderGenerator;
    private DecoderGenerator $decoderGenerator;

    /**
     * @param list<class-string> $encodableClassNames
     */
    public function __construct(
        private array $encodableClassNames,
        PropertyMetadataLoaderInterface $encodePropertyMetadataLoader,
        PropertyMetadataLoaderInterface $decodePropertyMetadataLoader,
        string $cacheDir,
        ?ContainerInterface $runtimeServices = null,
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->encoderGenerator = new EncoderGenerator(new EncodeDataModelBuilder($encodePropertyMetadataLoader, $runtimeServices), $cacheDir);
        $this->decoderGenerator = new DecoderGenerator(new DecodeDataModelBuilder($decodePropertyMetadataLoader, $runtimeServices), $cacheDir);
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        foreach ($this->encodableClassNames as $className) {
            $type = Type::object($className);

            $this->warmUpEncoders($type);
            $this->warmUpDecoders($type);
        }

        return [];
    }

    public function isOptional(): bool
    {
        return true;
    }

    private function warmUpEncoders(Type $type): void
    {
        foreach (EncodeAs::cases() as $encodeAs) {
            try {
                $this->encoderGenerator->generate($type, $encodeAs);
            } catch (ExceptionInterface $e) {
                $this->logger->debug('Cannot generate "json" {encodeAs} encoder for "{type}": {exception}', ['type' => (string) $type, 'encodeAs' => $encodeAs, 'exception' => $e]);
            }
        }
    }

    private function warmUpDecoders(Type $type): void
    {
        foreach (DecodeFrom::cases() as $decodeFrom) {
            try {
                $this->decoderGenerator->generate($type, $decodeFrom);
            } catch (ExceptionInterface $e) {
                $this->logger->debug('Cannot generate "json" {decodeFrom} decoder for "{type}": {exception}', ['type' => (string) $type, 'decodeFrom' => $decodeFrom, 'exception' => $e]);
            }
        }
    }
}
