<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Compiler;

use Symfony\Component\OpenApi\Documentation\DocumentationInterface;
use Symfony\Component\OpenApi\Model\OpenApi;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
interface DocumentationCompilerInterface
{
    public function compile(DocumentationInterface $doc): OpenApi;
}
