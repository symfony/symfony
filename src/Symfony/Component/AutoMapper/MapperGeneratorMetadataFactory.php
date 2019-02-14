<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper;

use Symfony\Component\AutoMapper\Extractor\FromSourceMappingExtractor;
use Symfony\Component\AutoMapper\Extractor\FromTargetMappingExtractor;
use Symfony\Component\AutoMapper\Extractor\SourceTargetMappingExtractor;

/**
 * Metadata factory, used to autoregistering new mapping without creating them.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class MapperGeneratorMetadataFactory
{
    private $sourceTargetPropertiesMappingExtractor;
    private $fromSourcePropertiesMappingExtractor;
    private $fromTargetPropertiesMappingExtractor;
    private $classPrefix;

    public function __construct(
        SourceTargetMappingExtractor $sourceTargetPropertiesMappingExtractor,
        FromSourceMappingExtractor $fromSourcePropertiesMappingExtractor,
        FromTargetMappingExtractor $fromTargetPropertiesMappingExtractor,
        string $classPrefix = 'Mapper_'
    ) {
        $this->sourceTargetPropertiesMappingExtractor = $sourceTargetPropertiesMappingExtractor;
        $this->fromSourcePropertiesMappingExtractor = $fromSourcePropertiesMappingExtractor;
        $this->fromTargetPropertiesMappingExtractor = $fromTargetPropertiesMappingExtractor;
        $this->classPrefix = $classPrefix;
    }

    /**
     * Create metadata for a source and target.
     */
    public function create(MapperGeneratorMetadataRegistryInterface $autoMapperRegister, string $source, string $target): MapperGeneratorMetadataInterface
    {
        $extractor = $this->sourceTargetPropertiesMappingExtractor;

        if ('array' === $source || 'stdClass' === $source) {
            $extractor = $this->fromTargetPropertiesMappingExtractor;
        }

        if ('array' === $target || 'stdClass' === $target) {
            $extractor = $this->fromSourcePropertiesMappingExtractor;
        }

        return new MapperMetadata($autoMapperRegister, $extractor, $source, $target, $this->classPrefix);
    }
}
