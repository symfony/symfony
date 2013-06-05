<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Driver;

use Symfony\Component\Cache\Psr\CacheInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface DriverInterface extends CacheInterface
{
    /**
     * Returns driver name.
     *
     * @return string
     */
    public function getName();
}
