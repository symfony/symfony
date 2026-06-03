<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\NodeVisitor;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\NodeVisitor\Scope;

class ScopeTest extends TestCase
{
    public function testScopeInitiation()
    {
        $scope = new Scope();
        $scope->enter();
        $this->assertNull($scope->get('test'));
    }
}
