<?php

namespace Symfony\Bridge\Propel1\Tests\Form\Type;

use Symfony\Bridge\Propel1\Tests\Fixtures\Item;
use Symfony\Bridge\Propel1\Form\PropelExtension;
use Symfony\Bridge\Propel1\Tests\Fixtures\TranslatableItemI18n;
use Symfony\Bridge\Propel1\Tests\Fixtures\TranslatableItem;
use Symfony\Component\Form\Tests\Extension\Core\Type\TypeTestCase;

/**
 * Created by JetBrains PhpStorm.
 * User: patrickkaufmann
 * Date: 7/27/12
 * Time: 11:02 AM
 * To change this template use File | Settings | File Templates.
 */
class TranslationCollectionTypeTest extends TypeTestCase
{
    const TRANSLATION_CLASS = 'Symfony\Bridge\Propel1\Tests\Fixtures\TranslatableItem';
    const TRANSLATABLE_I18N_CLASS = 'Symfony\Bridge\Propel1\Tests\Fixtures\TranslatableItemI18n';
    const NON_TRANSLATION_CLASS = 'Symfony\Bridge\Propel1\Tests\Fixtures\Item';

    protected function setUp()
    {
        parent::setUp();
    }

    protected function getExtensions()
    {
        return array_merge(parent::getExtensions(), array(
            new PropelExtension(),
        ));
    }

    public function testTranslationsAdded()
    {
        $item = new TranslatableItem();
        $item->addTranslatableItemI18n(new TranslatableItemI18n(1, 'fr', 'val1'));
        $item->addTranslatableItemI18n(new TranslatableItemI18n(2, 'en', 'val2'));

        $builder = $this->factory->createBuilder('form', null, array(
            'data_class' => self::TRANSLATION_CLASS
        ));

        $builder->add('translatableItemI18ns', 'propel1_translation_collection', array(
            'languages' => array('en', 'fr'),
            'options' => array(
                'data_class' => self::TRANSLATABLE_I18N_CLASS,
                'columns' => array('value', 'value2' => array('label' => 'Label', 'type' => 'textarea'))
            )
        ));
        $form = $builder->getForm();
        $form->setData($item);
        $translations = $form->get('translatableItemI18ns');

        $this->assertCount(2, $translations);
        $this->assertInstanceOf('Symfony\Component\Form\Form', $translations['en']);
        $this->assertInstanceOf('Symfony\Component\Form\Form', $translations['fr']);

        $this->assertInstanceOf(self::TRANSLATABLE_I18N_CLASS, $translations['en']->getData());
        $this->assertInstanceOf(self::TRANSLATABLE_I18N_CLASS, $translations['fr']->getData());

        $this->assertEquals($item->getTranslation('en'), $translations['en']->getData());
        $this->assertEquals($item->getTranslation('fr'), $translations['fr']->getData());

        $this->assertEquals('value', $translations['fr']->getConfig()->getOption('columns')[0]);
        $this->assertEquals('textarea', $translations['fr']->getConfig()->getOption('columns')['value2']['type']);
        $this->assertEquals('Label', $translations['fr']->getConfig()->getOption('columns')['value2']['label']);
    }

    public function testNotPresentTranslationsAdded()
    {
        $item = new TranslatableItem();

        $this->assertCount(0, $item->getTranslatableItemI18ns());

        $builder = $this->factory->createBuilder('form', null, array(
            'data_class' => self::TRANSLATION_CLASS
        ));
        $builder->add('translatableItemI18ns', 'propel1_translation_collection', array(
            'languages' => array('en', 'fr'),
            'options' => array(
                'data_class' => self::TRANSLATABLE_I18N_CLASS,
                'columns' => array('value', 'value2' => array('label' => 'Label', 'type' => 'textarea'))
            )
        ));

        $form = $builder->getForm();
        $form->setData($item);

        $this->assertCount(2, $item->getTranslatableItemI18ns());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testNoArrayGiven()
    {
        $item = new Item(null, 'val');

        $builder = $this->factory->createBuilder('form', null, array(
            'data_class' => self::NON_TRANSLATION_CLASS
        ));
        $builder->add('value', 'propel1_translation_collection', array(
            'languages' => array('en', 'fr'),
            'options' => array(
                'data_class' => self::TRANSLATABLE_I18N_CLASS,
                'columns' => array('value', 'value2' => array('label' => 'Label', 'type' => 'textarea'))
            )
        ));

        $form = $builder->getForm();
        $form->setData($item);
    }

    /**
     * @exssspectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testTranslationClassHasNoGetLocaleMethod()
    {
        $item = new Item(null, array('a', 'b', 'c'));

        $form = $this->factory->createNamed('value', 'propel1_translation_collection', null, array(
            'languages' => array('en', 'fr'),
            'options' => array(
                'data_class' => self::TRANSLATABLE_I18N_CLASS,
                'columns' => array('value', 'value2' => array('label' => 'Label', 'type' => 'textarea'))
            )
        ));
        $form->bind($item->getValue());
        //$form->setData($item->getValue());
    }

    /**
     * @expectedException Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testNoDataClassAdded()
    {
        $this->factory->createNamed('itemI18ns', 'propel1_translation_collection', null, array(
            'languages' => array('en', 'fr'),
            'options' => array(
                'columns' => array('value', 'value2')
            )
        ));
    }

    /**
     * @expectedException Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testNoLanguagesAdded()
    {
        $this->factory->createNamed('itemI18ns', 'propel1_translation_collection', null, array(
           'options' => array(
               'data_class' => self::TRANSLATABLE_I18N_CLASS,
               'columns' => array('value', 'value2')
           )
        ));
    }

    /**
     * @expectedException Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testNoColumnsAdded()
    {
        $this->factory->createNamed('itemI18ns', 'propel1_translation_collection', null, array(
            'languages' => array('en', 'fr'),
            'options' => array(
                'data_class' => self::TRANSLATABLE_I18N_CLASS
            )
        ));
    }
}
