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
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Command\DebugCommand;
use Symfony\Component\Messenger\Command\FailedMessagesRemoveCommand;
use Symfony\Component\Messenger\Command\FailedMessagesRetryCommand;
use Symfony\Component\Messenger\Command\FailedMessagesShowCommand;
use Symfony\Component\Messenger\Command\SetupTransportsCommand;
use Symfony\Component\Messenger\Command\StopWorkersCommand;
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
            ->tag('console.command', ['command' => 'about'])

        ->set('console.command.assets_install', AssetsInstallCommand::class)
            ->args([
                service('filesystem'),
                param('kernel.project_dir'),
            ])
            ->tag('console.command', ['command' => 'assets:install'])

        ->set('console.command.cache_clear', CacheClearCommand::class)
            ->args([
                service('cache_clearer'),
                service('filesystem'),
            ])
            ->tag('console.command', ['command' => 'cache:clear'])

        ->set('console.command.cache_pool_clear', CachePoolClearCommand::class)
            ->args([
                service('cache.global_clearer'),
            ])
            ->tag('console.command', ['command' => 'cache:pool:clear'])

        ->set('console.command.cache_pool_prune', CachePoolPruneCommand::class)
            ->args([
                [],
            ])
            ->tag('console.command', ['command' => 'cache:pool:prune'])

        ->set('console.command.cache_pool_delete', CachePoolDeleteCommand::class)
            ->args([
                service('cache.global_clearer'),
            ])
            ->tag('console.command', ['command' => 'cache:pool:delete'])

        ->set('console.command.cache_pool_list', CachePoolListCommand::class)
            ->args([
                null,
            ])
            ->tag('console.command', ['command' => 'cache:pool:list'])

        ->set('console.command.cache_warmup', CacheWarmupCommand::class)
            ->args([
                service('cache_warmer'),
            ])
            ->tag('console.command', ['command' => 'cache:warmup'])

        ->set('console.command.config_debug', ConfigDebugCommand::class)
            ->tag('console.command', ['command' => 'debug:config'])

        ->set('console.command.config_dump_reference', ConfigDumpReferenceCommand::class)
            ->tag('console.command', ['command' => 'config:dump-reference'])

        ->set('console.command.container_debug', ContainerDebugCommand::class)
            ->tag('console.command', ['command' => 'debug:container'])

        ->set('console.command.container_lint', ContainerLintCommand::class)
            ->tag('console.command', ['command' => 'lint:container'])

        ->set('console.command.debug_autowiring', DebugAutowiringCommand::class)
            ->args([
                null,
                service('debug.file_link_formatter')->nullOnInvalid(),
            ])
            ->tag('console.command', ['command' => 'debug:autowiring'])

        ->set('console.command.event_dispatcher_debug', EventDispatcherDebugCommand::class)
            ->args([
                service('event_dispatcher'),
            ])
            ->tag('console.command', ['command' => 'debug:event-dispatcher'])

        ->set('console.command.messenger_consume_messages', ConsumeMessagesCommand::class)
            ->args([
                abstract_arg('Routable message bus'),
                service('messenger.receiver_locator'),
                service('event_dispatcher'),
                service('logger')->nullOnInvalid(),
                [], // Receiver names
            ])
            ->tag('console.command', ['command' => 'messenger:consume'])
            ->tag('monolog.logger', ['channel' => 'messenger'])

        ->set('console.command.messenger_setup_transports', SetupTransportsCommand::class)
            ->args([
                service('messenger.receiver_locator'),
                [], // Receiver names
            ])
            ->tag('console.command', ['command' => 'messenger:setup-transports'])

        ->set('console.command.messenger_debug', DebugCommand::class)
            ->args([
                [], // Message to handlers mapping
            ])
            ->tag('console.command', ['command' => 'debug:messenger'])

        ->set('console.command.messenger_stop_workers', StopWorkersCommand::class)
            ->args([
                service('cache.messenger.restart_workers_signal'),
            ])
            ->tag('console.command', ['command' => 'messenger:stop-workers'])

        ->set('console.command.messenger_failed_messages_retry', FailedMessagesRetryCommand::class)
            ->args([
                abstract_arg('Receiver name'),
                abstract_arg('Receiver'),
                service('messenger.routable_message_bus'),
                service('event_dispatcher'),
                service('logger'),
            ])
            ->tag('console.command', ['command' => 'messenger:failed:retry'])

        ->set('console.command.messenger_failed_messages_show', FailedMessagesShowCommand::class)
            ->args([
                abstract_arg('Receiver name'),
                abstract_arg('Receiver'),
            ])
            ->tag('console.command', ['command' => 'messenger:failed:show'])

        ->set('console.command.messenger_failed_messages_remove', FailedMessagesRemoveCommand::class)
            ->args([
                abstract_arg('Receiver name'),
                abstract_arg('Receiver'),
            ])
            ->tag('console.command', ['command' => 'messenger:failed:remove'])

        ->set('console.command.router_debug', RouterDebugCommand::class)
            ->args([
                service('router'),
                service('debug.file_link_formatter')->nullOnInvalid(),
            ])
            ->tag('console.command', ['command' => 'debug:router'])

        ->set('console.command.router_match', RouterMatchCommand::class)
            ->args([
                service('router'),
                tagged_iterator('routing.expression_language_provider'),
            ])
            ->tag('console.command', ['command' => 'router:match'])

        ->set('console.command.translation_debug', TranslationDebugCommand::class)
            ->args([
                service('translator'),
                service('translation.reader'),
                service('translation.extractor'),
                param('translator.default_path'),
                null, // twig.default_path
                [], // Translator paths
                [], // Twig paths
            ])
            ->tag('console.command', ['command' => 'debug:translation'])

        ->set('console.command.translation_update', TranslationUpdateCommand::class)
            ->args([
                service('translation.writer'),
                service('translation.reader'),
                service('translation.extractor'),
                param('kernel.default_locale'),
                param('translator.default_path'),
                null, // twig.default_path
                [], // Translator paths
                [], // Twig paths
            ])
            ->tag('console.command', ['command' => 'translation:update'])

        ->set('console.command.validator_debug', ValidatorDebugCommand::class)
            ->args([
                service('validator'),
            ])
            ->tag('console.command', ['command' => 'debug:validator'])

        ->set('console.command.workflow_dump', WorkflowDumpCommand::class)
            ->tag('console.command', ['command' => 'workflow:dump'])

        ->set('console.command.xliff_lint', XliffLintCommand::class)
            ->tag('console.command', ['command' => 'lint:xliff'])

        ->set('console.command.yaml_lint', YamlLintCommand::class)
            ->tag('console.command', ['command' => 'lint:yaml'])

        ->set('console.command.form_debug', \Symfony\Component\Form\Command\DebugCommand::class)
            ->args([
                service('form.registry'),
                [], // All form types namespaces are stored here by FormPass
                [], // All services form types are stored here by FormPass
                [], // All type extensions are stored here by FormPass
                [], // All type guessers are stored here by FormPass
                service('debug.file_link_formatter')->nullOnInvalid(),
            ])
            ->tag('console.command', ['command' => 'debug:form'])

        ->set('console.command.secrets_set', SecretsSetCommand::class)
            ->args([
                service('secrets.vault'),
                service('secrets.local_vault')->nullOnInvalid(),
            ])
            ->tag('console.command', ['command' => 'secrets:set'])

        ->set('console.command.secrets_remove', SecretsRemoveCommand::class)
            ->args([
                service('secrets.vault'),
                service('secrets.local_vault')->nullOnInvalid(),
            ])
            ->tag('console.command', ['command' => 'secrets:remove'])

        ->set('console.command.secrets_generate_key', SecretsGenerateKeysCommand::class)
            ->args([
                service('secrets.vault'),
                service('secrets.local_vault')->ignoreOnInvalid(),
            ])
            ->tag('console.command', ['command' => 'secrets:generate-keys'])

        ->set('console.command.secrets_list', SecretsListCommand::class)
            ->args([
                service('secrets.vault'),
                service('secrets.local_vault'),
            ])
            ->tag('console.command', ['command' => 'secrets:list'])

        ->set('console.command.secrets_decrypt_to_local', SecretsDecryptToLocalCommand::class)
            ->args([
                service('secrets.vault'),
                service('secrets.local_vault')->ignoreOnInvalid(),
            ])
            ->tag('console.command', ['command' => 'secrets:decrypt-to-local'])

        ->set('console.command.secrets_encrypt_from_local', SecretsEncryptFromLocalCommand::class)
            ->args([
                service('secrets.vault'),
                service('secrets.local_vault'),
            ])
            ->tag('console.command', ['command' => 'secrets:encrypt-from-local'])
    ;
};
