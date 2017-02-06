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
        ob_start();
        parent::sendContent();
        $content = ob_get_contents();
        ob_end_clean();

        $pos = strripos($content, '</body>');

        if (false !== $pos) {
            $extraHtml = "\n".$this->extraHtml."\n";
            $content = substr($content, 0, $pos).$extraHtml.substr($content, $pos);
        }
        
        echo $content;
    }
}