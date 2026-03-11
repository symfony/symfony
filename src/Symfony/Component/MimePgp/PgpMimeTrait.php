<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\MimePgp;

/**
 * @author PuLLi <the@pulli.dev>
 *
 * @experimental
 */
trait PgpMimeTrait
{
    protected function iteratorToString(iterable $iterator): string
    {
        return implode('', iterator_to_array($iterator, false));
    }
}
