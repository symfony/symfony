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
use Symfony\Component\Form\Renderer\Theme\FormThemeInterface;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\TemporaryStorage;
use Doctrine\ORM\EntityManager;

class DefaultTypeLoader implements TypeLoaderInterface
{
    private $types = array();

    public function initialize(FormFactoryInterface $factory,
            FormThemeInterface $theme, CsrfProviderInterface $csrfProvider,
            ValidatorInterface $validator, TemporaryStorage $storage,
            EntityManager $em = null)
    {
        $this->addType(new Type\FieldType($theme, $validator));
        $this->addType(new Type\FormType());
        $this->addType(new Type\BirthdayType());
        $this->addType(new Type\CheckboxType());
        $this->addType(new Type\ChoiceType());
        $this->addType(new Type\CollectionType());
        $this->addType(new Type\CountryType());
        $this->addType(new Type\CsrfType($csrfProvider));
        $this->addType(new Type\DateType());
        $this->addType(new Type\DateTimeType());
        $this->addType(new Type\FileType($storage));
        $this->addType(new Type\HiddenType());
        $this->addType(new Type\IntegerType());
        $this->addType(new Type\LanguageType());
        $this->addType(new Type\LocaleType());
        $this->addType(new Type\MoneyType());
        $this->addType(new Type\NumberType());
        $this->addType(new Type\PasswordType());
        $this->addType(new Type\PercentType());
        $this->addType(new Type\RadioType());
        $this->addType(new Type\RepeatedType());
        $this->addType(new Type\TextareaType());
        $this->addType(new Type\TextType());
        $this->addType(new Type\TimeType());
        $this->addType(new Type\TimezoneType());
        $this->addType(new Type\UrlType());

        if (null !== $em) {
            $this->addType(new Type\EntityType($em));
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
