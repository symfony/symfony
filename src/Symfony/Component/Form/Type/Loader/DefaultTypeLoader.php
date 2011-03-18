<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type\Loader;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Type;
use Symfony\Component\Form\Type\FieldTypeInterface;
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\TemporaryStorage;
use Doctrine\ORM\EntityManager;

class DefaultTypeLoader implements TypeLoaderInterface
{
    private $types = array();

    public function initialize(FormFactoryInterface $factory,
            ThemeInterface $theme, CsrfProviderInterface $csrfProvider,
            ValidatorInterface $validator, TemporaryStorage $storage,
            EntityManager $em = null)
    {
        $this->addType(new Type\FieldType($csrfProvider, $theme, $validator));
        $this->addType(new Type\FormType());
        $this->addType(new Type\CheckboxFieldType());
        $this->addType(new Type\ChoiceFieldType());
        $this->addType(new Type\CollectionFieldType());
        $this->addType(new Type\CountryFieldType());
        $this->addType(new Type\DateFieldType());
        $this->addType(new Type\DateTimeFieldType());
        $this->addType(new Type\FileFieldType($storage));
        $this->addType(new Type\HiddenFieldType());
        $this->addType(new Type\IntegerFieldType());
        $this->addType(new Type\LanguageFieldType());
        $this->addType(new Type\LocaleFieldType());
        $this->addType(new Type\MoneyFieldType());
        $this->addType(new Type\NumberFieldType());
        $this->addType(new Type\PasswordFieldType());
        $this->addType(new Type\PercentFieldType());
        $this->addType(new Type\RadioFieldType());
        $this->addType(new Type\RepeatedFieldType());
        $this->addType(new Type\TextareaFieldType());
        $this->addType(new Type\TextFieldType());
        $this->addType(new Type\TimeFieldType());
        $this->addType(new Type\TimezoneFieldType());
        $this->addType(new Type\UrlFieldType());

        if (null !== $em) {
            $this->addType(new Type\EntityFieldType($em));
        }
    }

    public function addType(FieldTypeInterface $type)
    {
        $this->types[$type->getName()] = $type;
    }

    public function getType($name)
    {
        return $this->types[$name];
    }

    public function hasType($name)
    {
        return isset($this->types[$name]);
    }
}