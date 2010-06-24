<?php

namespace Symfony\Components\Form\Renderer;

use Symfony\Components\Form\FieldInterface;
use Symfony\Components\Form\Field\ChoiceField;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Renders a field group as HTML table
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class TableRenderer extends Renderer
{
    /**
     * {@inheritDoc}
     */
    public function render(FieldInterface $group, array $attributes = array())
    {
        $html = "<table>\n";

        foreach ($group as $field) {
            $label = self::humanize($field->getKey());

            $html .= "<tr>\n";
            $html .= "<td><label for=\"{$field->getId()}\">$label</label></td>\n";
            $html .= "<td>\n";
            if ($field->hasErrors()) {
                $html .= $field->renderErrors()."\n";
            }
            $html .= $field->render()."\n";
            $html .= "</td>";
            $html .= "</tr>\n";
        }

        $html .= "</table>\n";

        return $html;
    }

    protected static function humanize($text)
    {
        return ucfirst(strtolower(str_replace('_', ' ', $text)));
    }
}
