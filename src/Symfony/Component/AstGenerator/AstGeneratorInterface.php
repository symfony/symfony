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
 * An AstGeneratorInterface is a contract to transform an object into an AST
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
interface AstGeneratorInterface
{
    public function generate();

    public function supportsGeneration();
}
