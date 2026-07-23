<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Node;

/**
 * Represents a "<selector>:has(<subselector>)" node.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/scrapy/cssselect.
 *
 * @author Franck Ranaivo-Harisoa <franckranaivo@gmail.com>
 *
 * @internal
 */
class RelationNode extends AbstractNode
{
    /**
     * @param list<array{0: string, 1: NodeInterface}> $arguments
     */
    public function __construct(
        private NodeInterface $selector,
        private array $arguments,
    ) {
    }

    public function getSelector(): NodeInterface
    {
        return $this->selector;
    }

    /**
     * @return list<array{0: string, 1: NodeInterface}>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getSpecificity(): Specificity
    {
        $argumentsSpecificity = array_reduce(
            $this->arguments,
            static fn (Specificity $c, array $a) => 1 === $a[1]->getSpecificity()->compareTo($c) ? $a[1]->getSpecificity() : $c,
            new Specificity(0, 0, 0),
        );

        return $this->selector->getSpecificity()->plus($argumentsSpecificity);
    }

    public function __toString(): string
    {
        $parts = array_map(
            static fn (array $a): string => (' ' === $a[0] ? '' : $a[0].' ').$a[1],
            $this->arguments,
        );

        return \sprintf('%s[%s:has(%s)]', $this->getNodeName(), $this->selector, implode(', ', $parts));
    }
}
