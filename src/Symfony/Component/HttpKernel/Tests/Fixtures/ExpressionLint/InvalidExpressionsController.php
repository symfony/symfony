<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures\ExpressionLint;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\Attribute\Cache;

class InvalidExpressionsController
{
    #[Cache(if: 'this.ready and', etag: new Expression('id'))]
    public function invalid(int $id)
    {
    }

    #[Cache(maxage: [])]
    public function broken()
    {
    }
}
