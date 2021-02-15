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

use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Factory\PropertyAccessDecorator;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TransformationFailureExtension;
use Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;
use Symfony\Component\Form\Extension\HttpFoundation\Type\FormTypeHttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\Type\FormTypeValidatorExtension;
use Symfony\Component\Form\Extension\Validator\Type\RepeatedTypeValidatorExtension;
use Symfony\Component\Form\Extension\Validator\Type\SubmitTypeValidatorExtension;
use Symfony\Component\Form\Extension\Validator\Type\UploadValidatorExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorTypeGuesser;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\Form\ResolvedFormTypeFactoryInterface;
use Symfony\Component\Form\Util\ServerParams;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('form.resolved_type_factory', ResolvedFormTypeFactory::class)

        ->alias(ResolvedFormTypeFactoryInterface::class, 'form.resolved_type_factory')

        ->set('form.registry', FormRegistry::class)
            ->args([
                [
                    /*
                     * We don't need to be able to add more extensions.
                     * more types can be registered with the form.type tag
                     * more type extensions can be registered with the form.type_extension tag
                     * more type_guessers can be registered with the form.type_guesser tag
                     */
                    service('form.extension'),
                ],
                service('form.resolved_type_factory'),
            ])

        ->alias(FormRegistryInterface::class, 'form.registry')

        ->set('form.factory', FormFactory::class)
            ->public()
            ->args([service('form.registry')])
            ->tag('container.private', ['package' => 'symfony/framework-bundle', 'version' => '5.2'])

        ->alias(FormFactoryInterface::class, 'form.factory')

        ->set('form.extension', DependencyInjectionExtension::class)
            ->args([
                abstract_arg('All services with tag "form.type" are stored in a service locator by FormPass'),
                abstract_arg('All services with tag "form.type_extension" are stored here by FormPass'),
                abstract_arg('All services with tag "form.type_guesser" are stored here by FormPass'),
            ])

        ->set('form.type_guesser.validator', ValidatorTypeGuesser::class)
            ->args([service('validator.mapping.class_metadata_factory')])
            ->tag('form.type_guesser')

        ->alias('form.property_accessor', 'property_accessor')

        ->set('form.choice_list_factory.default', DefaultChoiceListFactory::class)

        ->set('form.choice_list_factory.property_access', PropertyAccessDecorator::class)
            ->args([
                service('form.choice_list_factory.default'),
                service('form.property_accessor'),
            ])

        ->set('form.choice_list_factory.cached', CachingFactoryDecorator::class)
            ->args([service('form.choice_list_factory.property_access')])
            ->tag('kernel.reset', ['method' => 'reset'])

        ->alias('form.choice_list_factory', 'form.choice_list_factory.cached')

        ->set('form.type.form', FormType::class)
            ->args([service('form.property_accessor')])
            ->tag('form.type')

        ->set('form.type.choice', ChoiceType::class)
            ->args([
                service('form.choice_list_factory'),
                service('translator')->ignoreOnInvalid(),
            ])
            ->tag('form.type')

        ->set('form.type.file', FileType::class)
            ->public()
            ->args([service('translator')->ignoreOnInvalid()])
            ->tag('form.type')
            ->tag('container.private', ['package' => 'symfony/framework-bundle', 'version' => '5.2'])

        ->set('form.type.color', ColorType::class)
            ->args([service('translator')->ignoreOnInvalid()])
            ->tag('form.type')

        ->set('form.type_extension.form.transformation_failure_handling', TransformationFailureExtension::class)
            ->args([service('translator')->ignoreOnInvalid()])
            ->tag('form.type_extension', ['extended-type' => FormType::class])

        ->set('form.type_extension.form.http_foundation', FormTypeHttpFoundationExtension::class)
            ->args([service('form.type_extension.form.request_handler')])
            ->tag('form.type_extension')

        ->set('form.type_extension.form.request_handler', HttpFoundationRequestHandler::class)
            ->args([service('form.server_params')])

        ->set('form.server_params', ServerParams::class)
            ->args([service('request_stack')])

        ->set('form.type_extension.form.validator', FormTypeValidatorExtension::class)
            ->args([
                service('validator'),
                true,
                service('twig.form.renderer')->ignoreOnInvalid(),
                service('translator')->ignoreOnInvalid(),
            ])
            ->tag('form.type_extension', ['extended-type' => FormType::class])

        ->set('form.type_extension.repeated.validator', RepeatedTypeValidatorExtension::class)
            ->tag('form.type_extension')

        ->set('form.type_extension.submit.validator', SubmitTypeValidatorExtension::class)
            ->tag('form.type_extension', ['extended-type' => SubmitType::class])

        ->set('form.type_extension.upload.validator', UploadValidatorExtension::class)
            ->args([
                service('translator'),
                param('validator.translation_domain'),
            ])
            ->tag('form.type_extension')
    ;
};
