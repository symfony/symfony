<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Generator\AstGenerator;

use PhpParser\Node\Name;
use PhpParser\Node\Param;
use Symfony\Component\Ast\NodeList;

/**
 * @author Guilhem N. <egetick@gmail.com>
 */
interface GeneratorAstGeneratorInterface
{
    /**
     * Dumps a set of routes to an ast representation that
     * can then be used to generate a URL of such a route.
     *
     * Available options:
     *
     *  * class:      The class name
     *  * base_class: The base class name
     *
     * @param array $options An array of options
     *
     * @return NodeList
     */
    public function generate(array $options = array());
}
