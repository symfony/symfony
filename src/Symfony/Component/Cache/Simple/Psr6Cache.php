<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Simple;

use Symfony\Component\Cache\Psr16Cache;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.3, use "%s" instead.', Psr6Cache::class, Psr16Cache::class), E_USER_DEPRECATED);

/**
 * @deprecated since Symfony 4.3, use Psr16Cache instead.
 */
class Psr6Cache extends Psr16Cache
{
}
