<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path={"en": "/en", "nl": "/nl"})
 */
class LocalizedPrefixWithRouteWithoutLocale
{
    /**
     * @Route("/suffix", name="action")
     */
    public function action()
    {
    }
}
