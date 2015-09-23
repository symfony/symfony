<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

use Symfony\Component\PropertyInfo\PropertyAccessInfoInterface;
use Symfony\Component\PropertyInfo\PropertyDescriptionInfoInterface;
use Symfony\Component\PropertyInfo\PropertyListRetrieverInterface;
use Symfony\Component\PropertyInfo\PropertyTypeInfoInterface;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DummyExtractor implements PropertyListRetrieverInterface, PropertyDescriptionInfoInterface, PropertyTypeInfoInterface, PropertyAccessInfoInterface
{
    /**
     * {@inheritdoc}
     */
    public function getShortDescription($class, $property, array $context = array())
    {
        return 'short';
    }

    /**
     * {@inheritdoc}
     */
    public function getLongDescription($class, $property, array $context = array())
    {
        return 'long';
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = array())
    {
        return array(new Type(Type::BUILTIN_TYPE_INT));
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($class, $property, array $context = array())
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($class, $property, array $context = array())
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = array())
    {
        return array('a', 'b');
    }
}
