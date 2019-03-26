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
 * Interface implemented by a single mapper.
 *
 * Each specific mapper should implements this interface
 *
 * @internal
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
interface MapperInterface
{
    /**
     * @param mixed   $value   Value to map
     * @param array   $context Options mapper have access to
     *
     * @return mixed The mapped value
     */
    public function &map($value, array $context = []);
}
