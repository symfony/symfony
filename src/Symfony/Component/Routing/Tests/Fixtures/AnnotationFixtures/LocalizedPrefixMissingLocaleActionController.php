<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Attribute\Route;

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
