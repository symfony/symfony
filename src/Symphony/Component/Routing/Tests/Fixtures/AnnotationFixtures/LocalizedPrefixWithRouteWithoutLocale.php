<?php

namespace Symphony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symphony\Component\Routing\Annotation\Route;

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
