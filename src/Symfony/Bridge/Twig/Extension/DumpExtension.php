<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Bridge\Twig\TokenParser\DumpTokenParser;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Template;
use Twig\TokenParser\TokenParserInterface;
use Twig\TwigFunction;

/**
 * Provides integration of the dump() function with Twig.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final since Symfony 4.4
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

    /**
     * @return TwigFunction[]
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('dump', [$this, 'dump'], ['is_safe' => ['html'], 'needs_context' => true, 'needs_environment' => true]),
        ];
    }

    /**
     * @return TokenParserInterface[]
     */
    public function getTokenParsers()
    {
        return [new DumpTokenParser()];
    }

    public function getName()
    {
        return 'dump';
    }

    public function dump(Environment $env, $context)
    {
        if (!$env->isDebug()) {
            return null;
        }

        if (2 === \func_num_args()) {
            $vars = [];
            foreach ($context as $key => $value) {
                if (!$value instanceof Template) {
                    $vars[$key] = $value;
                }
            }

            $vars = [$vars];
        } else {
            $vars = \func_get_args();
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
