<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Repository;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Order;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\BlindIndexInterface;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Repository\BlindIndexResolver;
use Symfony\Component\KeyManagement\Exception\LogicException;

class BlindIndexResolverTest extends TestCase
{
    public function testACriterionOnAnIndexedPropertyBecomesItsTag()
    {
        $resolver = $this->createResolver();

        $this->assertSame(
            ['emailIndex' => 'tag-john@example.com'],
            $resolver->resolve('App\Entity\User', ['email' => 'john@example.com']),
        );
    }

    public function testOtherCriteriaAreUntouched()
    {
        $resolver = $this->createResolver();

        $this->assertSame(
            ['username' => 'john', 'enabled' => true],
            $resolver->resolve('App\Entity\User', ['username' => 'john', 'enabled' => true]),
        );
    }

    public function testAnotherClassIsUntouched()
    {
        $resolver = $this->createResolver();

        $this->assertSame(
            ['email' => 'john@example.com'],
            $resolver->resolve('App\Entity\Contact', ['email' => 'john@example.com']),
        );
    }

    public function testNullGivesANullTag()
    {
        $resolver = $this->createResolver();

        $this->assertSame(
            ['emailIndex' => null],
            $resolver->resolve('App\Entity\User', ['email' => null]),
        );
    }

    public function testAnArrayOfValuesIsMapped()
    {
        $resolver = $this->createResolver();

        $this->assertSame(
            ['emailIndex' => ['tag-john@example.com', 'tag-jane@example.com']],
            $resolver->resolve('App\Entity\User', ['email' => ['john@example.com', 'jane@example.com']]),
        );
    }

    public function testAPropertyIndexedTwiceIsRefused()
    {
        $resolver = new BlindIndexResolver(
            ['App\Entity\User' => ['email' => ['emailIndex' => 'user-email', 'emailDomainIndex' => 'user-email-domain']]],
            $this->createIndexes(),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "App\Entity\User::$email" property is indexed by several blind indexes ("emailIndex", "emailDomainIndex")');

        $resolver->resolve('App\Entity\User', ['email' => 'john@example.com']);
    }

    public function testACriterionOnAnIndexedPropertyBecomesItsTagInACriteria()
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('email', 'john@example.com'));

        $expression = $this->createResolver()->resolveCriteria('App\Entity\User', $criteria)->getWhereExpression();

        $this->assertInstanceOf(Comparison::class, $expression);
        $this->assertSame('emailIndex', $expression->getField());
        $this->assertSame('tag-john@example.com', $expression->getValue()->getValue());
    }

    public function testInIsMappedToTags()
    {
        $criteria = Criteria::create()->where(Criteria::expr()->in('email', ['john@example.com', 'jane@example.com']));

        $expression = $this->createResolver()->resolveCriteria('App\Entity\User', $criteria)->getWhereExpression();

        $this->assertInstanceOf(Comparison::class, $expression);
        $this->assertSame('emailIndex', $expression->getField());
        $this->assertSame(['tag-john@example.com', 'tag-jane@example.com'], $expression->getValue()->getValue());
    }

    public function testNullIsMappedToANullTagInACriteria()
    {
        $criteria = Criteria::create()->where(Criteria::expr()->isNull('email'));

        $expression = $this->createResolver()->resolveCriteria('App\Entity\User', $criteria)->getWhereExpression();

        $this->assertInstanceOf(Comparison::class, $expression);
        $this->assertSame('emailIndex', $expression->getField());
        $this->assertNull($expression->getValue()->getValue());
    }

    public function testCompositeExpressionsAreRewritten()
    {
        $criteria = Criteria::create()->where(Criteria::expr()->andX(
            Criteria::expr()->eq('email', 'john@example.com'),
            Criteria::expr()->eq('username', 'john'),
        ));

        $expression = $this->createResolver()->resolveCriteria('App\Entity\User', $criteria)->getWhereExpression();

        $this->assertInstanceOf(CompositeExpression::class, $expression);
        $comparisons = $expression->getExpressionList();
        $this->assertSame('emailIndex', $comparisons[0]->getField());
        $this->assertSame('username', $comparisons[1]->getField());
    }

    public function testOrderingsAndLimitsAreKept()
    {
        $order = class_exists(Order::class) ? Order::Ascending : 'ASC';
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('email', 'john@example.com'))
            ->orderBy(['id' => $order])
            ->setFirstResult(10)
            ->setMaxResults(5);

        $resolved = $this->createResolver()->resolveCriteria('App\Entity\User', $criteria);

        $this->assertSame(['id' => $order], $resolved->orderings());
        $this->assertSame(10, $resolved->getFirstResult());
        $this->assertSame(5, $resolved->getMaxResults());
    }

    public function testAnOrderingComparisonIsRefused()
    {
        $criteria = Criteria::create()->where(Criteria::expr()->gt('email', 'john@example.com'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The ">" comparison cannot be applied to the blind-indexed property "App\Entity\User::$email"');

        $this->createResolver()->resolveCriteria('App\Entity\User', $criteria);
    }

    public function testACriteriaWithoutExpressionIsReturnedUnchanged()
    {
        $criteria = Criteria::create();

        $this->assertSame($criteria, $this->createResolver()->resolveCriteria('App\Entity\User', $criteria));
    }

    public function testAValueThatIsNotAStringIsRefused()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A blind index is derived from a string, "stdClass" given.');

        $this->createResolver()->resolve('App\Entity\User', ['email' => new \stdClass()]);
    }

    public function testAStringableValueIsIndexed()
    {
        $value = new class implements \Stringable {
            public function __toString(): string
            {
                return 'john@example.com';
            }
        };

        $this->assertSame(
            ['emailIndex' => 'tag-john@example.com'],
            $this->createResolver()->resolve('App\Entity\User', ['email' => $value]),
        );
    }

    private function createResolver(): BlindIndexResolver
    {
        return new BlindIndexResolver(
            ['App\Entity\User' => ['email' => ['emailIndex' => 'user-email']]],
            $this->createIndexes(),
        );
    }

    private function createIndexes(): ServiceLocator
    {
        $index = new class implements BlindIndexInterface {
            public function of(#[\SensitiveParameter] string $value): string
            {
                return 'tag-'.$value;
            }

            public function allOf(#[\SensitiveParameter] string $value): array
            {
                return ['tag-'.$value];
            }
        };

        return new ServiceLocator(['user-email' => static fn (): BlindIndexInterface => $index]);
    }
}
