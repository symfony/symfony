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
        if (null === $sourcesTypes || \count($sourcesTypes) <= 1) {
            return null;
        }

        $transformers = [];

        foreach ($sourcesTypes as $sourceType) {
            $transformer = $this->chainTransformerFactory->getTransformer([$sourceType], $targetTypes, $mapperMetadata);

            if (null !== $transformer) {
                $transformers[] = [
                    'transformer' => $transformer,
                    'type' => $sourceType
                ];
            }
        }

        if (\count($transformers) > 1) {
            return new MultipleTransformer($transformers);
        }

        if (\count($transformers) === 1) {
            return $transformers[0]['transformer'];
        }

        return null;
    }
}
