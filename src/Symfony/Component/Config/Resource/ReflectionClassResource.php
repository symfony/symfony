<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final
 */
class ReflectionClassResource implements SelfCheckingResourceInterface
{
    private array $files = [];
    private string $className;
    private \ReflectionClass $classReflector;
    private array $excludedVendors = [];
    private string $hash;

    public function __construct(\ReflectionClass $classReflector, array $excludedVendors = [])
    {
        $this->className = $classReflector->name;
        $this->classReflector = $classReflector;
        $this->excludedVendors = $excludedVendors;
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh(int $timestamp): bool
    {
        if (!isset($this->hash)) {
            $this->hash = $this->computeHash();
            $this->loadFiles($this->classReflector);
        }

        foreach ($this->files as $file => $v) {
            if (false === $filemtime = @filemtime($file)) {
                return false;
            }

            if ($filemtime > $timestamp) {
                return $this->hash === $this->computeHash();
            }
        }

        return true;
    }

    public function __toString(): string
    {
        return 'reflection.'.$this->className;
    }

    /**
     * @internal
     */
    public function __sleep(): array
    {
        if (!isset($this->hash)) {
            $this->hash = $this->computeHash();
            $this->loadFiles($this->classReflector);
        }

        return ['files', 'className', 'hash'];
    }

    private function loadFiles(\ReflectionClass $class)
    {
        foreach ($class->getInterfaces() as $v) {
            $this->loadFiles($v);
        }
        do {
            $file = $class->getFileName();
            if (false !== $file && is_file($file)) {
                foreach ($this->excludedVendors as $vendor) {
                    if (str_starts_with($file, $vendor) && false !== strpbrk(substr($file, \strlen($vendor), 1), '/'.\DIRECTORY_SEPARATOR)) {
                        $file = false;
                        break;
                    }
                }
                if ($file) {
                    $this->files[$file] = null;
                }
            }
            foreach ($class->getTraits() as $v) {
                $this->loadFiles($v);
            }
        } while ($class = $class->getParentClass());
    }

    private function computeHash(): string
    {
        try {
            $this->classReflector ??= new \ReflectionClass($this->className);
        } catch (\ReflectionException) {
            // the class does not exist anymore
            return false;
        }
        $hash = hash_init('md5');

        foreach ($this->generateSignature($this->classReflector) as $info) {
            hash_update($hash, $info);
        }

        return hash_final($hash);
    }

    private function generateSignature(\ReflectionClass $class): iterable
    {
        $attributes = [];
        foreach ($class->getAttributes() as $a) {
            $attributes[] = [$a->getName(), (string) $a];
        }
        yield print_r($attributes, true);
        $attributes = [];

        yield $class->getDocComment();
        yield (int) $class->isFinal();
        yield (int) $class->isAbstract();

        if ($class->isTrait()) {
            yield print_r(class_uses($class->name), true);
        } else {
            yield print_r(class_parents($class->name), true);
            yield print_r(class_implements($class->name), true);
            yield print_r($class->getConstants(), true);
        }

        if (!$class->isInterface()) {
            $defaults = $class->getDefaultProperties();

            foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED) as $p) {
                foreach ($p->getAttributes() as $a) {
                    $attributes[] = [$a->getName(), (string) $a];
                }
                yield print_r($attributes, true);
                $attributes = [];

                yield $p->getDocComment();
                yield $p->isDefault() ? '<default>' : '';
                yield $p->isPublic() ? 'public' : 'protected';
                yield $p->isStatic() ? 'static' : '';
                yield '$'.$p->name;
                yield print_r(isset($defaults[$p->name]) && !\is_object($defaults[$p->name]) ? $defaults[$p->name] : null, true);
            }
        }

        $defined = \Closure::bind(static function ($c) { return \defined($c); }, null, $class->name);

        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $m) {
            foreach ($m->getAttributes() as $a) {
                $attributes[] = [$a->getName(), (string) $a];
            }
            yield print_r($attributes, true);
            $attributes = [];

            $defaults = [];
            foreach ($m->getParameters() as $p) {
                foreach ($p->getAttributes() as $a) {
                    $attributes[] = [$a->getName(), (string) $a];
                }
                yield print_r($attributes, true);
                $attributes = [];

                if (!$p->isDefaultValueAvailable()) {
                    $defaults[$p->name] = null;

                    continue;
                }

                $defaults[$p->name] = (string) $p;
            }

            yield preg_replace('/^  @@.*/m', '', $m);
            yield print_r($defaults, true);
        }

        if ($class->isAbstract() || $class->isInterface() || $class->isTrait()) {
            return;
        }

        if (interface_exists(EventSubscriberInterface::class, false) && $class->isSubclassOf(EventSubscriberInterface::class)) {
            yield EventSubscriberInterface::class;
            yield print_r($class->name::getSubscribedEvents(), true);
        }

        if (interface_exists(MessageSubscriberInterface::class, false) && $class->isSubclassOf(MessageSubscriberInterface::class)) {
            yield MessageSubscriberInterface::class;
            foreach ($class->name::getHandledMessages() as $key => $value) {
                yield $key.print_r($value, true);
            }
        }

        if (interface_exists(ServiceSubscriberInterface::class, false) && $class->isSubclassOf(ServiceSubscriberInterface::class)) {
            yield ServiceSubscriberInterface::class;
            yield print_r($class->name::getSubscribedServices(), true);
        }
    }
}
