<?php

namespace Symfony\Component\Console\Descriptor\Markdown\Document;

/**
 * Document paragraph.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Paragraph implements BlockInterface
{
    /**
     * @var string
     */
    private $content;

    /**
     * @param $content
     */
    public function __construct($content)
    {
        $this->content = $content;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return empty($this->content);
    }

    /**
     * {@inheritdoc}
     */
    public function format(Formatter $formatter)
    {
        return implode("\n", $formatter->clip($this->content));
    }
}
