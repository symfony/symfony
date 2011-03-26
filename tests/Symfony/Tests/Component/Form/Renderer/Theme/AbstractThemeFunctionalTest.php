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

use Symfony\Component\Form\Type\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\CsrfProvider\DefaultCsrfProvider;
use Symfony\Component\Form\Type\Loader\DefaultTypeLoader;
use Symfony\Component\Form\Type\Loader\ArrayTypeLoader;
use Symfony\Component\Form\Type\Loader\TypeLoaderChain;
use Symfony\Component\Form\FormFactory;

/**
 * Test theme template files shipped with framework bundle.
 */
abstract class AbstractThemeFunctionalTest extends \PHPUnit_Framework_TestCase
{
    /** @var FormFactory */
    private $factory;

    abstract protected function createThemeFactory();

    protected function getTemplate()
    {
    }

    public function setUp()
    {
        $themeFactory = $this->createThemeFactory();
        $template = $this->getTemplate();
        $csrfProvider = new DefaultCsrfProvider('foo');
        $validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');
        $rendererFactoryLoader = $this->getMock('Symfony\Component\Form\Renderer\Loader\FormRendererFactoryLoaderInterface');
        $storage = new \Symfony\Component\HttpFoundation\File\TemporaryStorage('foo', 1, \sys_get_temp_dir());

        // ok more than 2 lines, see DefaultFormFactory.php for proposed simplication
        $loaderChain = new TypeLoaderChain();
        $loaderChain->addLoader(new DefaultTypeLoader($themeFactory, $template, $validator, $csrfProvider , $storage));
        $loaderChain->addLoader(new ArrayTypeLoader(array(
            new MyTestFormConfig(),
            new MyTestSubFormConfig()
        )));
        $this->factory = new FormFactory($loaderChain, $rendererFactoryLoader);
    }

    public function testFullFormRendering()
    {
        $this->markTestSkipped('Fix me');

        \Locale::setDefault('en');

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
            'input#form_field0_subfield0',
            'input#form_field1',
            'select#form_field2_month',
            'select#form_field2_day',
            'select#form_field2_year',
            'select#form_field5_hour',
            'select#form_field5_minute',
            'input#form_field3_active',
            'input#form_field3_inactive',
            'select#form_field21',
            'select#form_field22',
            'select#form_field4_date_month',
            'select#form_field4_date_day',
            'select#form_field4_date_year',
            'select#form_field4_time_hour',
            'select#form_field4_time_minute',
            'select#form_field6_month',
            'select#form_field6_day',
            'select#form_field6_year',
            'input#form_field7',
            'input#form_field8_file',
            'input#form_field8_token',
            'input#form_field8_name',
            'input#form_field10',
            'select#form_field11',
            'select#form_field12',
            'input#form_field13',
            'input#form_field14',
            'input#form_field15',
            'input#form_field16',
            'input#form_field17',
            'input#form_field18_first',
            'input#form_field18_second',
            'select#form_field19',
            'input#form_field20',
            ), $ids);
    }
}

class MyTestFormConfig extends AbstractType
{
    public function configure(FormBuilder $builder, array $options)
    {
        $builder->setDataClass('Symfony\Bundle\FrameworkBundle\Tests\Form\MyTestObject');
        $builder->add('field0', 'my.sub_form');
        $builder->add('field1', 'text', array('max_length' => 127));
        $builder->add('field2', 'date');
        $builder->add('field5', 'time');
        $builder->add('field3', 'choice', array(
            'expanded' => true,
            'multiple' => false,
            'choices' => array('active' => 'Active', 'inactive' => 'Inactive'),
        ));
        $builder->add('field21', 'choice', array(
            'expanded' => false,
            'multiple' => false,
            'choices' => array('active' => 'Active', 'inactive' => 'Inactive'),
        ));
        $builder->add('field22', 'choice', array(
            'expanded' => false,
            'multiple' => false,
            'choices' => array('active' => 'Active', 'inactive' => 'Inactive'),
            'preferred_choices' => array('active')
        ));
        $builder->add('field4', 'datetime');
        $builder->add('field6', 'birthday');
        $builder->add('field7', 'checkbox');
        $builder->add('field8', 'file');
        $builder->add('field9', 'hidden');
        $builder->add('field10', 'integer');
        $builder->add('field11', 'language');
        $builder->add('field12', 'locale');
        $builder->add('field13', 'money');
        $builder->add('field14', 'number');
        $builder->add('field15', 'password');
        $builder->add('field16', 'percent');
        $builder->add('field17', 'radio');
        $builder->add('field18', 'repeated', array('type' => 'password'));
        $builder->add('emails', 'collection', array(
            'type' => 'text',
        ));
        $builder->add('field19', 'timezone');
        $builder->add('field20', 'url');
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
    private $emails = 'test, foo, bar';
}

class MyTestSubFormConfig extends AbstractType
{
    public function configure(FormBuilder $builder, array $options)
    {
        $builder->add('subfield0', 'text');
    }

    public function getName()
    {
        return 'my.sub_form';
    }

    public function getParent(array $options) {
        return 'form';
    }
}
