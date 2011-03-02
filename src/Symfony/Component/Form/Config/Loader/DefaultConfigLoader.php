<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Config\Loader;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Config;
use Symfony\Component\Form\Config\FieldConfigInterface;
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\TemporaryStorage;
use Doctrine\ORM\EntityManager;

class DefaultConfigLoader implements ConfigLoaderInterface
{
    private $configs = array();

    public function initialize(FormFactoryInterface $factory,
            ThemeInterface $theme, CsrfProviderInterface $csrfProvider,
            ValidatorInterface $validator, TemporaryStorage $storage,
            EntityManager $em = null)
    {
        $this->addConfig(new Config\FieldConfig($factory, $theme));
        $this->addConfig(new Config\FormConfig($factory, $csrfProvider, $validator));
        $this->addConfig(new Config\CheckboxFieldConfig($factory));
        $this->addConfig(new Config\ChoiceFieldConfig($factory));
        $this->addConfig(new Config\CollectionFieldConfig($factory));
        $this->addConfig(new Config\CountryFieldConfig($factory));
        $this->addConfig(new Config\DateFieldConfig($factory));
        $this->addConfig(new Config\DateTimeFieldConfig($factory));
        $this->addConfig(new Config\FileFieldConfig($factory, $storage));
        $this->addConfig(new Config\HiddenFieldConfig($factory));
        $this->addConfig(new Config\IntegerFieldConfig($factory));
        $this->addConfig(new Config\LanguageFieldConfig($factory));
        $this->addConfig(new Config\LocaleFieldConfig($factory));
        $this->addConfig(new Config\MoneyFieldConfig($factory));
        $this->addConfig(new Config\NumberFieldConfig($factory));
        $this->addConfig(new Config\PasswordFieldConfig($factory));
        $this->addConfig(new Config\PercentFieldConfig($factory));
        $this->addConfig(new Config\RadioFieldConfig($factory));
        $this->addConfig(new Config\RepeatedFieldConfig($factory));
        $this->addConfig(new Config\TextareaFieldConfig($factory));
        $this->addConfig(new Config\TextFieldConfig($factory));
        $this->addConfig(new Config\TimeFieldConfig($factory));
        $this->addConfig(new Config\TimezoneFieldConfig($factory));
        $this->addConfig(new Config\UrlFieldConfig($factory));

        if (null !== $em) {
            $this->addConfig(new Config\EntityFieldConfig($factory, $em));
        }
    }

    public function addConfig(FieldConfigInterface $config)
    {
        $this->configs[$config->getIdentifier()] = $config;
    }

    public function getConfig($identifier)
    {
        return $this->configs[$identifier];
    }

    public function hasConfig($identifier)
    {
        return isset($this->configs[$identifier]);
    }
}