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

use Symfony\Component\Mailer\Transport\RemoteTemplateTransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\TextPart;

/**
 * An email rendered by the mail provider from a template hosted on its side.
 *
 * Such emails can only be sent through transports implementing
 * {@see RemoteTemplateTransportInterface}; other transports refuse them.
 *
 * @author Florent Blaison <florent.blaison@gmail.com>
 */
class RemoteTemplateEmail extends Email
{
    private ?RemoteTemplate $template = null;

    /**
     * @param string|null          $template  The provider-side reference of the template (an id, uuid, name or alias, depending on the provider)
     * @param array<string, mixed> $variables The variables used by the provider to render the template
     *
     * @return $this
     */
    public function template(?string $template, array $variables = []): static
    {
        $this->template = null === $template ? null : new RemoteTemplate($template, $variables);

        return $this;
    }

    public function getRemoteTemplate(): ?RemoteTemplate
    {
        return $this->template;
    }

    public function getBody(): AbstractPart
    {
        if (null === $this->template) {
            return parent::getBody();
        }

        return new TextPart(\sprintf('This email is rendered by the mail provider from its "%s" template.', $this->template->getReference()));
    }

    protected function ensureBodyValid(): void
    {
        if (null === $this->template) {
            parent::ensureBodyValid();

            return;
        }

        if (null !== $this->getTextBody() || null !== $this->getHtmlBody()) {
            throw new LogicException('An email using a remote template cannot have a text or an HTML part; its body is rendered by the mail provider.');
        }
    }

    /**
     * @internal
     */
    public function __serialize(): array
    {
        return [$this->template, parent::__serialize()];
    }

    /**
     * @internal
     */
    public function __unserialize(array $data): void
    {
        if (null !== ($data[0] ?? null) && !$data[0] instanceof RemoteTemplate) {
            throw new \BadMethodCallException('Cannot unserialize '.self::class);
        }

        [$this->template, $parentData] = $data;

        parent::__unserialize($parentData);
    }
}
