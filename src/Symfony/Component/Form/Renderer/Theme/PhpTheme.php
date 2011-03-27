<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\Theme;

/**
 * Default Php Theme that renders a form without a dependency on a template engine.
 *
 * To change the rendering of this theme just extend the class and overwrite
 * the respective methods.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class PhpTheme implements FormThemeInterface
{
    /**
     * Charset to be used with htmlentities.
     *
     * @var string
     */
    private $charset;

    public function __construct($charset = 'UTF-8')
    {
        $this->charset = $charset;
    }

    public function render(array $blocks, $section, array $parameters)
    {
        foreach ($blocks as $block) {
            $method = $block.'_'.$section;

            if (method_exists($this, $method)) {
                return $this->$method($parameters);
            }
        }

        $blocks = array_map(function ($block) use ($section) {
            return $block.'_'.$section;
        }, $blocks);

        throw new \BadMethodCallException(sprintf('PhpTheme does not support the form block methods "%s"', implode('", "', $blocks)));
    }

    protected function checkbox_widget($attr)
    {
        $html = '<input type="checkbox" ' . $this->attributes($attr);
        if ($attr['checked']) {
            $html .= 'checked="checked" ';
        }
        $html .= '/>' . PHP_EOL;
        return $html;
    }

    protected function choice_widget($attr)
    {
        if ($attr['expanded']) {
            $html = '';
            foreach ($attr['renderer'] as $choice => $child) {
                $html .= $child->getWidget() . $child->getLabel() . PHP_EOL;
            }
        } else {
            $html = '<select ' . $this->attributes($attr);
            if ($attr['multiple']) {
                $html .= 'multiple="multiple" ';
            }
            $html .= '>' . PHP_EOL;
            if (!$attr['required']) {
                $html .= '<option value="">' . $this->escape($attr['empty_value']) .'</option>';
            }
            if (count($attr['preferred_choices']) > 0) {
                $html .= $this->choice_list($attr['preferred_choices'], $attr['choice_list'], $attr['value']);
                $html .= '<option disabled="disabled">' .  $this->escape($attr['separator']) . '</option>' . PHP_EOL;
            }
            $html .= $this->choice_list($attr['choices'], $attr['choice_list'], $attr['value']);
            $html .= '</select>' . PHP_EOL;
        }
        return $html;
    }

    protected function choice_list($choices, $choiceList, $value)
    {
        $html = '';
        foreach ($choices as $choice => $label) {
            if ($choiceList->isChoiceGroup($label)) {
                $html .= '<optgroup label="' . $this->escape($choice) .'">' . PHP_EOL;
                foreach ($label as $nestedChoice => $nestedLabel) {
                    $html .= '<option value="' . $nestedChoice . '"' .
                             (($choiceList->isChoiceSelected($nestedChoice, $value)) ? ' selected="selected"' : '') .
                             '>';
                    $html .= $this->escape($nestedLabel);
                    $html .= '</option>' . PHP_EOL;
                }
                $html .= '</optgroup>' . PHP_EOL;
            } else {
                $html .= '<option value="' . $choice . '"' .
                         (($choiceList->isChoiceSelected($choice, $value)) ? ' selected="selected"' : '') .
                         '>';
                $html .= $this->escape($label);
                $html .= '</option>' . PHP_EOL;
            }
        }
        return $html;
    }

    protected function collection_row($attr)
    {
        return $this->form_widget($attr);
    }

    protected function date_widget($attr)
    {
        if ($attr['widget'] == "text") {
            return $this->text_widget($attr);
        } else {
            return str_replace(array('{{ year }}', '{{ month }}', '{{ day }}'), array(
                $attr['renderer']['year']->getWidget(),
                $attr['renderer']['month']->getWidget(),
                $attr['renderer']['day']->getWidget(),
            ), $attr['date_pattern']);
        }
    }

    protected function datetime_widget($attr)
    {
        return $attr['renderer']['date']->getWidget() . " " . $attr['renderer']['time']->getWidget() . PHP_EOL;
    }

    protected function field_errors($attr)
    {
        $html = '';
        if ($attr['errors']) {
            $html = '<ul>' . PHP_EOL;
            foreach ($attr['errors'] as $error) {
                $html .= '<li>'.$this->escape($error->getMessageTemplate()).'</li>' . PHP_EOL;
            }
            $html .= '</ul> . PHP_EOL';
        }
        return $html;
    }

    protected function file_widget($attr)
    {
        $html = '<input type="file" ' . $this->attributes($attr['renderer']['file']->getVars()) . '/>' . PHP_EOL;
        $html .= $attr['renderer']['token']->getWidget();
        $html .= $attr['renderer']['name']->getWidget();
        return $html;
    }

    protected function form_widget($attr)
    {
        $html = '<div>' . $attr['renderer']->getErrors();
        foreach ($attr['renderer'] as $child) {
            $html .= $child->getRow();
        }
        $html .= $attr['renderer']->getRest();
        return $html;
    }

    protected function hidden_row($attr)
    {
        // Dont render hidden rows, they are rendered in $form->getRest()
        return '';
    }

    protected function hidden_widget($attr)
    {
        return '<input type="hidden" ' . $this->attributes($attr). ' />' . PHP_EOL;
    }

    protected function integer_widget($attr)
    {
        return $this->number_widget($attr);
    }

    protected function field_label($attr)
    {
        return '<label for="' . $this->escape($attr['id']) . '">'.$this->escape($attr['label']) . '</label>' . PHP_EOL;
    }

    protected function money_widget($attr)
    {
        return str_replace('{{ widget }}', $this->number_widget($attr), $attr['money_pattern']);
    }

    protected function number_widget($attr)
    {
        return '<input type="number" ' . $this->attributes($attr) . '/>' . PHP_EOL;
    }

    protected function password_widget($attr)
    {
        return '<input type="password" ' . $this->attributes($attr) . '/>' . PHP_EOL;
    }

    protected function percent_widget($attr)
    {
        return $this->number_widget($attr) . ' %';
    }

    protected function radio_widget($attr)
    {
        $html = '<input type="radio" ' . $this->attributes($attr);
        if ($attr['checked']) {
            $html .= 'checked="checked" ';
        }
        $html .= '/>' . PHP_EOL;
        return $html;
    }

    protected function repeated_row($attr)
    {
        $html = '';
        foreach ($attr['renderer'] as $child) {
            $html .= $child->getRow();
        }
        return $html;
    }

    protected function field_rest($attr)
    {
        $html = '';
        foreach ($attr['renderer'] as $child) {
            if (!$child->isRendered()) {
                $html .= $child->getRow();
            }
        }
        return $html;
    }

    protected function field_row($attr)
    {
        return '<div>' . $attr['renderer']->getLabel() . $attr['renderer']->getErrors() . $attr['renderer']->getWidget() . '</div>' . PHP_EOL;
    }

    protected function text_widget($attr)
    {
        return '<input type="text" ' . $this->attributes($attr) . ' />' . PHP_EOL;
    }

    protected function textarea_widget($attr)
    {
        $html = '<textarea id="' . $this->escape($attr['id']) . "' " .
                'name="' . $this->escape($attr['name']) . "' ";
        if ($attr['disabled']) {
            $html .= 'disabled="disabled" ';
        }
        if ($attr['required']) {
            $html .= 'required="required" ';
        }
        if ($attr['class']) {
            $html .= 'class="' . $this->escape($attr['class']) . '" ';
        }
        $html .= '>' . $this->escape($value) . '</textarea>' . PHP_EOL;
        return $html;
    }

    protected function time_widget($attr)
    {
        $html = $attr['renderer']['hour']->getWidget(array('size' => 1));
        $html .=  ':' .$attr['renderer']['minute']->getWidget(array('size' => 1));
        if ($attr['with_seconds']) {
            $html .=  ':' .$attr['renderer']['second']->getWidget(array('size' => 1));
        }
        return $html . PHP_EOL;
    }

    protected function url_widget($attr)
    {
        return '<input type="url" ' . $this->attributes($attr) . '/>' . PHP_EOL;
    }

    protected function widget($attr)
    {
        return '<input type="'. $attr['type'] . '" ' . $this->attributes($attr) . '/>' . PHP_EOL;
    }

    protected function attributes($attr)
    {
        $html = 'id="' . $this->escape($attr['id']).'" ';
        $html .= 'name="' . $this->escape($attr['name']).'" ';
        if ($attr['value']) {
            $html .= 'value="' . $this->escape($attr['value']) .'" ';
        }
        if ($attr['disabled']) {
            $html .= 'disabled="disabled" ';
        }
        if ($attr['required']) {
            $html .= 'required="required" ';
        }
        if ($attr['class']) {
            $html .= 'class="' . $this->escape($attr['class']) . '" ';
        }
        if (isset($attr['size']) && $attr['size'] > 0) {
            $html .= 'size="' . $this->escape($attr['size']) . '" ';
        }
        if (isset($attr['max_length']) && $attr['max_length'] > 0) {
            $html .= 'maxlength="' . $this->escape($attr['max_length']) . '" ';
        }
        if (isset($attr['attr'])) {
            foreach ($attr['attr'] as $k => $v) {
                $html .= $this->escape($k).'="'.$this->escape($v).'" ';
            }
        }

        return $html;
    }

    protected function escape($val)
    {
        return htmlentities($val, \ENT_QUOTES, $this->charset);
    }
}
