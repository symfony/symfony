<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bundle\FrameworkBundle\CacheWarmer\ValidatorCacheWarmer;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraints\AtLeastOneOfValidator;
use Symfony\Component\Validator\Constraints\BicValidator;
use Symfony\Component\Validator\Constraints\BlankValidator;
use Symfony\Component\Validator\Constraints\CardSchemeValidator;
use Symfony\Component\Validator\Constraints\ChoiceValidator;
use Symfony\Component\Validator\Constraints\CidrValidator;
use Symfony\Component\Validator\Constraints\CountryValidator;
use Symfony\Component\Validator\Constraints\CountValidator;
use Symfony\Component\Validator\Constraints\CssColorValidator;
use Symfony\Component\Validator\Constraints\CurrencyValidator;
use Symfony\Component\Validator\Constraints\DateTimeValidator;
use Symfony\Component\Validator\Constraints\DateValidator;
use Symfony\Component\Validator\Constraints\DivisibleByValidator;
use Symfony\Component\Validator\Constraints\EmailValidator;
use Symfony\Component\Validator\Constraints\EqualToValidator;
use Symfony\Component\Validator\Constraints\ExpressionLanguageSyntaxValidator;
use Symfony\Component\Validator\Constraints\ExpressionSyntaxValidator;
use Symfony\Component\Validator\Constraints\ExpressionValidator;
use Symfony\Component\Validator\Constraints\FileValidator;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqualValidator;
use Symfony\Component\Validator\Constraints\GreaterThanValidator;
use Symfony\Component\Validator\Constraints\HostnameValidator;
use Symfony\Component\Validator\Constraints\IbanValidator;
use Symfony\Component\Validator\Constraints\IdenticalToValidator;
use Symfony\Component\Validator\Constraints\ImageValidator;
use Symfony\Component\Validator\Constraints\IpValidator;
use Symfony\Component\Validator\Constraints\IsbnValidator;
use Symfony\Component\Validator\Constraints\IsFalseValidator;
use Symfony\Component\Validator\Constraints\IsinValidator;
use Symfony\Component\Validator\Constraints\IsNullValidator;
use Symfony\Component\Validator\Constraints\IssnValidator;
use Symfony\Component\Validator\Constraints\IsTrueValidator;
use Symfony\Component\Validator\Constraints\JsonValidator;
use Symfony\Component\Validator\Constraints\LanguageValidator;
use Symfony\Component\Validator\Constraints\LengthValidator;
use Symfony\Component\Validator\Constraints\LessThanOrEqualValidator;
use Symfony\Component\Validator\Constraints\LessThanValidator;
use Symfony\Component\Validator\Constraints\LocaleValidator;
use Symfony\Component\Validator\Constraints\LuhnValidator;
use Symfony\Component\Validator\Constraints\NotBlankValidator;
use Symfony\Component\Validator\Constraints\NotCompromisedPasswordValidator;
use Symfony\Component\Validator\Constraints\NotEqualToValidator;
use Symfony\Component\Validator\Constraints\NotIdenticalToValidator;
use Symfony\Component\Validator\Constraints\NotNullValidator;
use Symfony\Component\Validator\Constraints\RangeValidator;
use Symfony\Component\Validator\Constraints\RegexValidator;
use Symfony\Component\Validator\Constraints\TimeValidator;
use Symfony\Component\Validator\Constraints\TimezoneValidator;
use Symfony\Component\Validator\Constraints\TypeValidator;
use Symfony\Component\Validator\Constraints\UlidValidator;
use Symfony\Component\Validator\Constraints\UniqueValidator;
use Symfony\Component\Validator\Constraints\UrlValidator;
use Symfony\Component\Validator\Constraints\UuidValidator;
use Symfony\Component\Validator\Constraints\WhenValidator;
use Symfony\Component\Validator\ContainerConstraintValidatorFactory;
use Symfony\Component\Validator\Mapping\Loader\PropertyInfoLoader;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('validator.mapping.cache.file', param('kernel.cache_dir').'/validation.php');

    $container->services()
        ->set('validator', ValidatorInterface::class)
            ->factory([service('validator.builder'), 'getValidator'])
        ->alias(ValidatorInterface::class, 'validator')

        ->set('validator.builder', ValidatorBuilder::class)
            ->factory([Validation::class, 'createValidatorBuilder'])
            ->call('setConstraintValidatorFactory', [
                service('validator.validator_factory'),
            ])
            ->call('setTranslator', [
                service('translator')->ignoreOnInvalid(),
            ])
            ->call('setTranslationDomain', [
                param('validator.translation_domain'),
            ])
        ->alias('validator.mapping.class_metadata_factory', 'validator')

        ->set('validator.mapping.cache_warmer', ValidatorCacheWarmer::class)
            ->args([
                service('validator.builder'),
                param('validator.mapping.cache.file'),
            ])
            ->tag('kernel.cache_warmer')

        ->set('validator.mapping.cache.adapter', PhpArrayAdapter::class)
            ->factory([PhpArrayAdapter::class, 'create'])
            ->args([
                param('validator.mapping.cache.file'),
                service('cache.validator'),
            ])

        ->set('validator.validator_factory', ContainerConstraintValidatorFactory::class)
            ->args([
                abstract_arg('Constraint validators locator'),
            ])

        ->set('validator.expression', ExpressionValidator::class)
            ->args([service('validator.expression_language')->nullOnInvalid()])
            ->tag('validator.constraint_validator', [
                'alias' => 'validator.expression',
            ])

        ->set('validator.expression_language', ExpressionLanguage::class)
            ->args([service('cache.validator_expression_language')->nullOnInvalid()])

        ->set('cache.validator_expression_language')
            ->parent('cache.system')
            ->tag('cache.pool')

        ->set('validator.email', EmailValidator::class)
            ->args([
                abstract_arg('Default mode'),
            ])
            ->tag('validator.constraint_validator', [
                'alias' => EmailValidator::class,
            ])

        ->set('validator.not_compromised_password', NotCompromisedPasswordValidator::class)
            ->args([
                service('http_client')->nullOnInvalid(),
                param('kernel.charset'),
                false,
            ])
            ->tag('validator.constraint_validator', [
                'alias' => NotCompromisedPasswordValidator::class,
            ])

        ->set('validator.when', WhenValidator::class)
            ->args([service('validator.expression_language')->nullOnInvalid()])
            ->tag('validator.constraint_validator', [
                'alias' => WhenValidator::class,
            ])

        ->set('validator.property_info_loader', PropertyInfoLoader::class)
            ->args([
                service('property_info'),
                service('property_info'),
                service('property_info'),
            ])
            ->tag('validator.auto_mapper')

        ->set('validator.at_least_one_of', AtLeastOneOfValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => AtLeastOneOfValidator::class,
            ])

        ->set('validator.bic', BicValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => BicValidator::class,
            ])

        ->set('validator.blank', BlankValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => BlankValidator::class,
            ])

        ->set('validator.card_scheme', CardSchemeValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => CardSchemeValidator::class,
            ])

        ->set('validator.choice', ChoiceValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => ChoiceValidator::class,
            ])

        ->set('validator.cidr', CidrValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => CidrValidator::class,
            ])

        ->set('validator.count', CountValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => CountValidator::class,
            ])

        ->set('validator.country', CountryValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => CountryValidator::class,
            ])

        ->set('validator.css_color', CssColorValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => CssColorValidator::class,
            ])

        ->set('validator.currency', CurrencyValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => CurrencyValidator::class,
            ])

        ->set('validator.date', DateValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => DateValidator::class,
            ])

        ->set('validator.date_time', DateTimeValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => DateTimeValidator::class,
            ])

        ->set('validator.divisible_by', DivisibleByValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => DivisibleByValidator::class,
            ])

        ->set('validator.equal_to', EqualToValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => EqualToValidator::class,
            ])

        ->set('validator.expression_language_syntax', ExpressionLanguageSyntaxValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => ExpressionLanguageSyntaxValidator::class,
            ])

        ->set('validator.expression_syntax', ExpressionSyntaxValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => ExpressionSyntaxValidator::class,
            ])

        ->set('validator.file', FileValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => FileValidator::class,
            ])

        ->set('validator.greater_than', GreaterThanValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => GreaterThanValidator::class,
            ])

        ->set('validator.greater_than_or_equal', GreaterThanOrEqualValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => GreaterThanOrEqualValidator::class,
            ])

        ->set('validator.hostname', HostnameValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => HostnameValidator::class,
            ])

        ->set('validator.iban', IbanValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => IbanValidator::class,
            ])

        ->set('validator.identical_to', IdenticalToValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => IdenticalToValidator::class,
            ])

        ->set('validator.image', ImageValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => ImageValidator::class,
            ])

        ->set('validator.ip', IpValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => IpValidator::class,
            ])

        ->set('validator.isbn', IsbnValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => IsbnValidator::class,
            ])

        ->set('validator.is_false', IsFalseValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => IsFalseValidator::class,
            ])

        ->set('validator.isin', IsinValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => IsinValidator::class,
            ])

        ->set('validator.is_null', IsNullValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => IsNullValidator::class,
            ])

        ->set('validator.issn', IssnValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => IssnValidator::class,
            ])

        ->set('validator.is_true', IsTrueValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => IsTrueValidator::class,
            ])

        ->set('validator.json', JsonValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => JsonValidator::class,
            ])

        ->set('validator.language', LanguageValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => LanguageValidator::class,
            ])

        ->set('validator.length', LengthValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => LengthValidator::class,
            ])

        ->set('validator.less_than', LessThanValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => LessThanValidator::class,
            ])

        ->set('validator.less_than_or_equal', LessThanOrEqualValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => LessThanOrEqualValidator::class,
            ])

        ->set('validator.locale', LocaleValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => LocaleValidator::class,
            ])

        ->set('validator.luhn', LuhnValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => LuhnValidator::class,
            ])

        ->set('validator.not_blank', NotBlankValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => NotBlankValidator::class,
            ])

        ->set('validator.not_equal_to', NotEqualToValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => NotEqualToValidator::class,
            ])

        ->set('validator.not_identical_to', NotIdenticalToValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => NotIdenticalToValidator::class,
            ])

        ->set('validator.not_null', NotNullValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => NotNullValidator::class,
            ])

        ->set('validator.range', RangeValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => RangeValidator::class,
            ])

        ->set('validator.regex', RegexValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => RegexValidator::class,
            ])

        ->set('validator.time', TimeValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => TimeValidator::class,
            ])

        ->set('validator.timezone', TimezoneValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => TimezoneValidator::class,
            ])

        ->set('validator.type', TypeValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => TypeValidator::class,
            ])

        ->set('validator.ulid', UlidValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => UlidValidator::class,
            ])

        ->set('validator.unique', UniqueValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => UniqueValidator::class,
            ])

        ->set('validator.url', UrlValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => UrlValidator::class,
            ])

        ->set('validator.uuid', UuidValidator::class)
            ->tag('validator.constraint_validator', [
                'alias' => UuidValidator::class,
            ])
    ;
};
