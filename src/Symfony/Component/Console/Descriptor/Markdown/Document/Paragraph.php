<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
