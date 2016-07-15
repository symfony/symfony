<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataMapper;

use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataMapper\FormDataToObjectConverterInterface;
use Symfony\Component\Form\Extension\Core\DataMapper\ObjectToFormDataConverterInterface;
use Symfony\Component\Form\Extension\Core\DataMapper\SimpleObjectMapper;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Tests\Fixtures\Money;
use Symfony\Component\Form\Tests\Fixtures\MoneyTypeConverter;

class SimpleObjectMapperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FormFactoryInterface
     */
    protected $factory;

    protected function setUp()
    {
        $this->factory = Forms::createFormFactoryBuilder()->getFormFactory();
    }

    public function testMapDataToFormsUsesOriginalMapper()
    {
        $converter = $this->getMockBuilder(FormDataToObjectConverterInterface::class)->getMock();

        $originalMapper = $this->getMockBuilder(DataMapperInterface::class)->getMock();
        $originalMapper->expects($this->once())->method('mapDataToForms');

        $simpleObjectMapper = new SimpleObjectMapper($converter, $originalMapper);
        $simpleObjectMapper->mapDataToForms(new \stdClass(), array());
    }

    public function testMapDataToFormsUsesConverterOnObjectToFormDataConverterInterfaceInstance()
    {
        $converter = $this->getMockBuilder(ConverterStub::class)->getMock();
        $converter->expects($this->once())->method('convertObjectToFormData')->willReturn(array());

        $originalMapper = $this->getMockBuilder(DataMapperInterface::class)->getMock();
        $originalMapper->expects($this->never())->method('mapDataToForms');

        $simpleObjectMapper = new SimpleObjectMapper($converter, $originalMapper);
        $simpleObjectMapper->mapDataToForms(new \stdClass(), array());
    }

    public function testItProperlyMapsObject()
    {
        $money = new Money(20.5, 'EUR');

        $simpleObjectMapper = new SimpleObjectMapper(new FormDataToMoneyConverter($money));

        /** @var FormInterface[] $forms */
        $forms = array(
            'amount' => $this->factory->createNamed('amount', NumberType::class),
            'currency' => $this->factory->createNamed('currency'),
        );

        $simpleObjectMapper->mapDataToForms($money, $forms);

        $this->assertSame(20.5, $forms['amount']->getData());
        $this->assertSame('EUR', $forms['currency']->getData());

        $newMoney = $money;
        $forms['amount']->setData(15.0);
        $forms['currency']->setData('USD');
        $simpleObjectMapper->mapFormsToData($forms, $newMoney);

        $this->assertNotSame($money, $newMoney);
        $this->assertInstanceOf(Money::class, $newMoney);
        $this->assertSame(15.0, $newMoney->getAmount());
        $this->assertSame('USD', $newMoney->getCurrency());
    }

    public function testSettingSimpleObjectMapperOnForm()
    {
        $money = new Money(20.5, 'EUR');

        $simpleObjectMapper = new SimpleObjectMapper(new FormDataToMoneyConverter($money));

        $form = $this->factory->createBuilder(FormType::class, $money, array('data_class' => Money::class))
            ->add('amount', NumberType::class)
            ->add('currency')
            ->setDataMapper($simpleObjectMapper)
            ->getForm()
        ;

        $form->submit(array('amount' => 15.0, 'currency' => 'USD'));

        $newMoney = $form->getData();

        $this->assertNotSame($money, $newMoney);
        $this->assertInstanceOf(Money::class, $newMoney);
        $this->assertSame(15.0, $newMoney->getAmount());
        $this->assertSame('USD', $newMoney->getCurrency());
    }

    public function testItProperlyMapsObjectWithObjectToFormDataConverter()
    {
        $media = new Book('foo');

        $simpleObjectMapper = new SimpleObjectMapper(new MediaConverter());

        /** @var FormInterface[] $forms */
        $forms = array(
            'author' => $this->factory->createNamed('author'),
            'mediaType' => $this->factory->createNamed('mediaType'),
        );

        $simpleObjectMapper->mapDataToForms($media, $forms);

        $this->assertSame('foo', $forms['author']->getData());
        $this->assertSame('book', $forms['mediaType']->getData());

        $newMedia = $media;
        $forms['author']->setData('bar');
        $forms['mediaType']->setData('movie');
        $simpleObjectMapper->mapFormsToData($forms, $newMedia);

        $this->assertNotSame($media, $newMedia);
        $this->assertInstanceOf(Movie::class, $newMedia);
        $this->assertSame('bar', $newMedia->getAuthor());
    }

    public function testSettingSimpleObjectOnFormWithObjectToFormDataConverter()
    {
        $media = new Book('foo');

        $simpleObjectMapper = new SimpleObjectMapper(new MediaConverter());

        $form = $this->factory->createBuilder(FormType::class, $media, array('data_class' => Media::class))
            ->add('author')
            ->add('mediaType')
            ->setDataMapper($simpleObjectMapper)
            ->getForm()
        ;

        $this->assertSame('foo', $form->get('author')->getData());
        $this->assertSame('book', $form->get('mediaType')->getData());

        $form->submit(array('author' => 'bar', 'mediaType' => 'movie'));

        $newMedia = $form->getData();

        $this->assertNotSame($media, $newMedia);
        $this->assertInstanceOf(Movie::class, $newMedia);
        $this->assertSame('bar', $newMedia->getAuthor());
    }
}

class FormDataToMoneyConverter extends \PHPUnit_Framework_TestCase implements FormDataToObjectConverterInterface
{
    private $originalData;

    public function __construct($originalData)
    {
        $this->originalData = $originalData;
    }

    public function convertFormDataToObject(array $data, $originalData)
    {
        $this->assertSame($this->originalData, $originalData);

        $converter = new MoneyTypeConverter();

        return $converter->convertFormDataToObject($data, $originalData);
    }
}

interface ConverterStub extends FormDataToObjectConverterInterface, ObjectToFormDataConverterInterface
{
}

class MediaConverter implements FormDataToObjectConverterInterface, ObjectToFormDataConverterInterface
{
    public function convertFormDataToObject(array $data, $originalData = null)
    {
        $author = $data['author'];

        switch ($data['mediaType']) {
            case 'movie':
                return new Movie($author);
            case 'book':
                return new Book($author);
            default:
                throw new TransformationFailedException();
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param Media|null $object
     */
    public function convertObjectToFormData($object)
    {
        if (null === $object) {
            return array();
        }

        $mediaTypeByClass = array(
            Movie::class => 'movie',
            Book::class => 'book',
        );

        if (!isset($mediaTypeByClass[get_class($object)])) {
            throw new TransformationFailedException();
        }

        return array(
            'mediaType' => $mediaTypeByClass[get_class($object)],
            'author' => $object->getAuthor(),
        );
    }
}

abstract class Media
{
    private $author;

    public function __construct($author)
    {
        $this->author = $author;
    }

    public function getAuthor()
    {
        return $this->author;
    }
}

class Movie extends Media
{
}

class Book extends Media
{
}
