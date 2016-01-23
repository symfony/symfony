<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AstGenerator;

/**
 * Generator delegating the generation to a chain of generators.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class AstGeneratorChain implements AstGeneratorInterface
{
    /** @var AstGeneratorInterface[] A list of generators */
    protected $generators;

    /** @var boolean Whether the generation must return as soon as possible or use all generators, default to false */
    protected $returnOnFirst;

    public function __construct(array $generators = [], $returnOnFirst = false)
    {
        $this->generators = $generators;
        $this->returnOnFirst = $returnOnFirst;
    }

    /**
     * {@inheritdoc}
     */
    public function generate($object, array $context = [])
    {
        $nodes = [];

        foreach ($this->generators as $generator) {
            if ($generator instanceof AstGeneratorInterface && $generator->supportsGeneration($object)) {
                $nodes = array_merge($nodes, $generator->generate($object, $context));

                if ($this->returnOnFirst) {
                    return $nodes;
                }
            }
        }

        return $nodes;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsGeneration($object)
    {
        foreach ($this->generators as $generator) {
            if ($generator instanceof AstGeneratorInterface && $generator->supportsGeneration($object)) {
                return true;
            }
        }

        return false;
    }
}
