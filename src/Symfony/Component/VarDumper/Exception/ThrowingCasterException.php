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
    private $caster;

    /**
     * @param callable   $caster The failing caster
     * @param \Exception $prev   The exception thrown from the caster
     */
    public function __construct($caster, \Exception $prev)
    {
        if (is_array($caster)) {
            if (isset($caster[0]) && is_object($caster[0])) {
                $caster[0] = get_class($caster[0]);
            }
            $caster = implode('::', $caster);
        }
        $this->caster = $caster;
        parent::__construct(null, 0, $prev);
    }
}
