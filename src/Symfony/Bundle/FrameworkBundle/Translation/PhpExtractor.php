<?php

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Extractor\ExtractorInterface;

/**
 * Extract translation messages from a php template
 */
class PhpExtractor implements ExtractorInterface
{
    const MESSAGE_TOKEN = 300;
    const IGNORE_TOKEN = 400;
    
    /**
     * Prefix for found message
     *
     * @var string
     */
    private $prefix = '';

    /**
     * The sequence that captures translation messages
     * 
     * @var array 
     */
    protected $sequences = array(
        array(
            '$view',
            '[',
            '\'translator\'',
            ']',
            '->',
            'trans',
            '(',
            self::MESSAGE_TOKEN,
            ')',
        ),
    );

    /**
     * {@inheritDoc}
     */
    public function load($directory, MessageCatalogue $catalog)
    {
        // load any existing translation files
        $finder = new Finder();
        $files = $finder->files()->name('*.php')->in($directory);
        foreach ($files as $file) {
            $this->parseTokens(token_get_all(file_get_contents($file)), $catalog);
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
     * Normalize a token
     * 
     * @param mixed $token
     * @return string 
     */
    protected function normalizeToken($token)
    {
        if (is_array($token)) {
            return $token[1];
        }
        
        return $token;
    }

    /**
     * Extract trans message from php tokens
     * 
     * @param array $tokens
     * @param MessageCatalogue $catalog 
     */
    protected function parseTokens($tokens, MessageCatalogue $catalog)
    {
        foreach ($tokens as $key => $token) {
            foreach ($this->sequences as $sequence) {
                $message = '';

                foreach ($sequence as $id => $item) {
                    if($this->normalizeToken($tokens[$key + $id]) == $item) {
                        continue;
                    } elseif (self::MESSAGE_TOKEN == $item) {
                        $message = $this->normalizeToken($tokens[$key + $id]);
                    } elseif (self::IGNORE_TOKEN == $item) {
                        continue;
                    } else {
                        break;
                    }
                }

                if ($message) {
                    $catalog->set($message, $this->prefix.$message);
                    break;
                }
            }
        }
    }
}
