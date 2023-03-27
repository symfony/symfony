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
use Symfony\Component\Form\Exception\LogicException;

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
        $this->factory = $this->createMock(ChoiceListFactoryInterface::class);
        $this->om = $this->createMock(ObjectManager::class);
        $this->repository = $this->createMock(ObjectRepository::class);
        $this->class = 'stdClass';
        $this->idReader = $this->createMock(IdReader::class);
        $this->idReader->expects($this->any())
            ->method('isSingleId')
            ->willReturn(true)
        ;

        $this->objectLoader = $this->createMock(EntityLoaderInterface::class);
        $this->obj1 = (object) ['name' => 'A'];
        $this->obj2 = (object) ['name' => 'B'];
        $this->obj3 = (object) ['name' => 'C'];

        $this->om->expects($this->any())
            ->method('getRepository')
            ->with($this->class)
            ->willReturn($this->repository);

        $this->om->expects($this->any())
            ->method('getClassMetadata')
            ->with($this->class)
            ->willReturn(new ClassMetadata($this->class));
        $this->repository->expects($this->any())
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

        $this->repository->expects($this->once())
            ->method('findAll')
            ->willReturn($choices);

        $this->assertEquals($choiceList, $loader->loadChoiceList($value));

        // no further loads on subsequent calls

        $this->assertEquals($choiceList, $loader->loadChoiceList($value));
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

        $this->repository->expects($this->never())
            ->method('findAll');

        $this->objectLoader->expects($this->once())
            ->method('getEntities')
            ->willReturn($choices);

        $this->assertEquals($choiceList, $loaded = $loader->loadChoiceList());

        // no further loads on subsequent calls
        $this->assertEquals($loaded, $loader->loadChoiceList());
    }

    public function testLoadValuesForChoices()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            null
        );

        $choices = [$this->obj1, $this->obj2, $this->obj3];

        $this->repository->expects($this->once())
            ->method('findAll')
            ->willReturn($choices);

        $this->assertSame(['1', '2'], $loader->loadValuesForChoices([$this->obj2, $this->obj3]));

        // no further loads on subsequent calls

        $this->assertSame(['1', '2'], $loader->loadValuesForChoices([$this->obj2, $this->obj3]));
    }

    public function testLoadValuesForChoicesDoesNotLoadIfEmptyChoices()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader
        );

        $this->repository->expects($this->never())
            ->method('findAll');

        $this->assertSame([], $loader->loadValuesForChoices([]));
    }

    public function testLoadValuesForChoicesDoesNotLoadIfSingleIntId()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Not defining the IdReader explicitly as a value callback when the query can be optimized is not supported.');

        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader
        );

        $this->repository->expects($this->never())
            ->method('findAll');

        $this->idReader->expects($this->any())
            ->method('getIdValue')
            ->with($this->obj2)
            ->willReturn('2');

        $this->assertSame(['2'], $loader->loadValuesForChoices([$this->obj2]));
    }

    public function testLoadValuesForChoicesDoesNotLoadIfSingleIntIdAndValueGiven()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader
        );

        $choices = [$this->obj1, $this->obj2, $this->obj3];
        $value = fn (\stdClass $object) => $object->name;

        $this->repository->expects($this->never())
            ->method('findAll')
            ->willReturn($choices);

        $this->assertSame(['B'], $loader->loadValuesForChoices(
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

        $this->repository->expects($this->never())
            ->method('findAll');

        $this->idReader->expects($this->any())
            ->method('getIdValue')
            ->with($this->obj2)
            ->willReturn('2');

        $this->assertSame(['2'], $loader->loadValuesForChoices(
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

        $this->repository->expects($this->once())
            ->method('findAll')
            ->willReturn($choices);

        $this->assertSame([$this->obj2, $this->obj3], $loader->loadChoicesForValues(['1', '2']));

        // no further loads on subsequent calls

        $this->assertSame([$this->obj2, $this->obj3], $loader->loadChoicesForValues(['1', '2']));
    }

    public function testLoadChoicesForValuesDoesNotLoadIfEmptyValues()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader
        );

        $this->repository->expects($this->never())
            ->method('findAll');

        $this->assertSame([], $loader->loadChoicesForValues([]));
    }

    public function testLegacyLoadChoicesForValuesLoadsOnlyChoicesIfValueUseIdReader()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Not defining the IdReader explicitly as a value callback when the query can be optimized is not supported.');

        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader,
            $this->objectLoader
        );

        $choices = [$this->obj2, $this->obj3];

        $this->idReader->expects($this->any())
            ->method('getIdField')
            ->willReturn('idField');

        $this->repository->expects($this->never())
            ->method('findAll');

        $this->objectLoader->expects($this->never())
            ->method('getEntitiesByIds');

        $this->assertSame(
            [4 => $this->obj3, 7 => $this->obj2],
            $loader->loadChoicesForValues([4 => '3', 7 => '2'])
        );
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

        $this->idReader->expects($this->any())
            ->method('getIdField')
            ->willReturn('idField');

        $this->repository->expects($this->never())
            ->method('findAll');

        $this->objectLoader->expects($this->once())
            ->method('getEntitiesByIds')
            ->with('idField', [4 => '3', 7 => '2'])
            ->willReturn($choices);

        $this->idReader->expects($this->any())
            ->method('getIdValue')
            ->willReturnMap([
                [$this->obj2, '2'],
                [$this->obj3, '3'],
            ]);

        $this->assertSame(
            [4 => $this->obj3, 7 => $this->obj2],
            $loader->loadChoicesForValues([4 => '3', 7 => '2'], [$this->idReader, 'getIdValue'])
        );
    }

    public function testLoadChoicesForValuesLoadsAllIfSingleIntIdAndValueGiven()
    {
        $loader = new DoctrineChoiceLoader(
            $this->om,
            $this->class,
            $this->idReader
        );

        $choices = [$this->obj1, $this->obj2, $this->obj3];
        $value = fn (\stdClass $object) => $object->name;

        $this->repository->expects($this->once())
            ->method('findAll')
            ->willReturn($choices);

        $this->assertSame([$this->obj2], $loader->loadChoicesForValues(
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

        $this->idReader->expects($this->any())
            ->method('getIdField')
            ->willReturn('idField');

        $this->repository->expects($this->never())
            ->method('findAll');

        $this->objectLoader->expects($this->once())
            ->method('getEntitiesByIds')
            ->with('idField', ['2'])
            ->willReturn($choices);

        $this->idReader->expects($this->any())
            ->method('getIdValue')
            ->willReturnMap([
                [$this->obj2, '2'],
                [$this->obj3, '3'],
            ]);

        $this->assertSame([$this->obj2], $loader->loadChoicesForValues(['2'], $value));
    }

    public function testPassingIdReaderWithoutSingleIdEntity()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The second argument "$idReader" of "Symfony\\Bridge\\Doctrine\\Form\\ChoiceList\\DoctrineChoiceLoader::__construct" must be null when the query cannot be optimized because of composite id fields.');

        $idReader = $this->createMock(IdReader::class);
        $idReader->expects($this->once())
            ->method('isSingleId')
            ->willReturn(false)
        ;

        new DoctrineChoiceLoader($this->om, $this->class, $idReader, $this->objectLoader);
    }
}
