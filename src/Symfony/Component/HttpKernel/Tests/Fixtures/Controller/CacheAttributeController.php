<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures\Controller;

use Symfony\Component\HttpKernel\Attribute\Cache;

#[Cache(smaxage: 20)]
class CacheAttributeController
{
    public const CLASS_SMAXAGE = 20;
    public const METHOD_SMAXAGE = 25;

    #[Cache(smaxage: 25)]
    public function foo()
    {
    }

    public function bar()
    {
    }
}
