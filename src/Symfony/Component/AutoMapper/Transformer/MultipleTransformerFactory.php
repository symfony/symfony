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
final class MultipleTransformerFactory implements TransformerFactoryInterface
{
    private $chainTransformerFactory;

    public function __construct(ChainTransformerFactory $chainTransformerFactory)
    {
        $this->chainTransformerFactory = $chainTransformerFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getTransformer(?array $sourcesTypes, ?array $targetTypes, MapperMetadataInterface $mapperMetadata): ?TransformerInterface
    {
        if (null === $sourcesTypes || 0 === \count($sourcesTypes)) {
            return null;
        }

        if (\count($sourcesTypes) > 1) {
            $transformer = new MultipleTransformer();

            foreach ($sourcesTypes as $sourceType) {
                $transformer->addTransformer($this->chainTransformerFactory->getTransformer([$sourceType], $targetTypes, $mapperMetadata), $sourceType);
            }

            return $transformer;
        }

        return null;
    }
}
