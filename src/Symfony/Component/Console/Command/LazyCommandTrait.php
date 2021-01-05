<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait LazyCommandTrait
{
    private $container;

    /**
     * Override this method and declare service dependencies as additional arguments.
     */
    private function exec(InputInterface $input, OutputInterface $output/*, ...$services*/): int
    {
        throw new \LogicException(sprintf('Method "%s::exec()" should be overriden in "%s".', __TRAIT__, self::class));
    }

    /**
     * @required
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;

        if (\is_callable(['parent', __FUNCTION__])) {
            parent::setContainer($container);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this instanceof ServiceSubscriberInterface) {
            throw new \LogicException(sprintf('Class "%s" should declare that it implements "%s".', self::class, ServiceSubscriberInterface::class));
        }

        $arguments = [$input, $output];

        foreach (self::getSubscribedServices() as $id => $type) {
            if (0 === strpos($id, self::class.'::get')) {
                $arguments[] = $this->container->has($id) ? $this->container->get($id) : null;
            }
        }

        return $this->exec(...$arguments);
    }

    public static function getSubscribedServices(): array
    {
        $m = new \ReflectionMethod(self::class, 'exec');

        if (!(\ReflectionMethod::IS_PRIVATE & $m->getModifiers())) {
            throw new \LogicException(sprintf('Method "%s::exec()" should be private.', self::class));
        }
        $r = $m->getReturnType();

        if ('int' !== ($r instanceof \ReflectionNamedType ? $r->getName() : (string) $r)) {
            throw new \LogicException(sprintf('Method "%s::exec()" should declare "int" as return type.', self::class));
        }

        $services = \is_callable(['parent', __FUNCTION__]) ? parent::getSubscribedServices() : [];
        $i = 0;

        foreach ($m->getParameters() as $i => $p) {
            $r = $p->getType();
            $type = $r instanceof \ReflectionNamedType ? $r->getName() : (string) $r;

            if (0 === $i && InputInterface::class !== $type) {
                throw new \LogicException(sprintf('Method "%s::exec()" should declare argument #1 as "%s".', self::class, InputInterface::class));
            } elseif (1 === $i && OutputInterface::class !== $type) {
                throw new \LogicException(sprintf('Method "%s::exec()" should declare argument #2 as "%s".', self::class, OutputInterface::class));
            } elseif (2 <= $i) {
                $services[self::class.'::get'.$p->name] = ($p->allowsNull() ? '?' : '').$type;
            }
        }

        if (1 > $i) {
            throw new \LogicException(sprintf('Method "%s::exec()" should declare 2 or more arguments.', self::class));
        }

        return $services;
    }
}
