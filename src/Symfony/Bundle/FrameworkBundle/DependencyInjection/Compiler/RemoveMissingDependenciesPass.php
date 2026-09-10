<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\Console\EventListener\ValidateQuestionInputListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Serializer\Serializer;

/**
 * Drops or reports the services this bundle cannot wire, because the component bundle
 * that would provide what they depend on registered nothing.
 *
 * @internal
 */
class RemoveMissingDependenciesPass implements CompilerPassInterface
{
    /**
     * The service a component bundle registers => the ids this bundle declares that are dead without it.
     */
    private const DEPENDENTS = [
        'html_sanitizer' => ['form.type_extension.form.html_sanitizer'],
        'router' => ['console.command.router_debug', 'console.command.router_match'],
        'serializer' => ['console.command.serializer_debug'],
        'validator' => ['console.command.validator_debug', '.console.validate_question_input_listener'],
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::DEPENDENTS as $id => $dependents) {
            if ($container->has($id)) {
                continue;
            }

            foreach ($dependents as $dependent) {
                $container->removeAlias($dependent);
                $container->removeDefinition($dependent);
            }
        }

        if (!class_exists(ValidateQuestionInputListener::class)) {
            $container->removeDefinition('.console.validate_question_input_listener');
        }

        if (!$container->has('http_client')) {
            $this->reportMissing($container, 'webhook.transport', 'You cannot use the "webhook transport" service since the HttpClient component is not '
                .(class_exists(ScopingHttpClient::class) ? 'enabled. Try setting "http_client.enabled" to true.' : 'installed. Try running "composer require symfony/http-client".'));
        }

        if (!$container->has('serializer')) {
            $this->reportMissing($container, 'argument_resolver.request_payload', 'You can neither use "#[MapRequestPayload]" nor "#[MapQueryString]" since the Serializer component is not '
                .(class_exists(Serializer::class) ? 'enabled. Try setting "serializer.enabled" to true.' : 'installed. Try running "composer require symfony/serializer-pack".'))
                ?->clearTag('kernel.event_subscriber');
        }
    }

    private function reportMissing(ContainerBuilder $container, string $id, string $message): ?Definition
    {
        if (!$container->hasDefinition($id)) {
            return null;
        }

        return $container->getDefinition($id)
            ->setArguments([])
            ->addError($message)
            ->addTag('container.error')
        ;
    }
}
