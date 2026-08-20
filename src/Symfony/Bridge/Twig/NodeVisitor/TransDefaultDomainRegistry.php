<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\NodeVisitor;

use Twig\Source;

/**
 * Shares "trans_default_domain ... for _self" declarations between parse time
 * and traversal time.
 *
 * The body of an "embed" tag is parsed and traversed before the template that
 * contains it, so the enclosing AST is not available when the embedded one is
 * visited. The declaration is therefore recorded by the token parser and read
 * back by the node visitor. Both are keyed by the Twig source, which the parser
 * shares between a template and the anonymous templates written inside it.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 *
 * @internal
 */
final class TransDefaultDomainRegistry
{
    /** @var \WeakMap<Source, string> */
    private \WeakMap $domains;

    /** @var \WeakMap<Source, true> */
    private \WeakMap $parsedTemplates;

    public function __construct()
    {
        $this->domains = new \WeakMap();
        $this->parsedTemplates = new \WeakMap();
    }

    public function setDomain(Source $source, string $domain): void
    {
        $this->domains[$source] = $domain;
    }

    public function getDomain(Source $source): ?string
    {
        return $this->domains[$source] ?? null;
    }

    /**
     * Flags that a template written in $source has been fully parsed, which for
     * anything but the outermost one means an "embed" body has been reached.
     */
    public function markTemplateParsed(Source $source): void
    {
        $this->parsedTemplates[$source] = true;
    }

    public function hasParsedEmbeddedTemplate(Source $source): bool
    {
        return isset($this->parsedTemplates[$source]);
    }
}
