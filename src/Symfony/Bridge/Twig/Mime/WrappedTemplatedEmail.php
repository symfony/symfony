<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Mime;

use Symfony\Component\Mime\Address;
use Twig\Environment;

/**
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class WrappedTemplatedEmail
{
    private $twig;
    private $message;

    public function __construct(Environment $twig, TemplatedEmail $message)
    {
        $this->twig = $twig;
        $this->message = $message;
    }

    public function toName(): string
    {
        return $this->message->getTo()[0]->getName();
    }

    /**
     * @param string      $image       A Twig path to the image file. It's recommended to define
     *                                 some Twig namespace for email images (e.g. '@email/images/logo.png').
     * @param string|null $contentType The media type (i.e. MIME type) of the image file (e.g. 'image/png').
     *                                 Some email clients require this to display embedded images.
     */
    public function image(string $image, string $contentType = null): string
    {
        $file = $this->twig->getLoader()->getSourceContext($image);
        if ($path = $file->getPath()) {
            $this->message->embedFromPath($path, $image, $contentType);
        } else {
            $this->message->embed($file->getCode(), $image, $contentType);
        }

        return 'cid:'.$image;
    }

    /**
     * @param string      $file        A Twig path to the file. It's recommended to define
     *                                 some Twig namespace for email files (e.g. '@email/files/contract.pdf').
     * @param string|null $name        A custom file name that overrides the original name of the attached file
     * @param string|null $contentType The media type (i.e. MIME type) of the file (e.g. 'application/pdf').
     *                                 Some email clients require this to display attached files.
     */
    public function attach(string $file, string $name = null, string $contentType = null): void
    {
        $file = $this->twig->getLoader()->getSourceContext($file);
        if ($path = $file->getPath()) {
            $this->message->attachFromPath($path, $name, $contentType);
        } else {
            $this->message->attach($file->getCode(), $name, $contentType);
        }
    }

    /**
     * @return $this
     */
    public function setSubject(string $subject): self
    {
        $this->message->subject($subject);

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->message->getSubject();
    }

    /**
     * @return $this
     */
    public function setReturnPath(string $address): self
    {
        $this->message->returnPath($address);

        return $this;
    }

    public function getReturnPath(): string
    {
        return $this->message->getReturnPath();
    }

    /**
     * @return $this
     */
    public function addFrom(string $address, string $name = ''): self
    {
        $this->message->addFrom(new Address($address, $name));

        return $this;
    }

    /**
     * @return Address[]
     */
    public function getFrom(): array
    {
        return $this->message->getFrom();
    }

    /**
     * @return $this
     */
    public function addReplyTo(string $address): self
    {
        $this->message->addReplyTo($address);

        return $this;
    }

    /**
     * @return Address[]
     */
    public function getReplyTo(): array
    {
        return $this->message->getReplyTo();
    }

    /**
     * @return $this
     */
    public function addTo(string $address, string $name = ''): self
    {
        $this->message->addTo(new Address($address, $name));

        return $this;
    }

    /**
     * @return Address[]
     */
    public function getTo(): array
    {
        return $this->message->getTo();
    }

    /**
     * @return $this
     */
    public function addCc(string $address, string $name = ''): self
    {
        $this->message->addCc(new Address($address, $name));

        return $this;
    }

    /**
     * @return Address[]
     */
    public function getCc(): array
    {
        return $this->message->getCc();
    }

    /**
     * @return $this
     */
    public function addBcc(string $address, string $name = ''): self
    {
        $this->message->addBcc(new Address($address, $name));

        return $this;
    }

    /**
     * @return Address[]
     */
    public function getBcc(): array
    {
        return $this->message->getBcc();
    }

    /**
     * @return $this
     */
    public function setPriority(int $priority): self
    {
        $this->message->priority($priority);

        return $this;
    }

    public function getPriority(): int
    {
        return $this->message->getPriority();
    }
}
