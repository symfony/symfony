<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\SyntaxAware;

use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * A decorator that makes parameters able to use the @= syntax for parameters.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class SyntaxAwareParameterBag implements ParameterBagInterface
{
    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->parameterBag->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function add(array $parameters)
    {
        $parameters = $this->resolveExpressions($parameters);

        $this->parameterBag->add($parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->parameterBag->all();
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        return $this->parameterBag->get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $value)
    {
        $value = $this->resolveExpressions($value);

        $this->parameterBag->set($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        return $this->parameterBag->has($name);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve()
    {
        $this->parameterBag->resolve();
    }

    /**
     * {@inheritdoc}
     */
    public function resolveValue($value)
    {
        return $this->parameterBag->resolveValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function escapeValue($value)
    {
        return $this->parameterBag->escapeValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function unescapeValue($value)
    {
        return $this->parameterBag->unescapeValue($value);
    }

    /**
     * @return ParameterBagInterface
     */
    public function getParameterBag()
    {
        return $this->parameterBag;
    }

    private function resolveExpressions($value)
    {
        if (is_array($value)) {
            $value = array_map(array('self', 'resolveExpressions'), $value);
        } elseif (is_string($value) &&  0 === strpos($value, '@=')) {
            return new Expression(substr($value, 2));
        }

        return $value;
    }
}
