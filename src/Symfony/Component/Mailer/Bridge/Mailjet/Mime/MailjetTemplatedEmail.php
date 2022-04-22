<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailjet\Mime;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\AbstractPart;

class MailjetTemplatedEmail extends Email
{
    protected $campaignName = null;
    protected $templateId = null;
    protected $variables = [];
    protected $errorReportingEmail = null;
    protected $templateErrorDeliver = false;
    protected $additionalProperties = [];

    public function __construct(Headers $headers = null, AbstractPart $body = null)
    {
        parent::__construct($headers, $body);

        $this->html('');
        $this->text('');
    }

    public function getCampaignName()
    {
        return $this->campaignName;
    }

    public function setCampaignName(string $campaignName): self
    {
        $this->campaignName = $campaignName;

        return $this;
    }

    public function getTemplateId()
    {
        return $this->templateId;
    }

    public function setTemplateId(int $templateId): self
    {
        $this->templateId = $templateId;

        return $this;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function setVariables(array $variables): self
    {
        $this->variables = $variables;

        return $this;
    }

    public function setVariable(string $key, string $value): self
    {
        $this->variables[$key] = $value;

        return $this;
    }

    public function getErrorReportingEmail()
    {
        return $this->errorReportingEmail;
    }

    public function setErrorReportingEmail(string $errorReportingEmail): self
    {
        $this->errorReportingEmail = $errorReportingEmail;

        return $this;
    }

    public function isTemplateErrorDeliver(): bool
    {
        return $this->templateErrorDeliver;
    }

    public function setTemplateErrorDeliver(bool $templateErrorDeliver = true): self
    {
        $this->templateErrorDeliver = $templateErrorDeliver;

        return $this;
    }

    public function getAdditionalProperties(): array
    {
        return $this->additionalProperties;
    }

    public function setAdditionalProperties(array $additionalProperties): self
    {
        $this->additionalProperties = $additionalProperties;

        return $this;
    }

    public function addProperty(string $key, string $value): self
    {
        $this->additionalProperties[$key] = $value;

        return $this;
    }

    public function ensureValidity()
    {
        if (null === $this->templateId) {
            if (\count($this->variables)) {
                throw new LogicException('A template id is required.');
            }
        }

        parent::ensureValidity();
    }

    /**
     * @internal
     */
    public function __serialize(): array
    {
        return [$this->campaignName, $this->templateId, $this->variables, $this->errorReportingEmail, $this->templateErrorDeliver, $this->additionalProperties, parent::__serialize()];
    }

    /**
     * @internal
     */
    public function __unserialize(array $data): void
    {
        [$this->campaignName, $this->templateId, $this->variables, $this->errorReportingEmail, $this->templateErrorDeliver, $this->additionalProperties, $parentData] = $data;

        parent::__unserialize($parentData);
    }
}
