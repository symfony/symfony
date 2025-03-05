<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ArgumentResolver\ArgumentValueSource;

/**
 * Exposes a context-specific input source from which arguments' values can be extracted then resolved.
 *
 * @author Robin Chalas <robin@baksla.sh>
 */
interface InputSourceInterface
{
    /**
     * @returns mixed The context-specific source from which to extract arguments' source values e.g. an HTTP request or a CLI input
     */
    public function getSource(): mixed;
}
