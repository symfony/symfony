<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Tests\Form\Type;

use Symfony\Bridge\Propel1\Tests\Fixtures\Item;
use Symfony\Bridge\Propel1\Form\PropelExtension;
use Symfony\Bridge\Propel1\Tests\Fixtures\TranslatableItemI18n;
use Symfony\Bridge\Propel1\Tests\Fixtures\TranslatableItem;
use Symfony\Component\Form\Test\TypeTestCase;

class TranslationCollectionTypeTest extends TypeTestCase
{
    const TRANSLATION_CLASS = 'Symfony\Bridge\Propel1\Tests\Fixtures\TranslatableItem';
    const TRANSLATABLE_I18N_CLASS = 'Symfony\Bridge\Propel1\Tests\Fixtures\TranslatableItemI18n';
    const NON_TRANSLATION_CLASS = 'Symfony\Bridge\Propel1\Tests\Fixtures\Item';

    protected function getExtensions()
    {
        return array(new PropelExtension());
    }

    public function testTranslationsAdded()
    {
        $item = new TranslatableItem();
        $item->addTranslatableItemI18n(new TranslatableItemI18n(1, 'fr', 'val1'));
        $item->addTranslatableItemI18n(new TranslatableItemI18n(2, 'en', 'val2'));

        $builder = $this->factory->createBuilder('form', null, array(
            'data_class' => self::TRANSLATION_CLASS,
        ));

        $builder->add('translatableItemI18ns', 'propel1_translation_collection', array(
            'languages' => array('en', 'fr'),
            'options' => array(
                'data_class' => self::TRANSLATABLE_I18N_CLASS,
                'columns' => array('value', 'value2' => array('label' => 'Label', 'type' => 'textarea')),
            ),
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

        $columnOptions = $translations['fr']->getConfig()->getOption('columns');
        $this->assertEquals('value', $columnOptions[0]);
        $this->assertEquals('textarea', $columnOptions['value2']['type']);
        $this->assertEquals('Label', $columnOptions['value2']['label']);
    }

    public function testNotPresentTranslationsAdded()
    {
        $item = new TranslatableItem();

        $this->assertCount(0, $item->getTranslatableItemI18ns());

        $builder = $this->factory->createBuilder('form', null, array(
            'data_class' => self::TRANSLATION_CLASS,
        ));
        $builder->add('translatableItemI18ns', 'propel1_translation_collection', array(
            'languages' => array('en', 'fr'),
            'options' => array(
                'data_class' => self::TRANSLATABLE_I18N_CLASS,
                'columns' => array('value', 'value2' => array('label' => 'Label', 'type' => 'textarea')),
            ),
        ));

        $form = $builder->getForm();
        $form->setData($item);

        $this->assertCount(2, $item->getTranslatableItemI18ns());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testNoArrayGiven()
    {
        $item = new Item(null, 'val');

        $builder = $this->factory->createBuilder('form', null, array(
            'data_class' => self::NON_TRANSLATION_CLASS,
        ));
        $builder->add('value', 'propel1_translation_collection', array(
            'languages' => array('en', 'fr'),
            'options' => array(
                'data_class' => self::TRANSLATABLE_I18N_CLASS,
                'columns' => array('value', 'value2' => array('label' => 'Label', 'type' => 'textarea')),
            ),
        ));

        $form = $builder->getForm();
        $form->setData($item);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testNoDataClassAdded()
    {
        $this->factory->createNamed('itemI18ns', 'propel1_translation_collection', null, array(
            'languages' => array('en', 'fr'),
            'options' => array(
                'columns' => array('value', 'value2'),
            ),
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testNoLanguagesAdded()
    {
        $this->factory->createNamed('itemI18ns', 'propel1_translation_collection', null, array(
           'options' => array(
               'data_class' => self::TRANSLATABLE_I18N_CLASS,
               'columns' => array('value', 'value2'),
           ),
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testNoColumnsAdded()
    {
        $this->factory->createNamed('itemI18ns', 'propel1_translation_collection', null, array(
            'languages' => array('en', 'fr'),
            'options' => array(
                'data_class' => self::TRANSLATABLE_I18N_CLASS,
            ),
        ));
    }
}
