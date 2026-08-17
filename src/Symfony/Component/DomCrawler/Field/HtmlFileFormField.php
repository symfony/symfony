<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Field;

/**
 * HtmlFileFormField represents a file form field (an HTML file input tag).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HtmlFileFormField extends HtmlFormField
{
    use FileFormFieldTrait;

    /**
     * Sets path to the file as string for simulating HTTP request.
     */
    public function setFilePath(string $path): void
    {
        $this->value = $path;
    }

    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize(): void
    {
        if ('input' !== $this->node->localName) {
            throw new \LogicException(\sprintf('An HtmlFileFormField can only be created from an input tag (%s given).', $this->node->localName));
        }

        if ('file' !== strtolower($this->node->getAttribute('type') ?? '')) {
            throw new \LogicException(\sprintf('An HtmlFileFormField can only be created from an input tag with a type of file (given type is "%s").', $this->node->getAttribute('type')));
        }

        $this->setValue(null);
    }
}
