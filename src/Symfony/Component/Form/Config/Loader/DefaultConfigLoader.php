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
        $this->addConfig(new Config\FieldConfig($csrfProvider, $theme, $validator));
        $this->addConfig(new Config\FormConfig());
        $this->addConfig(new Config\CheckboxFieldConfig());
        $this->addConfig(new Config\ChoiceFieldConfig());
        $this->addConfig(new Config\CollectionFieldConfig());
        $this->addConfig(new Config\CountryFieldConfig());
        $this->addConfig(new Config\DateFieldConfig());
        $this->addConfig(new Config\DateTimeFieldConfig());
        $this->addConfig(new Config\FileFieldConfig($storage));
        $this->addConfig(new Config\HiddenFieldConfig());
        $this->addConfig(new Config\IntegerFieldConfig());
        $this->addConfig(new Config\LanguageFieldConfig());
        $this->addConfig(new Config\LocaleFieldConfig());
        $this->addConfig(new Config\MoneyFieldConfig());
        $this->addConfig(new Config\NumberFieldConfig());
        $this->addConfig(new Config\PasswordFieldConfig());
        $this->addConfig(new Config\PercentFieldConfig());
        $this->addConfig(new Config\RadioFieldConfig());
        $this->addConfig(new Config\RepeatedFieldConfig());
        $this->addConfig(new Config\TextareaFieldConfig());
        $this->addConfig(new Config\TextFieldConfig());
        $this->addConfig(new Config\TimeFieldConfig());
        $this->addConfig(new Config\TimezoneFieldConfig());
        $this->addConfig(new Config\UrlFieldConfig());

        if (null !== $em) {
            $this->addConfig(new Config\EntityFieldConfig($em));
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