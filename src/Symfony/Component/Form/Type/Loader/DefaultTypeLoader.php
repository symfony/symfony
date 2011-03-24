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
use Symfony\Component\Form\Type\FormTypeInterface;
use Symfony\Component\Form\Renderer\Theme\FormThemeFactoryInterface;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\TemporaryStorage;

class DefaultTypeLoader extends ArrayTypeLoader
{
    public function __construct(
            FormThemeFactoryInterface $themeFactory, $template = null, ValidatorInterface $validator = null,
            CsrfProviderInterface $csrfProvider = null, TemporaryStorage $storage = null)
    {
        $types = array(
            new Type\FieldType($validator, $themeFactory, $template),
            new Type\FormType(),
            new Type\BirthdayType(),
            new Type\CheckboxType(),
            new Type\ChoiceType(),
            new Type\CollectionType(),
            new Type\CountryType(),
            new Type\DateType(),
            new Type\DateTimeType(),
            new Type\HiddenType(),
            new Type\IntegerType(),
            new Type\LanguageType(),
            new Type\LocaleType(),
            new Type\MoneyType(),
            new Type\NumberType(),
            new Type\PasswordType(),
            new Type\PercentType(),
            new Type\RadioType(),
            new Type\RepeatedType(),
            new Type\TextareaType(),
            new Type\TextType(),
            new Type\TimeType(),
            new Type\TimezoneType(),
            new Type\UrlType(),
        );

        if ($csrfProvider) {
            // TODO Move to a Symfony\Bridge\FormSecurity
            $types[] = new Type\CsrfType($csrfProvider);
        }

        if ($storage) {
            $types[] = new Type\FileType($storage);
        }

        parent::__construct($types);
    }
}
