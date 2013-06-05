<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Data;

use Symfony\Component\Cache\Exception\BadMethodCallException;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class NullResult implements ItemInterface
{
    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        throw new BadMethodCallException('A null result has no value.');
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        throw new BadMethodCallException('A null result has no key.');
    }

    /**
     * {@inheritdoc}
     */
    public function isHit()
    {
        return false;
    }
}
