<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

/**
 * Resources that implement SelfValidatingResourceInterface can check themselves for
 * changes so a generic ResourceValidator can be used.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
interface SelfValidatingResourceInterface extends ResourceInterface
{
    /**
     * Returns true if the resource has not been updated since it has been constructed.
     * @return Boolean true if the resource has not been updated, false otherwise
     */
    public function isFresh();
}
