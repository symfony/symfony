<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DataCollector;

use Symfony\Bundle\TwigBundle\Debug\TraceableTwigEngine;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TwigDataCollector.
 *
 * @author Vincent Bouzeran <vincent.bouzeran@elao.com>
 * @author Pierre Minnieur <pm@pierre-minnieur.de>
 */
class TwigDataCollector extends DataCollector
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var TraceableTwigEngine
     */
    private $templating;

    /**
     * @var \Twig_Environment
     */
    private $twig;

    /**
     * The Constructor for the Twig Datacollector
     *
     * @param KernelInterface $kernel A KernelInterface instance
     * @param TraceableTwigEngine $engine A TraceableTwigEngine instance
     * @param \Twig_Environment $twig Twig Enviroment
     */
    public function __construct(KernelInterface $kernel, TraceableTwigEngine $templating, \Twig_Environment $twig)
    {
        $this->kernel = $kernel;
        $this->templating = $templating;
        $this->twig = $twig;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $filters = array();
        $tests = array();
        $extensions = array();
        $functions = array();
        $templates = array();

        foreach ($this->twig->getExtensions() as $extensionName => $extension) {
            $extensions[] = array(
                'name' => $extensionName,
                'class' => get_class($extension)
            );
            foreach ($extension->getFilters() as $filterName => $filter) {
                $filters[] = array(
                    'name' => $filterName,
                    'call' => $filter->compile(),
                    'extension' => $extensionName
                );
            }

            foreach ($extension->getTests() as $testName => $test) {
                $tests[] = array(
                    'name' => $testName,
                    'call' => $test->compile(),
                    'extension' => $extensionName
                );
            }

            foreach ($extension->getFunctions() as $functionName => $function) {
                $functions[] = array(
                    'name' => $functionName,
                    'call' => $function->compile(),
                    'extension' => $extensionName
                );
            }
        }

        $templates = array();
        foreach ($this->templating->getRenderedTemplates() as $template) {
            $name = $template['name'];
            if (!array_key_exists($name, $templates)) {
                $reference = $template['reference'];
                $templates[$name] = array(
                    'name' => $name,
                    'reference' => $reference,
                    'resource' => $this->kernel->locateResource($reference->getPath()),
                    'counter' => 0,
                );
            }

            $templates[$name]['counter']++;
        }

        $this->data['extensions'] = $extensions;
        $this->data['tests'] = $tests;
        $this->data['filters'] = $filters;
        $this->data['functions'] = $functions;
        $this->data['templates'] = $templates;
    }

    /**
     * Returns the rendered templates.
     *
     * @return array
     */
    public function getTemplates()
    {
        return $this->data['templates'];
    }

    /**
     * Returns the available extensions.
     *
     * @return array
     */
    public function getExtensions()
    {
        return $this->data['extensions'];
    }

    /**
     * Returns the available tests.
     *
     * @return array
     */
    public function getTests()
    {
        return $this->data['tests'];
    }

    /**
     * Returns the available filters.
     *
     * @return array
     */
    public function getFilters()
    {
        return $this->data['filters'];
    }

    /**
     * Returns the available functions.
     *
     * @return array
     */
    public function getFunctions()
    {
        return $this->data['functions'];
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'twig';
    }
}
