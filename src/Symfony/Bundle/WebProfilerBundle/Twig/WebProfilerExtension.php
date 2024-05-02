<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Twig;

use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Twig\Environment;
use Twig\Extension\EscaperExtension;
use Twig\Extension\ProfilerExtension;
use Twig\Profiler\Profile;
use Twig\Runtime\EscaperRuntime;
use Twig\TwigFunction;

/**
 * Twig extension for the profiler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class WebProfilerExtension extends ProfilerExtension
{
    private HtmlDumper $dumper;

    /**
     * @var resource
     */
    private $output;

    private int $stackLevel = 0;

    public function __construct(?HtmlDumper $dumper = null)
    {
        $this->dumper = $dumper ?? new HtmlDumper();
        $this->dumper->setOutput($this->output = fopen('php://memory', 'r+'));
    }

    public function enter(Profile $profile): void
    {
        ++$this->stackLevel;
    }

    public function leave(Profile $profile): void
    {
        if (0 === --$this->stackLevel) {
            $this->dumper->setOutput($this->output = fopen('php://memory', 'r+'));
        }
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('profiler_dump', $this->dumpData(...), ['is_safe' => ['html'], 'needs_environment' => true]),
            new TwigFunction('profiler_dump_log', $this->dumpLog(...), ['is_safe' => ['html'], 'needs_environment' => true]),
        ];
    }

    public function dumpData(Environment $env, Data $data, int $maxDepth = 0): string
    {
        $this->dumper->setCharset($env->getCharset());
        $this->dumper->dump($data, null, [
            'maxDepth' => $maxDepth,
        ]);

        $dump = stream_get_contents($this->output, -1, 0);
        rewind($this->output);
        ftruncate($this->output, 0);

        return str_replace("\n</pre", '</pre', rtrim($dump));
    }

    public function dumpLog(Environment $env, string $message, ?Data $context = null): string
    {
        $message = self::escape($env, $message);
        $message = preg_replace('/&quot;(.*?)&quot;/', '&quot;<b>$1</b>&quot;', $message);

        $replacements = [];
        foreach ($context ?? [] as $k => $v) {
            $k = '{'.self::escape($env, $k).'}';
            if (str_contains($message, $k)) {
                $replacements[$k] = $v;
            }
        }

        if (!$replacements) {
            return '<span class="dump-inline">'.$message.'</span>';
        }

        foreach ($replacements as $k => $v) {
            $replacements['&quot;<b>'.$k.'</b>&quot;'] = $replacements['&quot;'.$k.'&quot;'] = $replacements[$k] = $this->dumpData($env, $v);
        }

        return '<span class="dump-inline">'.strtr($message, $replacements).'</span>';
    }

    public function getName(): string
    {
        return 'profiler';
    }

    private static function escape(Environment $env, string $s): string
    {
        // Twig 3.10 and above
        if (class_exists(EscaperRuntime::class)) {
            return $env->getRuntime(EscaperRuntime::class)->escape($s);
        }

        // Twig 3.9
        if (method_exists(EscaperExtension::class, 'escape')) {
            return EscaperExtension::escape($env, $s);
        }

        // to be removed when support for Twig 3 is dropped
        return twig_escape_filter($env, $s);
    }
}
