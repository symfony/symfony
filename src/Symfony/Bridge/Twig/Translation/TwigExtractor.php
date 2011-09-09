<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Translation;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Bridge\Twig\Node\TransNode;

/**
 * TwigExtractor extracts translation messages from a twig template.
 * 
 * @author Michel Salib <michelsalib@hotmail.com>
 */
class TwigExtractor implements ExtractorInterface
{
    /**
     * Default domain for found messages.
     *
     * @var string
     */
    private $defaultDomain = '';
    
    /**
     * Prefix for found message.
     *
     * @var string
     */
    private $prefix = '';
    
    /**
     * The twig environment.
     * @var \Twig_Environment
     */
    private $twig;
    
    public function __construct(\Twig_Environment $twig)
    {
        $this->twig = $twig;
    }
    
    /**
     * {@inheritDoc}
     */
    public function extract($directory, MessageCatalogue $catalogue)
    {
        // load any existing translation files
        $finder = new Finder();
        $files = $finder->files()->name('*.twig')->in($directory);
        foreach ($files as $file) {
            $tree = $this->twig->parse($this->twig->tokenize(file_get_contents($file->getPathname())));
            $this->crawlNode($tree, $catalogue);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }
    
    /**
     * Extracts trans message from a twig tree.
     *
     * @param \Twig_Node       $node      The twig tree root
     * @param MessageCatalogue $catalogue The catalogue
     */
    private function crawlNode(\Twig_Node $node, MessageCatalogue $catalogue)
    {
        if ($node instanceof TransNode && !$node->getNode('body') instanceof \Twig_Node_Expression_GetAttr) {
            // trans block
            $message = $node->getNode('body')->getAttribute('data');
            $domain = $node->getNode('domain')->getAttribute('value');
            $catalogue->set($message, $this->prefix.$message, $domain);
        } elseif ($node instanceof \Twig_Node_Print) {
            // trans filter (be carefull of how you chain your filters)
            $message = $this->extractMessage($node->getNode('expr'));
            $domain = $this->extractDomain($node->getNode('expr'));
            if ($message !== null && $domain !== null) {
                 $catalogue->set($message, $this->prefix.$message, $domain);
            }
        } else {
            // continue crawling
            foreach ($node as $child) {
                if ($child != null) {
                    $this->crawlNode($child, $catalogue);
                }
            }
        }
    }

    /**
     * Extracts a message from a \Twig_Node_Print.
     * Return null if not a constant message.
     *
     * @param \Twig_Node $node
     * @return The message (or null)
     */
    private function extractMessage(\Twig_Node $node)
    {
        if ($node->hasNode('node')) {
            return $this->extractMessage($node->getNode('node'));
        }
        if ($node instanceof \Twig_Node_Expression_Constant) {
            return $node->getAttribute('value');
        }

        return null;
    }

    /**
     * Extracts a domain from a \Twig_Node_Print.
     * Return null if no trans filter.
     *
     * @param \Twig_Node $node
     * @return The domain (or null)
     */
    private function extractDomain(\Twig_Node $node)
    {
        // must be a filter node
        if (!$node instanceof \Twig_Node_Expression_Filter) {
            return null;
        }
        // is a trans filter
        if ($node->getNode('filter')->getAttribute('value') === 'trans') {
            if ($node->getNode('arguments')->hasNode(1)) {
                return $node->getNode('arguments')->getNode(1)->getAttribute('value');
            }
            
            return $this->defaultDomain;
        }

        return $this->extractDomain($node->getNode('node'));
    }
}

