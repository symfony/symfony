<?php

namespace Symphony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symphony\Component\Routing\Annotation\Route;

/**
 * @Route(path={"nl": "/nl"})
 */
class LocalizedPrefixMissingLocaleActionController
{
    /**
     * @Route(path={"nl": "/actie", "en": "/action"}, name="action")
     */
    public function action()
    {
    }
}
