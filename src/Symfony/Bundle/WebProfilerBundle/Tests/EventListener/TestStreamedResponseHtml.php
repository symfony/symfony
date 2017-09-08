<?php

namespace Symfony\Bundle\WebProfilerBundle\Tests\EventListener;

use Symfony\Component\HttpFoundation\HtmlResponseInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 *
 * @author Bartłomiej Krukowski <bartlomiej@krukowski.me>
 */
class TestStreamedResponseHtml extends StreamedResponse implements HtmlResponseInterface
{
    private $extraHtml = '';

    public function appendToBody($html)
    {
        $this->extraHtml .= $html;
    }

    /**
     * {@inheritdoc}
     */
    public function sendContent()
    {
        parent::sendContent();
        if ('' !== $this->extraHtml) {
            echo "\n".$this->extraHtml;
        }
    }
}
