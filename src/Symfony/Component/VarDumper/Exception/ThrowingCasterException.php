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
     * @param \Exception $prev The exception thrown from the caster
     */
    public function __construct($prev, \Exception $e = null)
    {
        if (!$prev instanceof \Exception) {
            @trigger_error('Providing $caster as the first argument of the '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Provide directly the $prev exception instead.', E_USER_DEPRECATED);

            $prev = $e;
        }
        parent::__construct('Unexpected '.get_class($prev).' thrown from a caster: '.$prev->getMessage(), 0, $prev);
    }
}
