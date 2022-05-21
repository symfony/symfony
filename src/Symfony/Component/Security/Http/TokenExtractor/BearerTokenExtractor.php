<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\TokenExtractor;

use Symfony\Component\HttpFoundation\Request;

/**
 * @author Vincent Chalamon <vincentchalamon@gmail.com>
 *
 * @final
 */
class BearerTokenExtractor implements BearerTokenExtractorInterface
{
    /**
     * @var BearerTokenExtractorInterface[]
     */
    private iterable $extractors;

    public function __construct(iterable $extractors)
    {
        $this->extractors = $extractors;
    }

    public function supports(Request $request): bool
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($request)) {
                return true;
            }
        }

        return false;
    }

    public function extract(Request $request): string
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($request)) {
                return $extractor->extract($request);
            }
        }

        // todo throw BearerTokenNotFoundException
        return '';
    }
}
