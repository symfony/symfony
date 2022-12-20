<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form\ChoiceList;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\ChoiceList\DoctrineChoiceLoader;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityLoaderInterface;
use Symfony\Bridge\Doctrine\Form\ChoiceList\IdReader;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DoctrineChoiceLoaderTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @var MockObject&ChoiceListFactoryInterface
     */
    private $factory;

    /**
     * @var MockObject&ObjectManager
     */
    private $om;

    /**
     * @var MockObject&ObjectRepository
     */
    private $repository;

    /**
     * @var string
     */
    private $class;

    /**
     * @var MockObject&IdReader
     */
    private $idReader;

    /**
     * @var MockObject&EntityLoaderInterface
     */
    private $objectLoader;

    /**
     * @var \stdClass
     */
    private $obj1;

    /**
     * @var \stdClass
     */
    private $obj2;

    /**
     * @var \stdClass
     */
    private $obj3;

    protected function setUp(): void
    {
        $this->factory = self::createMock(ChoiceListFactoryInterface::class);
        $this->om = self::createMock(ObjectManager::class);
        $this->repository = self::createMock(ObjectRepository::class);
        $this->class = 'stdClass';
        $this->idReader = self::createMock(IdReader::class);
        $this->idReader->expects(self::any())
            ->method('isSingleId')
            ->willReturn(true)
        ;

        $this->objectLoader = self::createMock(EntityLoaderInterface::class);
        $this->obj1 = (object) ['name' => 'A'];
        $this->obj2 = (object) ['name' => 'B'];
        $this->obj3 = (object) ['name' => 'C'];

        $this->om->expects(self::any())
            ->method('getRepository')
            ->with($this->class)
            ->willReturn($this->repository);

        $this->om->expects(self::any())
            ->method('getClassMetadata')
            ->with($this->class)
            ->willReturn(new ClassMetadata($this->class));
        $this->repository->expects(self::any())
            ->method('findAll')
            ->willReturn([$this->obj1, $this->obj2, $this->obj3])
        ;
    }

    public function testLoadChoiceList()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader
        );

        $choices = [$this->obj1, $this->obj2, $this->obj3];
        $value = function () {};
        $choiceList = new ArrayChoiceList($choices, $value);

        $this->repository->expects(self::once())
            ->method('findAll')
            ->willReturn($choices);

        self::assertEquals($choiceList, $loader->loadChoiceList($value));

        // no further loads on subsequent calls

        self::assertEquals($choiceList, $loader->loadChoiceList($value));
    }

    public function testLoadChoiceListUsesObjectLoaderIfAvailable()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader,
            $this->objectLoader
        );

        $choices = [$this->obj1, $this->obj2, $this->obj3];
        $choiceList = new ArrayChoiceList($choices);

        $this->repository->expects(self::never())
            ->method('findAll');

        $this->objectLoader->expects(self::once())
            ->method('getEntities')
            ->willReturn($choices);

        self::assertEquals($choiceList, $loaded = $loader->loadChoiceList());

        // no further loads on subsequent calls
        self::assertEquals($loaded, $loader->loadChoiceList());
    }

    public function testLoadValuesForChoices()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            null
        );

        $choices = [$this->obj1, $this->obj2, $this->obj3];

        $this->repository->expects(self::once())
            ->method('findAll')
            ->willReturn($choices);

        self::assertSame(['1', '2'], $loader->loadValuesForChoices([$this->obj2, $this->obj3]));

        // no further loads on subsequent calls

        self::assertSame(['1', '2'], $loader->loadValuesForChoices([$this->obj2, $this->obj3]));
    }

    public function testLoadValuesForChoicesDoesNotLoadIfEmptyChoices()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader
        );

        $this->repository->expects(self::never())
            ->method('findAll');

        self::assertSame([], $loader->loadValuesForChoices([]));
    }

    /**
     * @group legacy
     */
    public function testLoadValuesForChoicesDoesNotLoadIfSingleIntId()
    {
        $this->expectDeprecation('Since symfony/doctrine-bridge 5.1: Not defining explicitly the IdReader as value callback when query can be optimized is deprecated. Don\'t pass the IdReader to "Symfony\Bridge\Doctrine\Form\ChoiceList\DoctrineChoiceLoader" or define the "choice_value" option instead.');
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader
        );

        $this->repository->expects(self::never())
            ->method('findAll');

        $this->idReader->expects(self::any())
            ->method('getIdValue')
            ->with($this->obj2)
            ->willReturn('2');

        self::assertSame(['2'], $loader->loadValuesForChoices([$this->obj2]));
    }

    public function testLoadValuesForChoicesDoesNotLoadIfSingleIntIdAndValueGiven()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader
        );

        $choices = [$this->obj1, $this->obj2, $this->obj3];
        $value = function (\stdClass $object) { return $object->name; };

        $this->repository->expects(self::never())
            ->method('findAll')
            ->willReturn($choices);

        self::assertSame(['B'], $loader->loadValuesForChoices(
            [$this->obj2],
            $value
        ));
    }

    public function testLoadValuesForChoicesDoesNotLoadIfValueIsIdReader()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader
        );

        $value = [$this->idReader, 'getIdValue'];

        $this->repository->expects(self::never())
            ->method('findAll');

        $this->idReader->expects(self::any())
            ->method('getIdValue')
            ->with($this->obj2)
            ->willReturn('2');

        self::assertSame(['2'], $loader->loadValuesForChoices(
            [$this->obj2],
            $value
        ));
    }

    public function testLoadChoicesForValues()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class
        );

        $choices = [$this->obj1, $this->obj2, $this->obj3];

        $this->repository->expects(self::once())
            ->method('findAll')
            ->willReturn($choices);

        self::assertSame([$this->obj2, $this->obj3], $loader->loadChoicesForValues(['1', '2']));

        // no further loads on subsequent calls

        self::assertSame([$this->obj2, $this->obj3], $loader->loadChoicesForValues(['1', '2']));
    }

    public function testLoadChoicesForValuesDoesNotLoadIfEmptyValues()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader
        );

        $this->repository->expects(self::never())
            ->method('findAll');

        self::assertSame([], $loader->loadChoicesForValues([]));
    }

    /**
     * @group legacy
     */
    public function testLegacyLoadChoicesForValuesLoadsOnlyChoicesIfValueUseIdReader()
    {
        $this->expectDeprecation('Since symfony/doctrine-bridge 5.1: Not defining explicitly the IdReader as value callback when query can be optimized is deprecated. Don\'t pass the IdReader to "Symfony\Bridge\Doctrine\Form\ChoiceList\DoctrineChoiceLoader" or define the "choice_value" option instead.');
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader,
            $this->objectLoader
        );

        $choices = [$this->obj2, $this->obj3];

        $this->idReader->expects(self::any())
            ->method('getIdField')
            ->willReturn('idField');

        $this->repository->expects(self::never())
            ->method('findAll');

        $this->objectLoader->expects(self::once())
            ->method('getEntitiesByIds')
            ->with('idField', [4 => '3', 7 => '2'])
            ->willReturn($choices);

        $this->idReader->expects(self::any())
            ->method('getIdValue')
            ->willReturnMap([
                [$this->obj2, '2'],
                [$this->obj3, '3'],
            ]);

        self::assertSame([4 => $this->obj3, 7 => $this->obj2], $loader->loadChoicesForValues([4 => '3', 7 => '2']));
    }

    public function testLoadChoicesForValuesLoadsOnlyChoicesIfValueUseIdReader()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader,
            $this->objectLoader
        );

        $choices = [$this->obj2, $this->obj3];

        $this->idReader->expects(self::any())
            ->method('getIdField')
            ->willReturn('idField');

        $this->repository->expects(self::never())
            ->method('findAll');

        $this->objectLoader->expects(self::once())
            ->method('getEntitiesByIds')
            ->with('idField', [4 => '3', 7 => '2'])
            ->willReturn($choices);

        $this->idReader->expects(self::any())
            ->method('getIdValue')
            ->willReturnMap([
                [$this->obj2, '2'],
                [$this->obj3, '3'],
            ]);

        self::assertSame([4 => $this->obj3, 7 => $this->obj2], $loader->loadChoicesForValues([4 => '3', 7 => '2'], [$this->idReader, 'getIdValue']));
    }

    public function testLoadChoicesForValuesLoadsAllIfSingleIntIdAndValueGiven()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader
        );

        $choices = [$this->obj1, $this->obj2, $this->obj3];
        $value = function (\stdClass $object) { return $object->name; };

        $this->repository->expects(self::once())
            ->method('findAll')
            ->willReturn($choices);

        self::assertSame([$this->obj2], $loader->loadChoicesForValues(
            ['B'],
            $value
        ));
    }

    public function testLoadChoicesForValuesLoadsOnlyChoicesIfValueIsIdReader()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader,
            $this->objectLoader
        );

        $choices = [$this->obj2, $this->obj3];
        $value = [$this->idReader, 'getIdValue'];

        $this->idReader->expects(self::any())
            ->method('getIdField')
            ->willReturn('idField');

        $this->repository->expects(self::never())
            ->method('findAll');

        $this->objectLoader->expects(self::once())
            ->method('getEntitiesByIds')
            ->with('idField', ['2'])
            ->willReturn($choices);

        $this->idReader->expects(self::any())
            ->method('getIdValue')
            ->willReturnMap([
                [$this->obj2, '2'],
                [$this->obj3, '3'],
            ]);

        self::assertSame([$this->obj2], $loader->loadChoicesForValues(['2'], $value));
    }

    public function testPassingIdReaderWithoutSingleIdEntity()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The second argument `$idReader` of "Symfony\\Bridge\\Doctrine\\Form\\ChoiceList\\DoctrineChoiceLoader::__construct" must be null when the query cannot be optimized because of composite id fields.');

        $idReader = self::createMock(IdReader::class);
        $idReader->expects(self::once())
            ->method('isSingleId')
            ->willReturn(false)
        ;

        new DoctrineChoiceLoader($this->om, $this->class, $idReader, $this->objectLoader);
    }
}
