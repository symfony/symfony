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

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ExpressionLanguage;
use Symfony\Component\DependencyInjection\Reference;
use function class_exists;
use function sprintf;
use function stripcslashes;
use function substr;
use function substr_replace;

/**
 * Replaces aliases with actual service definitions, effectively removing these
 * aliases.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ReplaceAliasByActualDefinitionPass extends AbstractRecursivePass
{
    private $replacements;
    private $expressionLanguageInstance;
    private $inExpressionUsage = false;

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
        foreach ($container->getAliases() as $definitionId => $target) {
            $targetId = (string) $target;
            // Special case: leave this target alone
            if ('service_container' === $targetId) {
                continue;
            }
            // Check if target needs to be replaces
            if (isset($replacements[$targetId])) {
                $container->setAlias($definitionId, $replacements[$targetId])->setPublic($target->isPublic())->setPrivate($target->isPrivate());
            }
            // No need to process the same target twice
            if (isset($seenAliasTargets[$targetId])) {
                continue;
            }
            // Process new target
            $seenAliasTargets[$targetId] = true;
            try {
                if (strpos($targetId, '=') === 0) {
                    $service = trim($targetId, '=');

                    $result = $this
                        ->getExpressionLanguage()
                        ->evaluate($service, ['this' => 'container', 'container' => $container]);

                    $container->setAlias($targetId, \get_class($result))
                        ->setPublic($target->isPublic())
                        ->setPrivate($target->isPrivate());

                    continue;
                }

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
            $definition->setPublic(!$target->isPrivate());
            $definition->setPrivate($target->isPrivate());
            $container->setDefinition($definitionId, $definition);
            $container->removeDefinition($targetId);
            $replacements[$targetId] = $definitionId;
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

    private function getExpressionLanguage(ContainerInterface $container): ExpressionLanguage
    {
        if (null === $this->expressionLanguageInstance) {
            if (!class_exists(ExpressionLanguage::class)) {
                throw new LogicException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }

            $providers = $container->getExpressionLanguageProviders();
            $this->expressionLanguageInstance = new ExpressionLanguage(null, $providers, function (string $arg): string {
                if ('""' === substr_replace($arg, '', 1, -1)) {
                    $id = stripcslashes(substr($arg, 1, -1));
                    $this->inExpressionUsage = true;
                    $arg = $this->processValue(new Reference($id));
                    $this->inExpressionUsage = false;
                    if (!$arg instanceof Reference) {
                        throw new RuntimeException(sprintf('"%s::processValue()" must return a Reference when processing an expression, %s returned for service("%s").', \get_class($this), \is_object($arg) ? \get_class($arg) : \gettype($arg), $id));
                    }
                    $arg = sprintf('"%s"', $arg);
                }

                return sprintf('$this->get(%s)', $arg);
            });
        }

        return $this->expressionLanguageInstance;
    }
}
