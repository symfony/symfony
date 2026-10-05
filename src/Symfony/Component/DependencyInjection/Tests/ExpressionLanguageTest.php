<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ExpressionLanguage;
use Symfony\Component\DependencyInjection\Tests\Fixtures\StringBackedEnum;

class ExpressionLanguageTest extends TestCase
{
    public function testConstantAndEnumFunctionsAllowAnyConstant()
    {
        $expressionLanguage = new ExpressionLanguage();

        $this->assertSame(\PHP_VERSION, $expressionLanguage->evaluate('constant("PHP_VERSION")'));
        $this->assertSame(StringBackedEnum::Bar, $expressionLanguage->evaluate('enum(name)', ['name' => StringBackedEnum::class.'::Bar']));
    }
}
