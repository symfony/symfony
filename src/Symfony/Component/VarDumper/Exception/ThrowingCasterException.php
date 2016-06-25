<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Exception;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ThrowingCasterException extends \Exception
{
    /**
     * @param callable   $caster The failing caster
     * @param \Exception $prev   The exception thrown from the caster
     */
    public function __construct($caster, \Exception $prev)
    {
        parent::__construct('Unexpected '.get_class($prev).' thrown from a caster: '.$prev->getMessage(), 0, $prev);
    }
}
