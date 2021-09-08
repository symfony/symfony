<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replaces aliases with actual service definitions, effectively removing these
 * aliases.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ReplaceAliasByActualDefinitionPass extends AbstractRecursivePass
{
    private $replacements;
    private $autoAliasServicePass;

    /**
     * @internal to be removed in Symfony 6.0
     *
     * @return $this
     */
    public function setAutoAliasServicePass(AutoAliasServicePass $autoAliasServicePass): self
    {
        $this->autoAliasServicePass = $autoAliasServicePass;

        return $this;
    }

    /**
     * Process the Container to replace aliases with service definitions.
     *
     * @throws InvalidArgumentException if the service definition does not exist
     */
    public function process(ContainerBuilder $container)
    {
        // First collect all alias targets that need to be replaced
        $seenAliasTargets = [];
        $replacements = [];

        $privateAliases = $this->autoAliasServicePass ? $this->autoAliasServicePass->getPrivateAliases() : [];
        foreach ($privateAliases as $target) {
            $target->setDeprecated('symfony/dependency-injection', '5.4', 'Accessing the "%alias_id%" service directly from the container is deprecated, use dependency injection instead.');
        }

        foreach ($container->getAliases() as $definitionId => $target) {
            $targetId = (string) $target;
            // Special case: leave this target alone
            if ('service_container' === $targetId) {
                continue;
            }
            // Check if target needs to be replaced
            if (isset($replacements[$targetId])) {
                $container->setAlias($definitionId, $replacements[$targetId])->setPublic($target->isPublic());

                if ($target->isDeprecated()) {
                    $container->getAlias($definitionId)->setDeprecated(...array_values($target->getDeprecation('%alias_id%')));
                }
            }
            // No need to process the same target twice
            if (isset($seenAliasTargets[$targetId])) {
                continue;
            }
            // Process new target
            $seenAliasTargets[$targetId] = true;
            try {
                $definition = $container->getDefinition($targetId);
            } catch (ServiceNotFoundException $e) {
                if ('' !== $e->getId() && '@' === $e->getId()[0]) {
                    throw new ServiceNotFoundException($e->getId(), $e->getSourceId(), null, [substr($e->getId(), 1)]);
                }

                throw $e;
            }
            if ($definition->isPublic()) {
                continue;
            }
            // Remove private definition and schedule for replacement
            $definition->setPublic($target->isPublic());
            $container->setDefinition($definitionId, $definition);
            $container->removeDefinition($targetId);
            $replacements[$targetId] = $definitionId;

            if ($target->isPublic() && $target->isDeprecated()) {
                $definition->addTag('container.private', $target->getDeprecation('%service_id%'));
            }
        }
        $this->replacements = $replacements;

        parent::process($container);
        $this->replacements = [];
    }

    /**
     * {@inheritdoc}
     */
    protected function processValue($value, bool $isRoot = false)
    {
        if ($value instanceof Reference && isset($this->replacements[$referenceId = (string) $value])) {
            // Perform the replacement
            $newId = $this->replacements[$referenceId];
            $value = new Reference($newId, $value->getInvalidBehavior());
            $this->container->log($this, sprintf('Changed reference of service "%s" previously pointing to "%s" to "%s".', $this->currentId, $referenceId, $newId));
        }

        return parent::processValue($value, $isRoot);
    }
}
