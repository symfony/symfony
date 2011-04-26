<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core;

use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\TemporaryStorage;

/**
 * Represents the main form extension, which loads the core functionality.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class CoreExtension extends AbstractExtension
{
    private $storage;

    public function __construct(TemporaryStorage $storage)
    {
        $this->storage = $storage;
    }

    protected function loadTypes()
    {
        return array(
            new Type\FieldType(),
            new Type\FormType(),
            new Type\BirthdayType(),
            new Type\CheckboxType(),
            new Type\ChoiceType(),
            new Type\CollectionType(),
            new Type\CountryType(),
            new Type\DateType(),
            new Type\DateTimeType(),
            new Type\EmailType(),
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
            new Type\FileType($this->storage),
        );
    }
}
