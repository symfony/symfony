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
 * MutableResourceInterface is the interface that must be implemented by all Resource classes.
 *
 * @author Luc Vieillescazes <luc@vieillescazes.net>
 */
interface MutableResourceInterface extends ResourceInterface
{
    public function isMutable();
}
