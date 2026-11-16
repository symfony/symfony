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

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Selectable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\BlindIndexInterface;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Repository\BlindIndexRepositoryTrait;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Repository\BlindIndexResolver;

class BlindIndexRepositoryTraitTest extends TestCase
{
    public function testFindOneByResolvesTheIndexedProperty()
    {
        $repository = new class extends StubRepository {
            use BlindIndexRepositoryTrait;
        };
        $repository->setBlindIndexResolver($this->createResolver());

        $repository->findOneBy(['email' => 'john@example.com']);

        $this->assertSame(['emailIndex' => 'tag-john@example.com'], $repository->criteria);
    }

    public function testFindByResolvesTheIndexedProperty()
    {
        $repository = new class extends StubRepository {
            use BlindIndexRepositoryTrait;
        };
        $repository->setBlindIndexResolver($this->createResolver());

        $repository->findBy(['email' => 'john@example.com'], ['emailIndex' => 'ASC'], 10);

        $this->assertSame(['emailIndex' => 'tag-john@example.com'], $repository->criteria);
        $this->assertSame(['emailIndex' => 'ASC'], $repository->orderBy);
        $this->assertSame(10, $repository->limit);
    }

    public function testMatchingResolvesTheIndexedProperty()
    {
        $repository = new class extends StubRepository {
            use BlindIndexRepositoryTrait;
        };
        $repository->setBlindIndexResolver($this->createResolver());

        $repository->matching(Criteria::create()->where(Criteria::expr()->eq('email', 'john@example.com')));

        $expression = $repository->matchCriteria->getWhereExpression();
        $this->assertInstanceOf(Comparison::class, $expression);
        $this->assertSame('emailIndex', $expression->getField());
        $this->assertSame('tag-john@example.com', $expression->getValue()->getValue());
    }

    public function testCriteriaArePassedThroughWithoutAResolver()
    {
        $repository = new class extends StubRepository {
            use BlindIndexRepositoryTrait;
        };

        $repository->findBy(['email' => 'john@example.com']);

        $this->assertSame(['email' => 'john@example.com'], $repository->criteria);
    }

    private function createResolver(): BlindIndexResolver
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

        return new BlindIndexResolver(
            ['App\Entity\User' => ['email' => ['emailIndex' => 'user-email']]],
            new ServiceLocator(['user-email' => static fn (): BlindIndexInterface => $index]),
        );
    }
}

class StubRepository
{
    public array $criteria = [];
    public ?array $orderBy = null;
    public ?int $limit = null;
    public ?Criteria $matchCriteria = null;

    public function matching(Criteria $criteria): AbstractLazyCollection&Selectable
    {
        $this->matchCriteria = $criteria;

        return new StubLazyCollection();
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $this->criteria = $criteria;
        $this->orderBy = $orderBy;
        $this->limit = $limit;

        return [];
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        $this->criteria = $criteria;
        $this->orderBy = $orderBy;

        return null;
    }

    public function getClassName(): string
    {
        return 'App\Entity\User';
    }
}

class StubLazyCollection extends AbstractLazyCollection
{
    protected function doInitialize(): void
    {
    }
}
