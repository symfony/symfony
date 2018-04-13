<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Translation\Extractor;

use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Extracts validation translation messages.
 *
 * @author Webnet team <comptewebnet@webnet.fr>
 */
class ChainExtractor implements ExtractorInterface
{
    /**
     * The extractors.
     *
     * @var ExtractorInterface[]
     */
    private $extractors = array();

    /**
     * Adds an extractor.
     *
     * @param ExtractorInterface $extractor
     */
    public function addExtractor(ExtractorInterface $extractor)
    {
        $this->extractors[] = $extractor;
    }

    /**
     * {@inheritdoc}
     */
    public function setPrefix($prefix)
    {
        foreach ($this->extractors as $extractor) {
            $extractor->setPrefix($prefix);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function extract($resource, MessageCatalogue $catalogue)
    {
        foreach ($this->extractors as $extractor) {
            $extractor->extract($resource, $catalogue);
        }
    }
}
