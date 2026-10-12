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

use Symfony\Bundle\FrameworkBundle\Command\AboutCommand;
use Symfony\Bundle\FrameworkBundle\Command\AssetsInstallCommand;
use Symfony\Bundle\FrameworkBundle\Command\CacheClearCommand;
use Symfony\Bundle\FrameworkBundle\Command\CacheWarmupCommand;
use Symfony\Bundle\FrameworkBundle\Command\ConfigDebugCommand;
use Symfony\Bundle\FrameworkBundle\Command\ConfigDumpReferenceCommand;
use Symfony\Bundle\FrameworkBundle\Command\ContainerDebugCommand;
use Symfony\Bundle\FrameworkBundle\Command\ContainerLintCommand;
use Symfony\Bundle\FrameworkBundle\Command\DebugAutowiringCommand;
use Symfony\Bundle\FrameworkBundle\Command\DebugCommand;
use Symfony\Bundle\FrameworkBundle\Command\EventDispatcherDebugCommand;
use Symfony\Bundle\FrameworkBundle\Command\RouterDebugCommand;
use Symfony\Bundle\FrameworkBundle\Command\SecretsDecryptToLocalCommand;
use Symfony\Bundle\FrameworkBundle\Command\SecretsEncryptFromLocalCommand;
use Symfony\Bundle\FrameworkBundle\Command\SecretsGenerateKeysCommand;
use Symfony\Bundle\FrameworkBundle\Command\SecretsListCommand;
use Symfony\Bundle\FrameworkBundle\Command\SecretsRemoveCommand;
use Symfony\Bundle\FrameworkBundle\Command\SecretsRevealCommand;
use Symfony\Bundle\FrameworkBundle\Command\SecretsSetCommand;
use Symfony\Bundle\FrameworkBundle\Command\YamlLintCommand;
use Symfony\Bundle\FrameworkBundle\Command\YamlLintSchemaResolver;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Debug\Section\AssetMapperDebugSection;
use Symfony\Bundle\FrameworkBundle\Debug\Section\ConfigDebugSection;
use Symfony\Bundle\FrameworkBundle\Debug\Section\ContainerDebugSection;
use Symfony\Bundle\FrameworkBundle\Debug\Section\EventDispatcherDebugSection;
use Symfony\Bundle\FrameworkBundle\Debug\Section\FormDebugSection;
use Symfony\Bundle\FrameworkBundle\Debug\Section\MessengerDebugSection;
use Symfony\Bundle\FrameworkBundle\Debug\Section\PlaceholderDebugSection;
use Symfony\Bundle\FrameworkBundle\Debug\Section\RouterDebugSection;
use Symfony\Bundle\FrameworkBundle\Debug\Section\SchedulerDebugSection;
use Symfony\Bundle\FrameworkBundle\Debug\Section\SerializerDebugSection;
use Symfony\Bundle\FrameworkBundle\Debug\Section\TranslationDebugSection;
use Symfony\Bundle\FrameworkBundle\Debug\Section\ValidatorDebugSection;
use Symfony\Bundle\FrameworkBundle\EventListener\SuggestMissingPackageSubscriber;
use Symfony\Component\Console\EventListener\ValidateQuestionInputListener;
use Symfony\Component\Console\Messenger\RunCommandMessageHandler;
use Symfony\Component\ErrorHandler\Command\ErrorDumpCommand;
use Symfony\Component\Form\Command\DebugCommand as FormDebugCommand;
use Symfony\Component\HttpKernel\Command\ExpressionLintCommand;
use Symfony\Component\Serializer\Command\DebugCommand as SerializerDebugCommand;
use Symfony\Component\Translation\Command\XliffLintCommand;
use Symfony\Component\Validator\Command\DebugCommand as ValidatorDebugCommand;
use Symfony\Component\Yaml\Schema\FileHeaderSchemaResolver;
use Symfony\Component\Yaml\Schema\SchemaValidator;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('console.suggest_missing_package_subscriber', SuggestMissingPackageSubscriber::class)
            ->tag('kernel.event_subscriber')

        ->set('.console.validate_question_input_listener', ValidateQuestionInputListener::class)
            ->args([
                service('validator'),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('container.remove_if_missing', ['service' => 'validator'])

        ->set('console.command.about', AboutCommand::class)
            ->tag('console.command')

        ->set('console.command.assets_install', AssetsInstallCommand::class)
            ->args([
                service('filesystem'),
                param('kernel.project_dir'),
            ])
            ->tag('console.command')

        ->set('console.command.cache_clear', CacheClearCommand::class)
            ->args([
                service('cache_clearer'),
                service('filesystem'),
            ])
            ->tag('console.command')

        ->set('console.command.cache_warmup', CacheWarmupCommand::class)
            ->args([
                service('cache_warmer'),
            ])
            ->tag('console.command')

        ->set('console.command.config_debug', ConfigDebugCommand::class)
            ->args([
                service('container.env_var_processors_locator'),
            ])
            ->tag('console.command')

        ->set('console.command.config_dump_reference', ConfigDumpReferenceCommand::class)
            ->tag('console.command')

        ->set('console.command.container_debug', ContainerDebugCommand::class)
            ->tag('console.command')

        ->set('console.command.container_lint', ContainerLintCommand::class)
            ->tag('console.command')

        ->set('console.command.expression_lint', ExpressionLintCommand::class)
            ->args([
                abstract_arg('expression languages, set in RegisterCompiledExpressionLanguagesPass'),
                service('expression_language.collector'),
            ])
            ->tag('console.command')

        ->set('console.command.debug', DebugCommand::class)
            ->args([
                tagged_iterator('debug.section', 'name'),
                abstract_arg('debug section names'),
            ])
            ->tag('console.command')

        ->set('console.command.debug.section.container', ContainerDebugSection::class)
            ->args([
                service('kernel'),
                service('debug.file_link_formatter')->nullOnInvalid(),
            ])
            ->tag('debug.section', ['name' => 'container', 'priority' => 1200])

        ->set('console.command.debug.section.router', RouterDebugSection::class)
            ->args([
                service('router'),
                service('kernel'),
                service('debug.file_link_formatter')->nullOnInvalid(),
            ])
            ->tag('debug.section', ['name' => 'routes', 'priority' => 1100])
            ->tag('container.remove_if_missing', ['service' => 'router'])

        ->set('console.command.debug.section.config', ConfigDebugSection::class)
            ->args([
                service('kernel'),
                service('container.env_var_processors_locator')->nullOnInvalid(),
                param('kernel.environment'),
                param('kernel.project_dir'),
            ])
            ->tag('debug.section', ['name' => 'config', 'priority' => 1000])

        ->set('console.command.debug.section.events', EventDispatcherDebugSection::class)
            ->args([
                tagged_locator('event_dispatcher.dispatcher', 'name'),
            ])
            ->tag('debug.section', ['name' => 'events', 'priority' => 900])

        ->set('console.command.debug.section.security', PlaceholderDebugSection::class)
            ->args(['Security', 'Sec', 'firewalls, authenticators, access rules, voters and role hierarchy'])
            ->tag('debug.section', ['name' => 'security', 'priority' => 800])

        ->set('console.command.debug.section.messenger', MessengerDebugSection::class)
            ->args([
                abstract_arg('Message to handlers mapping'),
            ])
            ->tag('debug.section', ['name' => 'messenger', 'priority' => 700])
            ->tag('container.remove_if_missing', ['service' => 'console.command.messenger_debug'])

        ->set('console.command.debug.section.twig', PlaceholderDebugSection::class)
            ->args(['Twig', 'Twig', 'Twig functions, filters, tests, globals and components'])
            ->tag('debug.section', ['name' => 'twig', 'priority' => 600])

        ->set('console.command.debug.section.form', FormDebugSection::class)
            ->args([
                service('form.registry'),
                abstract_arg('Form types'),
                abstract_arg('Form type extensions'),
                abstract_arg('Form type guessers'),
                service('debug.file_link_formatter')->nullOnInvalid(),
            ])
            ->tag('debug.section', ['name' => 'form', 'priority' => 500])

        ->set('console.command.debug.section.validator', ValidatorDebugSection::class)
            ->args([
                service('validator'),
                service('validator.builder'),
                abstract_arg('Validator candidate classes'),
            ])
            ->tag('debug.section', ['name' => 'validator', 'priority' => 400])
            ->tag('container.remove_if_missing', ['service' => 'validator'])

        ->set('console.command.debug.section.serializer', SerializerDebugSection::class)
            ->args([
                service('serializer.mapping.class_metadata_factory'),
                abstract_arg('Serializer metadata loaders'),
                abstract_arg('Serializer normalizers'),
                abstract_arg('Serializer encoders'),
                abstract_arg('Named serializers'),
            ])
            ->tag('debug.section', ['name' => 'serializer', 'priority' => 300])
            ->tag('container.remove_if_missing', ['service' => 'serializer'])

        ->set('console.command.debug.section.i18n', TranslationDebugSection::class)
            ->args([
                service('translator'),
                service('translation.reader'),
                service('translation.extractor'),
                param('translator.default_path'),
                null, // twig.default_path
                abstract_arg('Translator paths'),
                [], // Twig paths appended by TranslatorPathsPass
                param('kernel.enabled_locales'),
                service('kernel'),
            ])
            ->tag('debug.section', ['name' => 'translations', 'priority' => 200])
            ->tag('container.remove_if_missing', ['service' => 'console.command.translation_debug'])

        ->set('console.command.debug.section.scheduler', SchedulerDebugSection::class)
            ->args([
                tagged_locator('scheduler.schedule_provider', 'name'),
            ])
            ->tag('debug.section', ['name' => 'scheduler', 'priority' => 0])
            ->tag('container.remove_if_missing', ['service' => 'console.command.scheduler_debug'])

        ->set('console.command.debug.section.assets', AssetMapperDebugSection::class)
            ->args([
                service('asset_mapper'),
                param('kernel.project_dir'),
            ])
            ->tag('debug.section', ['name' => 'assets', 'priority' => 100])
            ->tag('container.remove_if_missing', ['service' => 'asset_mapper'])

        ->set('console.command.debug_autowiring', DebugAutowiringCommand::class)
            ->args([
                null,
                service('debug.file_link_formatter')->nullOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.command.event_dispatcher_debug', EventDispatcherDebugCommand::class)
            ->args([
                tagged_locator('event_dispatcher.dispatcher', 'name'),
            ])
            ->tag('console.command')

        ->set('console.command.router_debug', RouterDebugCommand::class)
            ->args([
                service('router'),
                service('debug.file_link_formatter')->nullOnInvalid(),
            ])
            ->tag('console.command')
            ->tag('container.remove_if_missing', ['service' => 'router'])

        ->set('console.command.serializer_debug', SerializerDebugCommand::class)
            ->args([
                service('serializer.mapping.class_metadata_factory'),
            ])
            ->tag('console.command')
            ->tag('container.remove_if_missing', ['service' => 'serializer'])

        ->set('console.command.validator_debug', ValidatorDebugCommand::class)
            ->args([
                service('validator'),
            ])
            ->tag('console.command')
            ->tag('container.remove_if_missing', ['service' => 'validator'])

        ->set('console.command.xliff_lint', XliffLintCommand::class)
            ->tag('console.command')

        ->set('console.command.yaml_lint', YamlLintCommand::class)
            ->args([
                inline_service(YamlLintSchemaResolver::class)
                    ->args([null, inline_service(FileHeaderSchemaResolver::class)]),
                inline_service(SchemaValidator::class),
                param('kernel.project_dir'),
            ])
            ->tag('console.command')

        ->set('console.command.form_debug', FormDebugCommand::class)
            ->args([
                service('form.registry'),
                [], // All form types namespaces are stored here by FormPass
                [], // All services form types are stored here by FormPass
                [], // All type extensions are stored here by FormPass
                [], // All type guessers are stored here by FormPass
                service('debug.file_link_formatter')->nullOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.command.secrets_set', SecretsSetCommand::class)
            ->args([
                service('secrets.vault'),
                service('secrets.local_vault')->nullOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.command.secrets_remove', SecretsRemoveCommand::class)
            ->args([
                service('secrets.vault'),
                service('secrets.local_vault')->nullOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.command.secrets_generate_key', SecretsGenerateKeysCommand::class)
            ->args([
                service('secrets.vault'),
                service('secrets.local_vault')->ignoreOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.command.secrets_list', SecretsListCommand::class)
            ->args([
                service('secrets.vault'),
                service('secrets.local_vault')->ignoreOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.command.secrets_reveal', SecretsRevealCommand::class)
            ->args([
                service('secrets.vault'),
                service('secrets.local_vault')->ignoreOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.command.secrets_decrypt_to_local', SecretsDecryptToLocalCommand::class)
            ->args([
                service('secrets.vault'),
                service('secrets.local_vault')->ignoreOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.command.secrets_encrypt_from_local', SecretsEncryptFromLocalCommand::class)
            ->args([
                service('secrets.vault'),
                service('secrets.local_vault')->ignoreOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.command.error_dumper', ErrorDumpCommand::class)
            ->args([
                service('filesystem'),
                service('error_renderer.html'),
                service(EntrypointLookupInterface::class)->nullOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.messenger.application', Application::class)
            ->share(false)
            ->call('setAutoExit', [false])
            ->args([
                service('kernel'),
            ])

        ->set('console.messenger.execute_command_handler', RunCommandMessageHandler::class)
            ->args([
                service('console.messenger.application'),
            ])
            ->tag('messenger.message_handler', ['sign' => true])
    ;
};
