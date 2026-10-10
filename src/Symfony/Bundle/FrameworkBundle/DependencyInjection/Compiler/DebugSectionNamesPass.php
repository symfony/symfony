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

use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Collects the machine names of the "debug.section" tagged services so that the
 * "debug" command can declare one input option per section without instantiating
 * the (lazy) sections when the command is merely listed or described.
 *
 * @internal
 */
final class DebugSectionNamesPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    /**
     * Option names that are reserved by the console application or the command itself.
     */
    private const array RESERVED_NAMES = ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'silent', 'env', 'no-debug', 'profile', 'query'];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('console.command.debug')) {
            return;
        }

        $sections = $this->findAndSortTaggedServices(new TaggedIteratorArgument('debug.section', 'name', needsIndexes: true), $container);

        $names = [];
        foreach (array_keys($sections) as $name) {
            if (!preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
                throw new InvalidArgumentException(\sprintf('The "name" attribute of the "debug.section" tag must match "[a-z][a-z0-9-]*", got "%s". Add a valid "name" attribute to the tag (it is used as the "--%s" option of the "debug" command).', $name, $name));
            }

            if (\in_array($name, self::RESERVED_NAMES, true)) {
                throw new InvalidArgumentException(\sprintf('The "name" attribute of the "debug.section" tag cannot be "%1$s", it conflicts with the built-in "--%1$s" option of the "debug" command.', $name));
            }

            $names[] = $name;
        }

        $container->getDefinition('console.command.debug')->replaceArgument(1, $names);
    }
}
