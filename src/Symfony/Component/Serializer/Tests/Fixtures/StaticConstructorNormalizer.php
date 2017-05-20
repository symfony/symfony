<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @author Guilhem N. <egetick@gmail.com>
 */
class StaticConstructorNormalizer extends ObjectNormalizer
{
    /**
     * {@inheritdoc}
     */
    protected function getConstructor(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes)
    {
        if (is_a($class, StaticConstructorDummy::class, true)) {
            return new \ReflectionMethod($class, 'create');
        }

        return parent::getConstructor($data, $class, $context, $reflectionClass, $allowedAttributes);
    }
}
