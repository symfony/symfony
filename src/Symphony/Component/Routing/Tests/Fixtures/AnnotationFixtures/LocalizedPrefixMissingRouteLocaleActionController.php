<?php

namespace Symphony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symphony\Component\Routing\Annotation\Route;

/**
 * @Route(path={"nl": "/nl", "en": "/en"})
 */
class LocalizedPrefixMissingRouteLocaleActionController
{
    /**
     * @Route(path={"nl": "/actie"}, name="action")
     */
    public function action()
    {
    }
}
