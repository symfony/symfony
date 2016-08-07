<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ast;

use PhpParser\PrettyPrinterAbstract;
use PhpParser\PrettyPrinter\Standard;

final class AstDumper
{
    private $printer;

    public function __construct(PrettyPrinterAbstract $printer = null)
    {
        if (null === $printer) {
            $printer = new Standard();
        }

        $this->printer = $printer;
    }

    public function dump(NodeList $nodeList)
    {
        return $this->printer->prettyPrintFile($nodeList->getNodes());
    }
}
