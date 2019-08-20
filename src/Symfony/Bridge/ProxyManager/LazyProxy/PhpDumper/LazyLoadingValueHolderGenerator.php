<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper;

use ProxyManager\ProxyGenerator\LazyLoadingValueHolderGenerator as BaseGenerator;
use Symfony\Component\DependencyInjection\Definition;
use Zend\Code\Generator\ClassGenerator;

/**
 * @internal
 */
class LazyLoadingValueHolderGenerator extends BaseGenerator
{
    private $fluentSafe = false;

    public function setFluentSafe(bool $fluentSafe)
    {
        $this->fluentSafe = $fluentSafe;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(\ReflectionClass $originalClass, ClassGenerator $classGenerator): void
    {
        parent::generate($originalClass, $classGenerator);

        foreach ($classGenerator->getMethods() as $method) {
            $body = preg_replace(
                '/(\$this->initializer[0-9a-f]++) && \1->__invoke\(\$this->(valueHolder[0-9a-f]++), (.*?), \1\);/',
                '$1 && ($1->__invoke(\$$2, $3, $1) || 1) && $this->$2 = \$$2;',
                $method->getBody()
            );
            $body = str_replace('(new \ReflectionClass(get_class()))', '$reflection', $body);
            $body = str_replace('$reflection = $reflection ?: ', '$reflection = $reflection ?? ', $body);
            $body = str_replace('$reflection ?? $reflection = ', '$reflection ?? ', $body);

            if ($originalClass->isInterface()) {
                $body = str_replace('get_parent_class($this)', var_export($originalClass->name, true), $body);
                $body = preg_replace_callback('/\n\n\$realInstanceReflection = [^{]++\{([^}]++)\}\n\n.*/s', function ($m) {
                    $r = '';
                    foreach (explode("\n", $m[1]) as $line) {
                        $r .= "\n".substr($line, 4);
                        if (0 === strpos($line, '    return ')) {
                            break;
                        }
                    }

                    return $r;
                }, $body);
            }

            if ($this->fluentSafe) {
                $indent = $method->getIndentation();
                $method->setIndentation('');
                $code = $method->generate();
                if (null !== $docBlock = $method->getDocBlock()) {
                    $code = substr($code, \strlen($docBlock->generate()));
                }
                $refAmp = (strpos($code, '&') ?: \PHP_INT_MAX) <= strpos($code, '(') ? '&' : '';
                $body = preg_replace(
                    '/\nreturn (\$this->valueHolder[0-9a-f]++)(->[^;]++);$/',
                    "\nif ($1 === \$returnValue = {$refAmp}$1$2) {\n    \$returnValue = \$this;\n}\n\nreturn \$returnValue;",
                    $body
                );
                $method->setIndentation($indent);
            }

            if (0 === strpos($originalClass->getFilename(), __FILE__)) {
                $body = str_replace(var_export($originalClass->name, true), '__CLASS__', $body);
            }

            $method->setBody($body);
        }

        if ($classGenerator->hasMethod('__destruct')) {
            $destructor = $classGenerator->getMethod('__destruct');
            $body = $destructor->getBody();
            $newBody = preg_replace('/^(\$this->initializer[a-zA-Z0-9]++) && .*;\n\nreturn (\$this->valueHolder)/', '$1 || $2', $body);

            if ($body === $newBody) {
                throw new \UnexpectedValueException(sprintf('Unexpected lazy-proxy format generated for method %s::__destruct()', $originalClass->name));
            }

            $destructor->setBody($newBody);
        }

        if (0 === strpos($originalClass->getFilename(), __FILE__)) {
            $interfaces = $classGenerator->getImplementedInterfaces();
            array_pop($interfaces);
            $classGenerator->setImplementedInterfaces(array_merge($interfaces, $originalClass->getInterfaceNames()));
        }
    }

    public function getProxifiedClass(Definition $definition): ?string
    {
        if (!$definition->hasTag('proxy')) {
            return class_exists($class = $definition->getClass()) || interface_exists($class, false) ? $class : null;
        }
        if (!$definition->isLazy()) {
            throw new \InvalidArgumentException(sprintf('Invalid definition for service of class "%s": setting the "proxy" tag on a service requires it to be "lazy".', $definition->getClass()));
        }
        $tags = $definition->getTag('proxy');
        if (!isset($tags[0]['interface'])) {
            throw new \InvalidArgumentException(sprintf('Invalid definition for service of class "%s": the "interface" attribute is missing on the "proxy" tag.', $definition->getClass()));
        }
        if (1 === \count($tags)) {
            return class_exists($tags[0]['interface']) || interface_exists($tags[0]['interface'], false) ? $tags[0]['interface'] : null;
        }

        $proxyInterface = 'LazyProxy';
        $interfaces = '';
        foreach ($tags as $tag) {
            if (!isset($tag['interface'])) {
                throw new \InvalidArgumentException(sprintf('Invalid definition for service of class "%s": the "interface" attribute is missing on a "proxy" tag.', $definition->getClass()));
            }
            if (!interface_exists($tag['interface'])) {
                throw new \InvalidArgumentException(sprintf('Invalid definition for service of class "%s": several "proxy" tags found but "%s" is not an interface.', $definition->getClass(), $tag['interface']));
            }

            $proxyInterface .= '\\'.$tag['interface'];
            $interfaces .= ', \\'.$tag['interface'];
        }

        if (!interface_exists($proxyInterface)) {
            $i = strrpos($proxyInterface, '\\');
            $namespace = substr($proxyInterface, 0, $i);
            $interface = substr($proxyInterface, 1 + $i);
            $interfaces = substr($interfaces, 2);

            eval("namespace {$namespace}; interface {$interface} extends {$interfaces} {}");
        }

        return $proxyInterface;
    }
}
