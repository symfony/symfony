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

use Symfony\Component\Mime\Email;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 4.3
 */
class TemplatedEmail extends Email
{
    private $template;
    private $htmlTemplate;
    private $textTemplate;
    private $context = [];

    /**
     * @return $this
     */
    public function template(?string $template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return $this
     */
    public function textTemplate(?string $template)
    {
        $this->textTemplate = $template;

        return $this;
    }

    /**
     * @return $this
     */
    public function htmlTemplate(?string $template)
    {
        $this->htmlTemplate = $template;

        return $this;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function getTextTemplate(): ?string
    {
        return $this->textTemplate;
    }

    public function getHtmlTemplate(): ?string
    {
        return $this->htmlTemplate;
    }

    /**
     * @return $this
     */
    public function context(array $context)
    {
        $this->context = $context;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
