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

class ExpressionsController
{
    #[Cache(if: 'request.isMethodSafe()', etag: new Expression('args["id"]'))]
    public function show(int $id)
    {
    }
}
