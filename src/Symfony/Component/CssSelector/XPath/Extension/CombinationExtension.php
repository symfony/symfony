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
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class CombinationExtension extends AbstractExtension
{
    public function getCombinationTranslators(): array
    {
        return [
            ' ' => $this->translateDescendant(...),
            '>' => $this->translateChild(...),
            '+' => $this->translateDirectAdjacent(...),
            '~' => $this->translateIndirectAdjacent(...),
        ];
    }

    public function translateDescendant(XPathExpr $xpath, XPathExpr $combinedXpath): XPathExpr
    {
        return $xpath->join('/descendant-or-self::*/', $combinedXpath);
    }

    public function translateChild(XPathExpr $xpath, XPathExpr $combinedXpath): XPathExpr
    {
        return $xpath->join('/', $combinedXpath);
    }

    public function translateDirectAdjacent(XPathExpr $xpath, XPathExpr $combinedXpath): XPathExpr
    {
        $combinedXpath = $this->positionsToSiblings($combinedXpath);

        // position() and last() are evaluated on the following-sibling step, keep it
        if (preg_match('/\b(?:position|last)\(\)/', $combinedXpath->getCondition())) {
            return $xpath
                ->join('/following-sibling::', $combinedXpath)
                ->addNameTest()
                ->addCondition('position() = 1');
        }

        // libxml stops at the first sibling only when [1] is the last predicate of the step
        return $xpath->join('/following-sibling::*[1]/self::', $combinedXpath);
    }

    public function translateIndirectAdjacent(XPathExpr $xpath, XPathExpr $combinedXpath): XPathExpr
    {
        return $xpath->join('/following-sibling::', $this->positionsToSiblings($combinedXpath));
    }

    public function getName(): string
    {
        return 'combination';
    }

    /**
     * Replaces position() and last() by tests on the siblings.
     *
     * On the following-sibling axis, position() and last() don't count the preceding siblings.
     */
    private function positionsToSiblings(XPathExpr $xpath): XPathExpr
    {
        if (!str_starts_with($xpath, '*/')) {
            return $xpath;
        }

        return new XPathExpr('', $xpath->getElement(), XPathExpr::positionsToSiblings($xpath->getCondition(), $xpath->getElement()));
    }
}
