<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder;

use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Yield_;
use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter\Standard;

if (!class_exists(PhpVersion::class)) {
    /**
     * Tweaks PHP printed string for PhpParser 5.x forward compatibility.
     *
     * @author Mathias Arlaud <mathias.arlaud@gmail.com>
     *
     * @internal
     */
    final class PhpPrinter extends Standard
    {
        /**
         * Removes parentheses around yield.
         */
        protected function pExpr_Yield(Yield_ $node): string
        {
            return preg_replace('/^\(|\)$/', '', parent::pExpr_Yield($node));
        }

        /**
         * Removes space between closing parenthesis and column and add space between use keyword and parenthesis.
         *
         * See https://github.com/nikic/PHP-Parser/blob/v5.0.0/lib/PhpParser/PrettyPrinter/Standard.php#L650
         */
        protected function pExpr_Closure(Closure $node): string
        {
            return $this->pAttrGroups($node->attrGroups, true)
                .($node->static ? 'static ' : '')
                .'function '.($node->byRef ? '&' : '')
                .'('.$this->pCommaSeparated($node->params).')'
                .(!empty($node->uses) ? ' use ('.$this->pCommaSeparated($node->uses).')' : '')
                .(null !== $node->returnType ? ': '.$this->p($node->returnType) : '')
                .' {'.$this->pStmts($node->stmts).$this->nl.'}';
        }

        /**
         * Only escape backslashes when needed.
         *
         * See https://github.com/nikic/PHP-Parser/blob/v5.0.0/lib/PhpParser/PrettyPrinter/Standard.php#L1049
         */
        protected function pSingleQuotedString(string $string): string
        {
            return '\''.preg_replace('/\'|\\\\(?=[\'\\\\]|$)|(?<=\\\\)\\\\/', '\\\\$0', $string).'\'';
        }
    }
} else {
    final class PhpPrinter extends Standard
    {
    }
}
