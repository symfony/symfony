<?php

namespace Symfony\Component\Mailer\Mime;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Email;

/**
 * @author Iain Cambridge <iain@iain.rocks>
 */
class ProviderTemplatedEmail extends TemplatedEmail
{
    private ?string $templateId = null;

    private array $context = [];

    public function getTemplateId(): ?string
    {
        return $this->templateId;
    }

    public function setTemplateId(?string $templateId): static
    {
        $this->templateId = $templateId;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }
}
