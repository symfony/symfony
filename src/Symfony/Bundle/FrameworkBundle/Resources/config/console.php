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
use Symfony\Bundle\FrameworkBundle\Command\CachePoolClearCommand;
use Symfony\Bundle\FrameworkBundle\Command\CachePoolDeleteCommand;
use Symfony\Bundle\FrameworkBundle\Command\CachePoolInvalidateTagsCommand;
use Symfony\Bundle\FrameworkBundle\Command\CachePoolListCommand;
use Symfony\Bundle\FrameworkBundle\Command\CachePoolPruneCommand;
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
use Symfony\Bundle\FrameworkBundle\Command\SecretsSetCommand;
use Symfony\Bundle\FrameworkBundle\Command\TranslationDebugCommand;
use Symfony\Bundle\FrameworkBundle\Command\TranslationUpdateCommand;
use Symfony\Bundle\FrameworkBundle\Command\WorkflowDumpCommand;
use Symfony\Bundle\FrameworkBundle\Command\YamlLintCommand;
use Symfony\Bundle\FrameworkBundle\EventListener\SuggestMissingPackageSubscriber;
use Symfony\Component\Console\EventListener\ErrorListener;
use Symfony\Component\Dotenv\Command\DebugCommand as DotenvDebugCommand;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Command\DebugCommand;
use Symfony\Component\Messenger\Command\FailedMessagesRemoveCommand;
use Symfony\Component\Messenger\Command\FailedMessagesRetryCommand;
use Symfony\Component\Messenger\Command\FailedMessagesShowCommand;
use Symfony\Component\Messenger\Command\SetupTransportsCommand;
use Symfony\Component\Messenger\Command\StatsCommand;
use Symfony\Component\Messenger\Command\StopWorkersCommand;
use Symfony\Component\Translation\Command\TranslationPullCommand;
use Symfony\Component\Translation\Command\TranslationPushCommand;
use Symfony\Component\Translation\Command\XliffLintCommand;
use Symfony\Component\Validator\Command\DebugCommand as ValidatorDebugCommand;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('console.error_listener', ErrorListener::class)
            ->args([
                service('logger')->nullOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'console'])

        ->set('console.suggest_missing_package_subscriber', SuggestMissingPackageSubscriber::class)
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

        ->set('console.command.cache_pool_clear', CachePoolClearCommand::class)
            ->args([
                service('cache.global_clearer'),
            ])
            ->tag('console.command')

        ->set('console.command.cache_pool_prune', CachePoolPruneCommand::class)
            ->args([
                [],
            ])
            ->tag('console.command')

        ->set('console.command.cache_pool_invalidate_tags', CachePoolInvalidateTagsCommand::class)
            ->args([
                tagged_locator('cache.taggable', 'pool'),
            ])
            ->tag('console.command')

        ->set('console.command.cache_pool_delete', CachePoolDeleteCommand::class)
            ->args([
                service('cache.global_clearer'),
            ])
            ->tag('console.command')

        ->set('console.command.cache_pool_list', CachePoolListCommand::class)
            ->args([
                null,
            ])
            ->tag('console.command')

        ->set('console.command.cache_warmup', CacheWarmupCommand::class)
            ->args([
                service('cache_warmer'),
            ])
            ->tag('console.command')

        ->set('console.command.config_debug', ConfigDebugCommand::class)
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

        ->set('console.command.dotenv_debug', DotenvDebugCommand::class)
            ->args([
                param('kernel.environment'),
                param('kernel.project_dir'),
            ])
            ->tag('console.command')

        ->set('console.command.event_dispatcher_debug', EventDispatcherDebugCommand::class)
            ->args([
                tagged_locator('event_dispatcher.dispatcher', 'name'),
            ])
            ->tag('console.command')

        ->set('console.command.messenger_consume_messages', ConsumeMessagesCommand::class)
            ->args([
                abstract_arg('Routable message bus'),
                service('messenger.receiver_locator'),
                service('event_dispatcher'),
                service('logger')->nullOnInvalid(),
                [], // Receiver names
                service('messenger.listener.reset_services')->nullOnInvalid(),
                [], // Bus names
                service('messenger.rate_limiter_locator')->nullOnInvalid(),
            ])
            ->tag('console.command')
            ->tag('monolog.logger', ['channel' => 'messenger'])

        ->set('console.command.messenger_setup_transports', SetupTransportsCommand::class)
            ->args([
                service('messenger.receiver_locator'),
                [], // Receiver names
            ])
            ->tag('console.command')

        ->set('console.command.messenger_debug', DebugCommand::class)
            ->args([
                [], // Message to handlers mapping
            ])
            ->tag('console.command')

        ->set('console.command.messenger_stop_workers', StopWorkersCommand::class)
            ->args([
                service('cache.messenger.restart_workers_signal'),
            ])
            ->tag('console.command')

        ->set('console.command.messenger_failed_messages_retry', FailedMessagesRetryCommand::class)
            ->args([
                abstract_arg('Default failure receiver name'),
                abstract_arg('Receivers'),
                service('messenger.routable_message_bus'),
                service('event_dispatcher'),
                service('logger')->nullOnInvalid(),
                service('messenger.transport.native_php_serializer')->nullOnInvalid(),
            ])
            ->tag('console.command')
            ->tag('monolog.logger', ['channel' => 'messenger'])

        ->set('console.command.messenger_failed_messages_show', FailedMessagesShowCommand::class)
            ->args([
                abstract_arg('Default failure receiver name'),
                abstract_arg('Receivers'),
                service('messenger.transport.native_php_serializer')->nullOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.command.messenger_failed_messages_remove', FailedMessagesRemoveCommand::class)
            ->args([
                abstract_arg('Default failure receiver name'),
                abstract_arg('Receivers'),
                service('messenger.transport.native_php_serializer')->nullOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.command.messenger_stats', StatsCommand::class)
            ->args([
                service('messenger.receiver_locator'),
                abstract_arg('Receivers names'),
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

        ->set('console.command.translation_debug', TranslationDebugCommand::class)
            ->args([
                service('translator'),
                service('translation.reader'),
                service('translation.extractor'),
                param('translator.default_path'),
                null, // twig.default_path
                [], // Translator paths
                [], // Twig paths
                param('kernel.enabled_locales'),
            ])
            ->tag('console.command')

        ->set('console.command.translation_extract', TranslationUpdateCommand::class)
            ->args([
                service('translation.writer'),
                service('translation.reader'),
                service('translation.extractor'),
                param('kernel.default_locale'),
                param('translator.default_path'),
                null, // twig.default_path
                [], // Translator paths
                [], // Twig paths
                param('kernel.enabled_locales'),
            ])
            ->tag('console.command')

        ->set('console.command.validator_debug', ValidatorDebugCommand::class)
            ->args([
                service('validator'),
            ])
            ->tag('console.command')

        ->set('console.command.translation_pull', TranslationPullCommand::class)
            ->args([
                service('translation.provider_collection'),
                service('translation.writer'),
                service('translation.reader'),
                param('kernel.default_locale'),
                [], // Translator paths
                [], // Enabled locales
            ])
            ->tag('console.command', ['command' => 'translation:pull'])

        ->set('console.command.translation_push', TranslationPushCommand::class)
            ->args([
                service('translation.provider_collection'),
                service('translation.reader'),
                [], // Translator paths
                [], // Enabled locales
            ])
            ->tag('console.command', ['command' => 'translation:push'])

        ->set('console.command.workflow_dump', WorkflowDumpCommand::class)
            ->args([
                tagged_locator('workflow', 'name'),
            ])
            ->tag('console.command')

        ->set('console.command.xliff_lint', XliffLintCommand::class)
            ->tag('console.command')

        ->set('console.command.yaml_lint', YamlLintCommand::class)
            ->tag('console.command')

        ->set('console.command.form_debug', \Symfony\Component\Form\Command\DebugCommand::class)
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
    ;
};
