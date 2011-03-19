<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Renderer\Theme;

use Symfony\Component\Form\Type\AbstractFieldType;
use Symfony\Component\Form\FieldBuilder;
use Symfony\Component\Form\CsrfProvider\DefaultCsrfProvider;
use Symfony\Component\Form\Type\Loader\DefaultTypeLoader;
use Symfony\Component\Form\FormFactory;

/**
 * Test theme template files shipped with framework bundle.
 */
abstract class AbstractThemeFunctionalTest extends \PHPUnit_Framework_TestCase
{
    /** @var FormFactory */
    private $factory;

    abstract protected function createTheme();

    public function setUp()
    {
        $theme = $this->createTheme();
        $csrfProvider = new DefaultCsrfProvider('foo');
        $validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');
        $storage = new \Symfony\Component\HttpFoundation\File\TemporaryStorage('foo', 1, \sys_get_temp_dir());

        // ok more than 2 lines, see DefaultFormFactory.php for proposed simplication
        $typeLoader = new DefaultTypeLoader();
        $this->factory = new FormFactory($typeLoader);
        $typeLoader->initialize($this->factory, $theme, $csrfProvider, $validator , $storage);
        // this is the relevant bit about your own forms:
        $typeLoader->addType(new MyTestFormConfig());
        $typeLoader->addType(new MyTestSubFormConfig());
    }

    public function testFullFormRendering()
    {
        $form = $this->factory->create('my.form');
        $html = $form->getRenderer()->getWidget();

        libxml_use_internal_errors(true);
        $dom = new \DomDocument('UTF-8');
        $dom->loadHtml($html);

        $xpath = new \DomXpath($dom);
        $ids = array();
        foreach ($xpath->evaluate('//*[@id]') as $node) {
            $ids[] = $node->tagName . "#" . $node->getAttribute('id');
        }
        libxml_use_internal_errors(false);
        $this->assertEquals(array (
            'input#my.form_field0_subfield0',
            'input#my.form_field1',
            'select#my.form_field2_month',
            'select#my.form_field2_day',
            'select#my.form_field2_year',
            'select#my.form_field5_hour',
            'select#my.form_field5_minute',
            'input#my.form_field3_active',
            'input#my.form_field3_inactive',
            'select#my.form_field21',
            'select#my.form_field22',
            'select#my.form_field4_date_month',
            'select#my.form_field4_date_day',
            'select#my.form_field4_date_year',
            'select#my.form_field4_time_hour',
            'select#my.form_field4_time_minute',
            'select#my.form_field6_month',
            'select#my.form_field6_day',
            'select#my.form_field6_year',
            'input#my.form_field7',
            'input#my.form_field8_file',
            'input#my.form_field8_token',
            'input#my.form_field8_name',
            'input#my.form_field10',
            'select#my.form_field11',
            'select#my.form_field12',
            'input#my.form_field13',
            'input#my.form_field14',
            'input#my.form_field15',
            'input#my.form_field16',
            'input#my.form_field17',
            'input#my.form_field18_first',
            'input#my.form_field18_second',
            'select#my.form_field19',
            'input#my.form_field20',
            ), $ids);
    }
}

class MyTestFormConfig extends AbstractFieldType
{
    public function configure(FieldBuilder $builder, array $options)
    {
        $builder->setDataClass('Symfony\Bundle\FrameworkBundle\Tests\Form\MyTestObject');
        $builder->add('my.sub_form', 'field0');
        $builder->add('text', 'field1', array('max_length' => 127, 'id' => 'foo'));
        $builder->add('date', 'field2');
        $builder->add('time', 'field5');
        $builder->add('choice', 'field3', array(
            'expanded' => true,
            'multiple' => false,
            'choices' => array('active' => 'Active', 'inactive' => 'Inactive'),
        ));
        $builder->add('choice', 'field21', array(
            'expanded' => false,
            'multiple' => false,
            'choices' => array('active' => 'Active', 'inactive' => 'Inactive'),
        ));
        $builder->add('choice', 'field22', array(
            'expanded' => false,
            'multiple' => false,
            'choices' => array('active' => 'Active', 'inactive' => 'Inactive'),
            'preferred_choices' => array('active')
        ));
        $builder->add('datetime', 'field4');
        $builder->add('birthday', 'field6');
        $builder->add('checkbox', 'field7');
        $builder->add('file', 'field8');
        $builder->add('hidden', 'field9');
        $builder->add('integer', 'field10');
        $builder->add('language', 'field11');
        $builder->add('locale', 'field12');
        $builder->add('money', 'field13');
        $builder->add('number', 'field14');
        $builder->add('password', 'field15');
        $builder->add('percent', 'field16');
        $builder->add('radio', 'field17');
        $builder->add('repeated', 'field18', array('identifier' => 'password'));
        $builder->add('collection', 'emails', array(
            'prototype' => 'text',
        ));
        $builder->add('timezone', 'field19');
        $builder->add('url', 'field20');
    }

    public function getName()
    {
        return 'my.form';
    }

    public function getParent(array $options) {
        return 'form';
    }
}

class MyTestObject
{
    private $emails = 'test,foo,bar';
}

class MyTestSubFormConfig extends AbstractFieldType
{
    public function configure(FieldBuilder $builder, array $options)
    {
        $builder->add('text', 'subfield0');
    }

    public function getName()
    {
        return 'my.sub_form';
    }

    public function getParent(array $options) {
        return 'form';
    }
}