<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime;

use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\TextPart;

/**
 * @author Mounir Mouih <mounir.mouih@gmail.com>
 */
final class RemoteEmail extends Email
{
    /**
     * The name of the header that contains the remote email template.
     */
    private string $headerName = '';

    /**
     * The templateId hosted on the cloud.
     */
    private string $templateId = '';

    protected function ensureBodyValid(): void
    {
        if (null === $this->getHeaders()->get($this->headerName)) {
            throw new LogicException('Cannot send remote email without template.');
        }
    }

    /**
     * Set the remote template to the header.
     */
    public function setRemoteTemplate(string $headerName, string $templateId): static
    {
        if ('' !== $this->headerName && $this->getHeaders()->has($this->headerName)) {
            $this->getHeaders()->remove($this->headerName);
        }

        $this->headerName = $headerName;
        $this->templateId = $templateId;
        $this->getHeaders()->addTextHeader($headerName, $templateId);

        return $this;
    }

    public function getBody(): AbstractPart
    {
        return new TextPart('');
    }

    public function getTemplateHeaderName(): string
    {
        return $this->headerName;
    }

    public function getTemplateId(): string
    {
        return $this->templateId;
    }
}
