<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Twig\Tests\NodeVisitor;

use PHPUnit\Framework\TestCase;
use Symphony\Bridge\Twig\NodeVisitor\Scope;

class ScopeTest extends TestCase
{
    public function testScopeInitiation()
    {
        $scope = new Scope();
        $scope->enter();
        $this->assertNull($scope->get('test'));
    }
}
