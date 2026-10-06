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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\Authorization\Voter\ExpressionVoter;

class ExpressionCacheWarmerTest extends TestCase
{
    public function testWarmUp()
    {
        $expressions = [new Expression('A'), new Expression('B')];

        $series = [
            [$expressions[0], ['token', 'user', 'object', 'subject', 'role_names', 'auth_checker', 'request', 'trust_resolver']],
            [$expressions[1], ['token', 'user', 'object', 'subject', 'role_names', 'auth_checker', 'request', 'trust_resolver']],
        ];

        $expressionLang = $this->createMock(ExpressionLanguage::class);
        $expressionLang->expects($this->exactly(2))
            ->method('parse')
            ->willReturnCallback(function (Expression|string $expression, array $names) use (&$series) {
                [$expectedExpression, $expectedNames] = array_shift($series);

                $this->assertSame($expectedExpression, $expression);
                $this->assertSame($expectedNames, $names);

                return $this->createStub(ParsedExpression::class);
            })
        ;

        (new ExpressionCacheWarmer($expressions, $expressionLang))->warmUp('');
    }

    public function testWarmUpPrimesTheCacheEntriesReadByTheExpressionVoter()
    {
        $cache = new ArrayAdapter();
        $expressionLanguage = new ExpressionLanguage($cache);
        $expression = new Expression('is_granted("ROLE_USER")');

        (new ExpressionCacheWarmer([$expression], $expressionLanguage))->warmUp('');
        $warmedKeys = array_keys($cache->getValues());

        $voter = new ExpressionVoter($expressionLanguage, new AuthenticationTrustResolver(), $this->createStub(AuthorizationCheckerInterface::class));
        $voter->vote(new NullToken(), new Request(), [$expression]);

        $this->assertSame($warmedKeys, array_keys($cache->getValues()));
    }
}
