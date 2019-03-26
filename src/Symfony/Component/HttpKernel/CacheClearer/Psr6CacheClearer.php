<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheClearer;

use Symfony\Component\Kernel\CacheClearer\Psr6CacheClearer as BasePsr6CacheClearer;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * TODO Trigger class deprecation on version 5.1.
 */
class Psr6CacheClearer extends BasePsr6CacheClearer implements CacheClearerInterface
{
}
