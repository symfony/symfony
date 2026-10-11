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

use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\ExpressionLanguage\ConstantFunctionProvider;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\CacheWarmer\ValidatorCacheWarmer;
use Symfony\Component\Validator\Constraints\EmailValidator;
use Symfony\Component\Validator\Constraints\EqualToValidator;
use Symfony\Component\Validator\Constraints\ExpressionLanguageProvider;
use Symfony\Component\Validator\Constraints\ExpressionValidator;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqualValidator;
use Symfony\Component\Validator\Constraints\GreaterThanValidator;
use Symfony\Component\Validator\Constraints\IdenticalToValidator;
use Symfony\Component\Validator\Constraints\LessThanOrEqualValidator;
use Symfony\Component\Validator\Constraints\LessThanValidator;
use Symfony\Component\Validator\Constraints\NoSuspiciousCharactersValidator;
use Symfony\Component\Validator\Constraints\NotCompromisedPasswordValidator;
use Symfony\Component\Validator\Constraints\NotEqualToValidator;
use Symfony\Component\Validator\Constraints\NotIdenticalToValidator;
use Symfony\Component\Validator\Constraints\PathAvailableValidator;
use Symfony\Component\Validator\Constraints\RangeValidator;
use Symfony\Component\Validator\Constraints\WhenValidator;
use Symfony\Component\Validator\ContainerConstraintValidatorFactory;
use Symfony\Component\Validator\Mapping\Loader\PropertyInfoLoader;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('validator.mapping.cache.file', '%kernel.build_dir%/validation.php');

    $validatorsDir = \dirname((new \ReflectionClass(EmailValidator::class))->getFileName());

    $container->services()
        ->set('validator', ValidatorInterface::class)
            ->factory([service('validator.builder'), 'getValidator'])
        ->alias(ValidatorInterface::class, 'validator')

        ->set('validator.builder', ValidatorBuilder::class)
            ->factory([Validation::class, 'createValidatorBuilder'])
            ->call('setConstraintValidatorFactory', [
                service('validator.validator_factory'),
            ])
            ->call('setGroupProviderLocator', [
                tagged_locator('validator.group_provider'),
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

        ->set('cache.validator')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')
            ->tag('container.remove_if_missing', ['service' => 'cache.system'])

        ->set('validator.mapping.cache.adapter', PhpArrayAdapter::class)
            ->factory([PhpArrayAdapter::class, 'create'])
            ->args([
                param('validator.mapping.cache.file'),
                service('cache.validator'),
            ])
            ->tag('container.remove_if_missing', ['service' => 'cache.system'])

        ->set('validator.validator_factory', ContainerConstraintValidatorFactory::class)
            ->args([
                abstract_arg('Constraint validators locator'),
            ])

        // lists the built-in constraints for the translation extractor; as excluded definitions, they are never instantiated
        ->load('Symfony\Component\Validator\Constraints\\', $validatorsDir.'/*Validator.php')
            ->abstract()
            ->tag('container.excluded')
            ->tag('validator.constraint_validator')

        ->set('validator.expression', ExpressionValidator::class)
            ->args([service('validator.expression_language')->nullOnInvalid()])
            ->tag('validator.constraint_validator', [
                'alias' => 'validator.expression',
            ])

        ->set('validator.expression_language', ExpressionLanguage::class)
            ->args([
                service('cache.validator_expression_language')->nullOnInvalid(),
                class_exists(ConstantFunctionProvider::class) ? [inline_service(ConstantFunctionProvider::class)->args([['*', '*::*']])] : [],
            ])
            ->call('registerProvider', [
                service('validator.expression_language_provider')->ignoreOnInvalid(),
            ])

        ->set('cache.validator_expression_language')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')
            ->tag('container.remove_if_missing', ['service' => 'cache.system'])

        ->set('validator.expression_language_provider', ExpressionLanguageProvider::class)

        ->set('validator.email', EmailValidator::class)
            ->args([
                abstract_arg('Default mode'),
            ])
            ->tag('validator.constraint_validator')

        ->set('validator.not_compromised_password', NotCompromisedPasswordValidator::class)
            ->args([
                service('http_client')->nullOnInvalid(),
                param('kernel.charset'),
                false,
            ])
            ->tag('validator.constraint_validator')

        ->set('validator.when', WhenValidator::class)
            ->args([service('validator.expression_language')->nullOnInvalid()])
            ->tag('validator.constraint_validator')

        ->set('validator.no_suspicious_characters', NoSuspiciousCharactersValidator::class)
            ->args([param('kernel.enabled_locales')])
            ->tag('validator.constraint_validator', [
                'alias' => NoSuspiciousCharactersValidator::class,
            ])

        ->set('validator.equal_to', EqualToValidator::class)
            ->args([null, service('clock')->nullOnInvalid()])
            ->tag('validator.constraint_validator')

        ->set('validator.not_equal_to', NotEqualToValidator::class)
            ->args([null, service('clock')->nullOnInvalid()])
            ->tag('validator.constraint_validator')

        ->set('validator.identical_to', IdenticalToValidator::class)
            ->args([null, service('clock')->nullOnInvalid()])
            ->tag('validator.constraint_validator')

        ->set('validator.not_identical_to', NotIdenticalToValidator::class)
            ->args([null, service('clock')->nullOnInvalid()])
            ->tag('validator.constraint_validator')

        ->set('validator.less_than', LessThanValidator::class)
            ->args([null, service('clock')->nullOnInvalid()])
            ->tag('validator.constraint_validator')

        ->set('validator.less_than_or_equal', LessThanOrEqualValidator::class)
            ->args([null, service('clock')->nullOnInvalid()])
            ->tag('validator.constraint_validator')

        ->set('validator.greater_than', GreaterThanValidator::class)
            ->args([null, service('clock')->nullOnInvalid()])
            ->tag('validator.constraint_validator')

        ->set('validator.greater_than_or_equal', GreaterThanOrEqualValidator::class)
            ->args([null, service('clock')->nullOnInvalid()])
            ->tag('validator.constraint_validator')

        ->set('validator.range', RangeValidator::class)
            ->args([null, service('clock')->nullOnInvalid()])
            ->tag('validator.constraint_validator')

        ->set('validator.property_info_loader', PropertyInfoLoader::class)
            ->args([
                service('property_info'),
                service('property_info'),
                service('property_info'),
            ])
            ->tag('validator.auto_mapper')
            ->tag('container.remove_if_missing', ['service' => 'property_info'])

        ->set('validator.form.attribute_metadata', Form::class)
            ->tag('container.excluded')
            ->tag('validator.attribute_metadata')

        ->set('validator.path_available', PathAvailableValidator::class)
            ->args([
                service('router'),
            ])
            ->tag('validator.constraint_validator')
            ->tag('container.remove_if_missing', ['service' => 'router'])
    ;
};
