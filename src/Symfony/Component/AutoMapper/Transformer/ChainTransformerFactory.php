<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Transformer;

use Symfony\Component\AutoMapper\MapperMetadataInterface;

/**
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class ChainTransformerFactory implements TransformerFactoryInterface
{
    /** @var TransformerFactoryInterface[] */
    private $factories = [];

    public function addTransformerFactory(TransformerFactoryInterface $transformerFactory)
    {
        $this->factories[] = $transformerFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getTransformer(?array $sourcesTypes, ?array $targetTypes, MapperMetadataInterface $mapperMetadata): ?TransformerInterface
    {
        foreach ($this->factories as $factory) {
            $transformer = $factory->getTransformer($sourcesTypes, $targetTypes, $mapperMetadata);

            if (null !== $transformer) {
                return $transformer;
            }
        }

        return null;
    }
}
