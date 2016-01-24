<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AstGenerator\Tests;

use Symfony\Component\AstGenerator\UniqueVariableScope;

class UniqueVariableScopeTest extends \PHPUnit_Framework_TestCase
{
    public function testUniqueVariable()
    {
        $uniqueVariableScope = new UniqueVariableScope();

        $name = $uniqueVariableScope->getUniqueName('name');
        $this->assertEquals('name', $name);

        $name = $uniqueVariableScope->getUniqueName('name');
        $this->assertEquals('name_1', $name);

        $name = $uniqueVariableScope->getUniqueName('name');
        $this->assertEquals('name_2', $name);
    }
}
