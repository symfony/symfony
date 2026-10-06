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

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;

#[Cache(lastModified: 'this.lastModified')]
class ExpressionsController
{
    #[Cache(etag: new Expression('args["id"]'), if: 'request.isMethodSafe()')]
    public function show(int $id, #[MapQueryString(validationGroups: new Expression('"Default"'))] ?object $query = null)
    {
    }

    #[NotAnAttribute(new Expression('"missing"'))]
    #[Cache(public: true)]
    public function broken()
    {
    }

    #[Cache(etag: new Expression('"private"'))]
    private function hidden()
    {
    }
}
