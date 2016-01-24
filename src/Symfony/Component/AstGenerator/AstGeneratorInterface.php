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
 * An AstGeneratorInterface is a contract to transform an object into an AST.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
interface AstGeneratorInterface
{
    /**
     * Generate an object into an AST given a specific context.
     *
     * @param mixed $object  Object to generate AST from
     * @param array $context Context for the generator
     *
     * @return \PhpParser\Node[] An array of statements (AST Node)
     */
    public function generate($object, array $context = []);

    /**
     * Check whether the given object is supported for generation by this generator.
     *
     * @param mixed $object Object to generate AST from
     *
     * @return bool
     */
    public function supportsGeneration($object);
}
