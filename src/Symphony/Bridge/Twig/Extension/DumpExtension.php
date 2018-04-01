<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Twig\Extension;

use Symphony\Bridge\Twig\TokenParser\DumpTokenParser;
use Symphony\Component\VarDumper\Cloner\ClonerInterface;
use Symphony\Component\VarDumper\Dumper\HtmlDumper;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Template;
use Twig\TwigFunction;

/**
 * Provides integration of the dump() function with Twig.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DumpExtension extends AbstractExtension
{
    private $cloner;
    private $dumper;

    public function __construct(ClonerInterface $cloner, HtmlDumper $dumper = null)
    {
        $this->cloner = $cloner;
        $this->dumper = $dumper;
    }

    public function getFunctions()
    {
        return array(
            new TwigFunction('dump', array($this, 'dump'), array('is_safe' => array('html'), 'needs_context' => true, 'needs_environment' => true)),
        );
    }

    public function getTokenParsers()
    {
        return array(new DumpTokenParser());
    }

    public function getName()
    {
        return 'dump';
    }

    public function dump(Environment $env, $context)
    {
        if (!$env->isDebug()) {
            return;
        }

        if (2 === func_num_args()) {
            $vars = array();
            foreach ($context as $key => $value) {
                if (!$value instanceof Template) {
                    $vars[$key] = $value;
                }
            }

            $vars = array($vars);
        } else {
            $vars = func_get_args();
            unset($vars[0], $vars[1]);
        }

        $dump = fopen('php://memory', 'r+b');
        $this->dumper = $this->dumper ?: new HtmlDumper();
        $this->dumper->setCharset($env->getCharset());

        foreach ($vars as $value) {
            $this->dumper->dump($this->cloner->cloneVar($value), $dump);
        }

        return stream_get_contents($dump, -1, 0);
    }
}
