<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer;

/**
 * The reference and variables of a template hosted by a mail provider.
 *
 * @author Florent Blaison <florent.blaison@gmail.com>
 */
final class RemoteTemplate
{
    /**
     * @param string               $reference The provider-side reference of the template (an id, uuid, name or alias, depending on the provider)
     * @param array<string, mixed> $variables The variables used by the provider to render the template
     */
    public function __construct(
        private string $reference,
        private array $variables = [],
    ) {
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }
}
