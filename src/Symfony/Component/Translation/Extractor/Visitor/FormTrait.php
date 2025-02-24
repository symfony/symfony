<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Extractor\Visitor;

use PhpParser\Node;
use Symfony\Component\Form\AbstractType;

trait FormTrait
{
    /**
     * Stores whether the current class is a form type across visits of all children nodes.
     */
    private bool $isFormType = false;

    private function isFormType(Node $node): bool
    {
        if ($node instanceof Node\Stmt\Class_) {
            if ($node->extends !== null) {
                if ($node->extends->isFullyQualified()) {
                    if ($node->extends->name === AbstractType::class) {
                        $this->isFormType = true;
                    }
                }
            }
        }

        return $this->isFormType;
    }
}
