<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\XPath\Extension;

use Symfony\Component\CssSelector\XPath\XPathExpr;

/**
 * XPath expression translator combination extension.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/scrapy/cssselect.
 *
 * @author Franck Ranaivo-Harisoa <franckranaivo@gmail.com>
 *
 * @internal
 */
class RelationExtension extends AbstractExtension
{
    public function getRelativeCombinationTranslators(): array
    {
        return [
            ' ' => $this->translateRelationDescendant(...),
            '>' => $this->translateRelationChild(...),
            '+' => $this->translateRelationDirectAdjacent(...),
            '~' => $this->translateRelationIndirectAdjacent(...),
        ];
    }

    public function translateRelationDescendant(XPathExpr $xpath, XPathExpr $combinedXpath): XPathExpr
    {
        return $xpath->join('[descendant-or-self::', $combinedXpath, ']', true);
    }

    public function translateRelationChild(XPathExpr $xpath, XPathExpr $combinedXpath): XPathExpr
    {
        return $xpath->join('[./', $combinedXpath, ']');
    }

    public function translateRelationDirectAdjacent(XPathExpr $xpath, XPathExpr $combinedXpath): XPathExpr
    {
        $combinedXpath
            ->addNameTest()
            ->addCondition('position() = 1');
        return $xpath
            ->join('[following-sibling::', $combinedXpath, ']', true)
            ;
    }

    public function translateRelationIndirectAdjacent(XPathExpr $xpath, XPathExpr $combinedXpath): XPathExpr
    {
        return $xpath->join('[following-sibling::', $combinedXpath, ']');
    }

    public function getName(): string
    {
        return 'relation';
    }
}
