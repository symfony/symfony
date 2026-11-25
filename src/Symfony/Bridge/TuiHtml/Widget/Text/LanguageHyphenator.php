<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Widget\Text;

use Symfony\Component\HtmlRenderer\Hyphenation\HyphenatorInterface;
use Symfony\Component\HtmlRenderer\Hyphenation\LanguageHyphenator as RendererHyphenator;

/**
 * @internal
 */
final class LanguageHyphenator
{
    public const MAX_LENGTH = RendererHyphenator::MAX_LENGTH;

    private readonly RendererHyphenator $hyphenator;

    public function __construct(HyphenatorInterface $hyphenator, public readonly string $language)
    {
        $this->hyphenator = new RendererHyphenator($hyphenator, $language);
    }

    public function hyphenate(string $word): array
    {
        return $this->hyphenator->hyphenate($word);
    }
}
