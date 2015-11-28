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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Add finds all services taged with `console.helper` and adds them to a parameter
 * array which is used later in the FrameworkBundle's console application.
 *
 * @author Christopher Davis <chris@classicalguitar.org>
 */
class ConsoleHelperPass implements CompilerPassInterface
{
    const TAG = 'console.helper';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $ids = [];
        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            $id = $this->assureValidService($container, $id);
            $alias = empty($tags[0]['alias']) ? null : $tags[0]['alias'];
            $ids[$id] = $alias;
        }

        $container->setParameter(sprintf('%s.ids', self::TAG), $ids);
    }

    /**
     * Ensure that the service is public, not abstract and the class it uses
     * implements HelperInterface.
     *
     * @param $container the container in which the service is definied
     * @param string $id the service ID
     *
     * @throws InvalidArgumentException if the service is invalid.
     *
     * @return string the service ID if valid
     */
    protected function assureValidService(ContainerBuilder $container, $id)
    {
        $def = $container->getDefinition($id);
        if (!$def->isPublic()) {
            throw new \InvalidArgumentException(sprintf(
                'The service "%s" tagged "%s" must be public.',
                $id, self::TAG
            ));
        }

        if ($def->isAbstract()) {
            throw new \InvalidArgumentException(sprintf(
                'The service "%s" tagged "%s" must not be abstract.',
                $id,
                self::TAG
            ));
        }

        $class = $container->getParameterBag()->resolveValue($def->getClass());
        if (!$this->isHelper($class)) {
            throw new \InvalidArgumentException(sprintf(
                'The service "%s" tagged "%s" must implement "Symfony\\Component\\Console\\Helper\\HelperInterface".',
                $id,
                self::TAG
            ));
        }

        return $id;
    }

    /**
     * Check to see if the class implements the required `HelperInterface`.
     *
     * @param string $class The class to check
     *
     * @return bool
     */
    protected function isHelper($class)
    {
        $ref = new \ReflectionClass($class);

        return $ref->implementsInterface('Symfony\\Component\\Console\\Helper\\HelperInterface');
    }
}
