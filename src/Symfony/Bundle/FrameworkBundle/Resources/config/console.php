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
use Symfony\Bundle\FrameworkBundle\Command\EventDispatcherDebugCommand;
use Symfony\Bundle\FrameworkBundle\Command\RouterDebugCommand;
use Symfony\Bundle\FrameworkBundle\Command\RouterMatchCommand;
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
use Symfony\Bundle\FrameworkBundle\EventListener\SuggestMissingPackageSubscriber;
use Symfony\Component\Console\EventListener\ValidateQuestionInputListener;
use Symfony\Component\Console\Messenger\RunCommandMessageHandler;
use Symfony\Component\ErrorHandler\Command\ErrorDumpCommand;
use Symfony\Component\Form\Command\DebugCommand;
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

        ->set('console.command.router_match', RouterMatchCommand::class)
            ->args([
                service('router'),
                tagged_iterator('routing.expression_language_provider'),
            ])
            ->tag('console.command')

        ->set('console.command.serializer_debug', SerializerDebugCommand::class)
            ->args([
                service('serializer.mapping.class_metadata_factory'),
            ])
            ->tag('console.command')

        ->set('console.command.validator_debug', ValidatorDebugCommand::class)
            ->args([
                service('validator'),
            ])
            ->tag('console.command')

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

        ->set('console.command.form_debug', DebugCommand::class)
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
