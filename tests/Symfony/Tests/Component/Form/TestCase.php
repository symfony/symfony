<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\Config\FieldConfig;
use Symfony\Component\Form\Config\FormConfig;
use Symfony\Component\Form\Config\CheckboxFieldConfig;
use Symfony\Component\Form\Config\ChoiceFieldConfig;
use Symfony\Component\Form\Config\CollectionFieldConfig;
use Symfony\Component\Form\Config\CountryFieldConfig;
use Symfony\Component\Form\Config\DateFieldConfig;
use Symfony\Component\Form\Config\DateTimeFieldConfig;
use Symfony\Component\Form\Config\EntityFieldConfig;
use Symfony\Component\Form\Config\FileFieldConfig;
use Symfony\Component\Form\Config\HiddenFieldConfig;
use Symfony\Component\Form\Config\IntegerFieldConfig;
use Symfony\Component\Form\Config\LanguageFieldConfig;
use Symfony\Component\Form\Config\LocaleFieldConfig;
use Symfony\Component\Form\Config\MoneyFieldConfig;
use Symfony\Component\Form\Config\NumberFieldConfig;
use Symfony\Component\Form\Config\PasswordFieldConfig;
use Symfony\Component\Form\Config\PercentFieldConfig;
use Symfony\Component\Form\Config\RadioFieldConfig;
use Symfony\Component\Form\Config\RepeatedFieldConfig;
use Symfony\Component\Form\Config\TextareaFieldConfig;
use Symfony\Component\Form\Config\TextFieldConfig;
use Symfony\Component\Form\Config\TimeFieldConfig;
use Symfony\Component\Form\Config\TimezoneFieldConfig;
use Symfony\Component\Form\Config\UrlFieldConfig;

class TestCase extends \PHPUnit_Framework_TestCase
{
    protected $theme;

    protected $csrfProvider;

    protected $validator;

    protected $fieldFactory;

    protected $storage;

    private $em;

    protected $factory;

    protected function setUp()
    {
        $this->theme = $this->getMock('Symfony\Component\Form\Renderer\Theme\ThemeInterface');
        $this->csrfProvider = $this->getMock('Symfony\Component\Form\CsrfProvider\CsrfProviderInterface');
        $this->validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');
        $this->fieldFactory = $this->getMock('Symfony\Component\Form\FieldFactory\FieldFactoryInterface');
        $this->storage = $this->getMockBuilder('Symfony\Component\HttpFoundation\File\TemporaryStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->factory = new FormFactory();
        $this->factory->addConfig(new FieldConfig($this->theme));
        $this->factory->addConfig(new FormConfig($this->csrfProvider, $this->fieldFactory, $this->validator));
        $this->factory->addConfig(new CheckboxFieldConfig());
        $this->factory->addConfig(new ChoiceFieldConfig());
        $this->factory->addConfig(new CollectionFieldConfig());
        $this->factory->addConfig(new CountryFieldConfig());
        $this->factory->addConfig(new DateFieldConfig());
        $this->factory->addConfig(new DateTimeFieldConfig());
        $this->factory->addConfig(new EntityFieldConfig($this->em));
        $this->factory->addConfig(new FileFieldConfig($this->storage));
        $this->factory->addConfig(new HiddenFieldConfig());
        $this->factory->addConfig(new IntegerFieldConfig());
        $this->factory->addConfig(new LanguageFieldConfig());
        $this->factory->addConfig(new LocaleFieldConfig());
        $this->factory->addConfig(new MoneyFieldConfig());
        $this->factory->addConfig(new NumberFieldConfig());
        $this->factory->addConfig(new PasswordFieldConfig());
        $this->factory->addConfig(new PercentFieldConfig());
        $this->factory->addConfig(new RadioFieldConfig());
        $this->factory->addConfig(new RepeatedFieldConfig());
        $this->factory->addConfig(new TextareaFieldConfig());
        $this->factory->addConfig(new TextFieldConfig());
        $this->factory->addConfig(new TimeFieldConfig());
        $this->factory->addConfig(new TimezoneFieldConfig());
        $this->factory->addConfig(new UrlFieldConfig());
    }
}