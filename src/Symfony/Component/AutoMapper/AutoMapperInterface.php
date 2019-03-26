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

/**
 * An auto mapper has the role of mapping a source to a target.
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
interface AutoMapperInterface
{
    /**
     * Maps data from a source to a target.
     *
     * @param array|object        $source  Any data object, which may be an object or an array
     * @param string|array|object $target  To which type of data, or data, the source should be mapped
     * @param array               $context Mapper context
     *
     * @return array|object The mapped object
     */
    public function map($source, $target, array $context = []);
}
