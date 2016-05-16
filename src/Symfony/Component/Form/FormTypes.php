<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * To learn more about how form types work check the documentation
 * entry at {@link http://symfony.com/doc/2.8/reference/forms/types.html}.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
final class FormTypes
{
    const BIRTHDAY = 'Symfony\Component\Form\Extension\Core\Type\BirthdayType';

    const BUTTON = 'Symfony\Component\Form\Extension\Core\Type\ButtonType';

    const CHECKBOX = 'Symfony\Component\Form\Extension\Core\Type\CheckboxType';

    const CHOICE = 'Symfony\Component\Form\Extension\Core\Type\ChoiceType';

    const COLLECTION = 'Symfony\Component\Form\Extension\Core\Type\CollectionType';

    const COUNTRY = 'Symfony\Component\Form\Extension\Core\Type\CountryType';

    const CURRENCY = 'Symfony\Component\Form\Extension\Core\Type\CurrencyType';

    const DATETIME = 'Symfony\Component\Form\Extension\Core\Type\DateTimeType';

    const DATE = 'Symfony\Component\Form\Extension\Core\Type\DateType';

    const EMAIL = 'Symfony\Component\Form\Extension\Core\Type\EmailType';

    const FILE = 'Symfony\Component\Form\Extension\Core\Type\FileType';

    const FORM = 'Symfony\Component\Form\Extension\Core\Type\FormType';

    const HIDDEN = 'Symfony\Component\Form\Extension\Core\Type\HiddenType';

    const INTEGER = 'Symfony\Component\Form\Extension\Core\Type\IntegerType';

    const LANGUAGE = 'Symfony\Component\Form\Extension\Core\Type\LanguageType';

    const LOCALE = 'Symfony\Component\Form\Extension\Core\Type\LocaleType';

    const MONEY = 'Symfony\Component\Form\Extension\Core\Type\MoneyType';

    const NUMBER = 'Symfony\Component\Form\Extension\Core\Type\NumberType';

    const PASSWORD = 'Symfony\Component\Form\Extension\Core\Type\PasswordType';

    const PERCENT = 'Symfony\Component\Form\Extension\Core\Type\PercentType';

    const RADIO = 'Symfony\Component\Form\Extension\Core\Type\RadioType';

    const RANGE = 'Symfony\Component\Form\Extension\Core\Type\RangeType';

    const REPEAT = 'Symfony\Component\Form\Extension\Core\Type\RepeatType';

    const RESET = 'Symfony\Component\Form\Extension\Core\Type\ResetType';

    const SEARCH = 'Symfony\Component\Form\Extension\Core\Type\SearchType';

    const SUBMIT = 'Symfony\Component\Form\Extension\Core\Type\SubmitType';

    const TEXTAREA = 'Symfony\Component\Form\Extension\Core\Type\TextareaType';

    const TEXT = 'Symfony\Component\Form\Extension\Core\Type\TextType';

    const TIME = 'Symfony\Component\Form\Extension\Core\Type\TimeType';

    const TIMEZONE = 'Symfony\Component\Form\Extension\Core\Type\TimezoneType';

    const URL = 'Symfony\Component\Form\Extension\Core\Type\UrlType';

    private function __construct()
    {
    }
}
