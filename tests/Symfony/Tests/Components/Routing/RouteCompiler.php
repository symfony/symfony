<?php

namespace Symfony\Tests\Components\Routing;

use Symfony\Components\Routing\RouteCompiler as BaseRouteCompiler;
use Symfony\Components\Routing\Route;

class RouteCompiler extends BaseRouteCompiler
{
    protected function tokenizeBufferBefore(&$buffer, &$tokens, &$afterASeparator, &$currentSeparator)
    {
        if ($afterASeparator && preg_match('#^=('.$this->options['variable_regex'].')#', $buffer, $match)) {
            // a labelled variable
            $this->tokens[] = array('label', $currentSeparator, $match[0], $match[1]);

            $currentSeparator = '';
            $buffer = substr($buffer, strlen($match[0]));
            $afterASeparator = false;
        } else {
            return false;
        }
    }

    protected function compileForLabel($separator, $name, $variable)
    {
        if (null === $requirement = $this->route->getRequirement($variable)) {
            $requirement = $this->options['variable_content_regex'];
        }

        $this->segments[] = preg_quote($separator, '#').$variable.$separator.'(?P<'.$variable.'>'.$requirement.')';
        $this->variables[$variable] = $name;

        if (!$this->route->getDefault($variable)) {
            $this->firstOptional = count($this->segments);
        }
    }

    protected function generateForLabel($optional, $tparams, $separator, $name, $variable)
    {
        if (!empty($tparams[$variable]) && (!$optional || !isset($this->defaults[$variable]) || $tparams[$variable] != $this->defaults[$variable])) {
            return $variable.'/'.urlencode($tparams[$variable]);
        }
    }
}
