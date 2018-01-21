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

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ReflectionClassResource implements SelfCheckingResourceInterface, \Serializable
{
    private $files = array();
    private $className;
    private $classReflector;
    private $excludedVendors = array();
    private $hash;

    public function __construct(\ReflectionClass $classReflector, array $excludedVendors = array())
    {
        $this->className = $classReflector->name;
        $this->classReflector = $classReflector;
        $this->excludedVendors = $excludedVendors;
    }

    public function isFresh($timestamp)
    {
        if (null === $this->hash) {
            $this->hash = $this->computeHash();
            $this->loadFiles($this->classReflector);
        }

        foreach ($this->files as $file => $v) {
            if (!file_exists($file)) {
                return false;
            }

            if (@filemtime($file) > $timestamp) {
                return $this->hash === $this->computeHash();
            }
        }

        return true;
    }

    public function __toString()
    {
        return 'reflection.'.$this->className;
    }

    public function serialize()
    {
        if (null === $this->hash) {
            $this->hash = $this->computeHash();
            $this->loadFiles($this->classReflector);
        }

        return serialize(array($this->files, $this->className, $this->hash));
    }

    public function unserialize($serialized)
    {
        list($this->files, $this->className, $this->hash) = unserialize($serialized);
    }

    private function loadFiles(\ReflectionClass $class)
    {
        foreach ($class->getInterfaces() as $v) {
            $this->loadFiles($v);
        }
        do {
            $file = $class->getFileName();
            if (false !== $file && file_exists($file)) {
                foreach ($this->excludedVendors as $vendor) {
                    if (0 === strpos($file, $vendor) && false !== strpbrk(substr($file, strlen($vendor), 1), '/'.DIRECTORY_SEPARATOR)) {
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

    private function computeHash()
    {
        if (null === $this->classReflector) {
            try {
                $this->classReflector = new \ReflectionClass($this->className);
            } catch (\ReflectionException $e) {
                // the class does not exist anymore
                return false;
            }
        }
        $hash = hash_init('md5');

        foreach ($this->generateSignature($this->classReflector) as $info) {
            hash_update($hash, $info);
        }

        return hash_final($hash);
    }

    private function generateSignature(\ReflectionClass $class)
    {
        yield $class->getDocComment().$class->getModifiers();

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
                yield $p->getDocComment().$p;
                yield print_r($defaults[$p->name], true);
            }
        }

        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $m) {
            yield preg_replace('/^  @@.*/m', '', $m);

            $defaults = array();
            foreach ($m->getParameters() as $p) {
                $defaults[$p->name] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
            }
            yield print_r($defaults, true);
        }
    }
}
