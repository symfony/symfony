<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\Plugin;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Renderer\RendererInterface;

class DatePatternPlugin implements RendererPluginInterface
{
    private $formatter;

    public function __construct(\IntlDateFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    public function setUp(FieldInterface $field, RendererInterface $renderer)
    {
        $renderer->setVar('date_pattern', $this->getPattern());
    }

    public function getPattern()
    {
//        // set order as specified in the pattern
//        if ($this->getOption('pattern')) {
//            return $this->getOption('pattern');
//        }

        $pattern = $this->formatter->getPattern();

        // set right order with respect to locale (e.g.: de_DE=dd.MM.yy; en_US=M/d/yy)
        // lookup various formats at http://userguide.icu-project.org/formatparse/datetime
        if (preg_match('/^([yMd]+).+([yMd]+).+([yMd]+)$/', $pattern)) {
            return preg_replace(array('/y+/', '/M+/', '/d+/'), array('{{ year }}', '{{ month }}', '{{ day }}'), $pattern);
        }

        // default fallback
        return '{{ year }}-{{ month }}-{{ day }}';
    }
}