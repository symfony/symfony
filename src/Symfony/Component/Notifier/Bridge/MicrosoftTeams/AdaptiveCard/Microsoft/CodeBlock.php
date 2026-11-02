<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Microsoft;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Element;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\HorizontalAlignment;

/**
 * A block of code with syntax highlighting, only rendered by Microsoft Teams.
 *
 * @see https://learn.microsoft.com/en-us/microsoftteams/platform/task-modules-and-cards/cards/cards-format#codeblock-in-adaptive-cards
 */
final class CodeBlock extends Element
{
    public function __construct(string $codeSnippet)
    {
        $this->options = [
            'type' => 'CodeBlock',
            'codeSnippet' => $codeSnippet,
        ];
    }

    /**
     * @return $this
     */
    public function language(CodeLanguage $language): static
    {
        $this->options['language'] = $language->value;

        return $this;
    }

    /**
     * @return $this
     */
    public function horizontalAlignment(HorizontalAlignment $horizontalAlignment): static
    {
        $this->options['horizontalAlignment'] = $horizontalAlignment->value;

        return $this;
    }
}
