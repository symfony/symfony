<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Extractor;

use Symfony\Component\Serializer\Context\ChildContextBuilderInterface;

trait DecorateChildContextBuilderTrait
{
    private $extractor;

    /**
     * {@inheritdoc}
     */
    public function createChildContextForAttribute(array $context, string $attribute): array
    {
        if ($this->extractor instanceof ChildContextBuilderInterface) {
            return $this->extractor->createChildContextForAttribute($context, $attribute);
        }

        return $context;
    }
}
