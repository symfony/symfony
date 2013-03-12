<?php

namespace Symfony\Bridge\Twig\Tests\NodeVisitor;

use Symfony\Bridge\Twig\NodeVisitor\Scope;
use Symfony\Bridge\Twig\Tests\TestCase;

class ScopeTest extends TestCase
{
    public function testScopeInitiation()
    {
        $scope = new Scope();
        $scope->enter();
        $result = $scope->get('test');
        $scope->leave();

        $this->assertEquals($result, null);
    }
}