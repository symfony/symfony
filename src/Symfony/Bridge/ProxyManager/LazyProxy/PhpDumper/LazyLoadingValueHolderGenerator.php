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
use Zend\Code\Generator\ClassGenerator;

/**
 * @internal
 */
class LazyLoadingValueHolderGenerator extends BaseGenerator
{
    /**
     * {@inheritdoc}
     */
    public function generate(\ReflectionClass $originalClass, ClassGenerator $classGenerator)
    {
        parent::generate($originalClass, $classGenerator);

        if ($classGenerator->hasMethod('__destruct')) {
            $destructor = $classGenerator->getMethod('__destruct');
            $body = $destructor->getBody();
            $newBody = preg_replace('/^(\$this->initializer[a-zA-Z0-9]++) && .*;\n\nreturn (\$this->valueHolder)/', '$1 || $2', $body);

            if ($body === $newBody) {
                throw new \UnexpectedValueException(sprintf('Unexpected lazy-proxy format generated for method %s::__destruct()', $originalClass->name));
            }

            $destructor->setBody($newBody);
        }
    }
}
