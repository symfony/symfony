<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\ArgumentResolver;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\Doctrine\ArgumentResolver\EntityCollectionAsset\MappingType;
use Symfony\Bridge\Doctrine\ArgumentResolver\EntityCollectionValueResolver;
use Symfony\Bridge\Doctrine\Attribute\MapEntityCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @internal
 */
#[CoversClass(EntityCollectionValueResolver::class)]
class EntityCollectionValueResolverTest extends TestCase
{
    public function testResolveReturnsMapEntityCollectionAttributes()
    {
        $attribute = new MapEntityCollection('App\Entity\Product');
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument
            ->expects($this->once())
            ->method('getAttributesOfType')
            ->with(MapEntityCollection::class, ArgumentMetadata::IS_INSTANCEOF)
            ->willReturn([$attribute])
        ;

        $resolver = $this->createResolver(
            registry: $this->createStub(ManagerRegistry::class),
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $resolved = $resolver->resolve(new Request(), $argument);

        $this->assertSame([$attribute], $resolved);
    }

    public function testMapEntityCollectionReplacesArgumentWithResolvedCollection()
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn(['row-1', 'row-2']);

        $expr = $this->createStub(Expr::class);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['expr', 'getDQLPart', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->method('expr')->willReturn($expr);
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn([]);
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock()
        ;
        $repository->expects($this->once())->method('createQueryBuilder')->with(EntityCollectionValueResolver::QUERY_ROOT_ALIAS)->willReturn($queryBuilder);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())->method('getManagerForClass')->with('App\Entity\Product')->willReturn($entityManager);

        $attribute = new MapEntityCollection('App\Entity\Product', returnPaginator: false);
        $event = $this->createControllerArgumentsEvent(['first', $attribute, 'third']);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $resolver->mapEntityCollection($event);

        $this->assertSame(['first', ['row-1', 'row-2'], 'third'], $event->getArguments());
    }

    public function testMapEntityCollectionReturnsPaginatorWhenEnabled()
    {
        $query = $this->createStub(Query::class);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDQLPart', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn(['existing_order']);
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with('App\Entity\Product')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $event = $this->createControllerArgumentsEvent([new MapEntityCollection('App\Entity\Product')]);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $resolver->mapEntityCollection($event);

        $resolvedArgument = $event->getArguments()[0];
        $this->assertInstanceOf(Paginator::class, $resolvedArgument);
    }

    public function testMapEntityCollectionThrowsWhenManagerIsNotEntityManager()
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $event = $this->createControllerArgumentsEvent([
            new MapEntityCollection('App\Entity\Missing', returnPaginator: false),
        ]);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No manager found for class "App\Entity\Missing".');

        $resolver->mapEntityCollection($event);
    }

    public function testMapEntityCollectionRejectsUnsupportedDoctrineLimitParameter()
    {
        $expr = $this->createStub(Expr::class);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['expr'])
            ->getMock()
        ;
        $queryBuilder->expects($this->never())->method('expr');

        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock()
        ;
        $repository->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        $event = $this->createControllerArgumentsEvent([
            new MapEntityCollection(
                class: 'App\Entity\Product',
                doctrineParameters: ['pageSize' => MappingType::LIMIT],
                returnPaginator: false,
            ),
        ]);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Doctrine parameter "limit" is not supported.');

        $resolver->mapEntityCollection($event);
    }

    public function testMapEntityCollectionAppliesQueryStringMapping()
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $expr = $this->createMock(Expr::class);
        $comparison = $this->createStub(Comparison::class);
        $expr
            ->expects($this->once())
            ->method('eq')
            ->with('ecr.status', $this->matchesRegularExpression('/^:ecr_status_\d+$/'))
            ->willReturn($comparison)
        ;

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['expr', 'setMaxResults', 'setFirstResult', 'setParameter', 'andWhere', 'getDQLPart', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->method('expr')->willReturn($expr);
        $queryBuilder->expects($this->once())->method('setMaxResults')->with(20)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setFirstResult')->with(20)->willReturnSelf();
        $queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with($this->matchesRegularExpression('/^:ecr_status_\d+$/'), 'active')
            ->willReturnSelf()
        ;
        $queryBuilder->expects($this->once())->method('andWhere')->with($comparison)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn(['existing_order']);
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with('App\Entity\Product')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $propertyInfoExtractor = $this->createMock(PropertyInfoExtractorInterface::class);
        $propertyInfoExtractor
            ->expects($this->once())
            ->method('getProperties')
            ->with(MapEntityCollectionQueryInput::class)
            ->willReturn(['page', 'size', 'status', 'ignored'])
        ;

        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $propertyAccessor
            ->method('getValue')
            ->willReturnCallback(static fn (object $object, string $property): mixed => $object->{$property})
        ;

        $queryInput = new MapEntityCollectionQueryInput(page: 2, size: 20, status: 'active', ignored: 'skip');
        $attribute = new MapEntityCollection(
            class: 'App\Entity\Product',
            queryString: 'query',
            queryMapping: [
                'page' => MappingType::PAGE,
                'size' => MappingType::LIMIT,
                'ignored' => MappingType::IGNORE,
            ],
            returnPaginator: false,
        );
        $event = $this->createControllerArgumentsEvent(
            arguments: [$attribute, $queryInput],
            controller: static function (array $collection, MapEntityCollectionQueryInput $query) {},
        );

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $propertyInfoExtractor,
            propertyAccessor: $propertyAccessor,
        );

        $resolver->mapEntityCollection($event);

        $this->assertIsArray($event->getArguments()[0]);
    }

    public function testMapEntityCollectionAppliesDefaultOrderingWhenOrderByIsMissing()
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDQLPart', 'addOrderBy', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn([]);
        $queryBuilder->expects($this->once())->method('addOrderBy')->with('ecr.createdAt', 'DESC')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with('App\Entity\Product')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $attribute = new MapEntityCollection(
            class: 'App\Entity\Product',
            defaultOrdering: ['createdAt' => MapEntityCollection::ORDERING_DESC],
            returnPaginator: false,
        );
        $event = $this->createControllerArgumentsEvent([$attribute]);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $resolver->mapEntityCollection($event);
    }

    public function testMapEntityCollectionDoesNotApplyDefaultOrderingWhenOrderByExists()
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDQLPart', 'addOrderBy', 'getQuery'])
            ->getMock()
        ;
        $queryBuilder->expects($this->once())->method('getDQLPart')->with('orderBy')->willReturn(['existing_order']);
        $queryBuilder->expects($this->never())->method('addOrderBy');
        $queryBuilder->method('getQuery')->willReturn($query);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with('App\Entity\Product')
            ->willReturn($this->createEntityManagerWithRepositoryQueryBuilder($queryBuilder))
        ;

        $attribute = new MapEntityCollection(
            class: 'App\Entity\Product',
            defaultOrdering: ['createdAt' => MapEntityCollection::ORDERING_DESC],
            returnPaginator: false,
        );
        $event = $this->createControllerArgumentsEvent([$attribute]);

        $resolver = $this->createResolver(
            registry: $registry,
            tokenStorage: $this->createStub(TokenStorageInterface::class),
            container: $this->createStub(ContainerInterface::class),
            propertyInfoExtractor: $this->createStub(PropertyInfoExtractorInterface::class),
            propertyAccessor: $this->createStub(PropertyAccessorInterface::class),
        );

        $resolver->mapEntityCollection($event);
    }

    private function createResolver(
        ManagerRegistry $registry,
        TokenStorageInterface $tokenStorage,
        ContainerInterface $container,
        PropertyInfoExtractorInterface $propertyInfoExtractor,
        PropertyAccessorInterface $propertyAccessor,
    ): EntityCollectionValueResolver {
        return new EntityCollectionValueResolver(
            registry: $registry,
            tokenStorage: $tokenStorage,
            container: $container,
            propertyInfoExtractor: $propertyInfoExtractor,
            propertyAccessor: $propertyAccessor,
        );
    }

    private function createControllerArgumentsEvent(array $arguments, ?callable $controller = null): ControllerArgumentsEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = new Request();
        $controller ??= static fn () => null;

        return new ControllerArgumentsEvent(
            kernel: $kernel,
            controller: $controller,
            arguments: $arguments,
            request: $request,
            requestType: HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function createEntityManagerWithRepositoryQueryBuilder(QueryBuilder $queryBuilder): EntityManagerInterface
    {
        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock()
        ;
        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with(EntityCollectionValueResolver::QUERY_ROOT_ALIAS)
            ->willReturn($queryBuilder)
        ;

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        return $entityManager;
    }
}

final class MapEntityCollectionQueryInput
{
    public function __construct(
        public int $page,
        public int $size,
        public string $status,
        public string $ignored,
    ) {
    }
}
