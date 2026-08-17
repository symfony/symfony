<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler;

/**
 * Any HTML element that can link to an URI, backed by the native HTML parser.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractHtmlUriElement
{
    use UriElementTrait;

    protected \Dom\Element $node;
    protected ?string $method;

    /**
     * @param \Dom\Element $node       A \Dom\Element instance
     * @param string|null  $currentUri The URI of the page where the link is embedded (or the base href)
     * @param string|null  $method     The method to use for the link (GET by default)
     *
     * @throws \InvalidArgumentException if the node is not a link
     */
    public function __construct(
        \Dom\Element $node,
        protected ?string $currentUri = null,
        ?string $method = 'GET',
    ) {
        $this->setNode($node);
        $this->method = $method ? strtoupper($method) : null;

        $this->assertUriIsResolvable();
    }

    /**
     * Gets the node associated with this link.
     */
    public function getNode(): \Dom\Element
    {
        return $this->node;
    }

    /**
     * Sets current \Dom\Element instance.
     *
     * @param \Dom\Element $node A \Dom\Element instance
     *
     * @throws \LogicException If given node is not an anchor
     */
    abstract protected function setNode(\Dom\Element $node): void;
}
