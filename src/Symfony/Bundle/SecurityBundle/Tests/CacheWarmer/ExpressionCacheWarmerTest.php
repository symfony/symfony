<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\CacheWarmer\ExpressionCacheWarmer;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;

class ExpressionCacheWarmerTest extends TestCase
{
    public function testWarmUp()
    {
        $expressions = [new Expression('A'), new Expression('B')];

        $series = [
            [$expressions[0], ['token', 'user', 'object', 'subject', 'role_names', 'request', 'trust_resolver']],
            [$expressions[1], ['token', 'user', 'object', 'subject', 'role_names', 'request', 'trust_resolver']],
        ];

        $expressionLang = $this->createMock(ExpressionLanguage::class);
        $expressionLang->expects($this->exactly(2))
            ->method('parse')
            ->willReturnCallback(function (...$args) use (&$series) {
                $expectedArgs = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $this->createMock(ParsedExpression::class);
            })
        ;

        (new ExpressionCacheWarmer($expressions, $expressionLang))->warmUp('');
    }
}
