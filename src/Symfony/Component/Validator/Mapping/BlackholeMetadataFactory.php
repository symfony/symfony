<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping;

use Symfony\Component\Validator\MetadataFactoryInterface;

/**
 * Simple implementation of MetadataFactoryInterface that can be used when using ValidatorInterface::validateValue().
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class BlackholeMetadataFactory implements MetadataFactoryInterface
{
    /**
     * @inheritdoc
     */
    public function getMetadataFor($value)
    {
        throw new \LogicException('BlackholeClassMetadataFactory only works with ValidatorInterface::validateValue().');
    }

    /**
     * @inheritdoc
     */
    public function hasMetadataFor($value)
    {
        return false;
    }
}
