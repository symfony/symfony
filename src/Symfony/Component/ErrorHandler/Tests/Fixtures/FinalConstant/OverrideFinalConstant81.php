<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant;

class OverrideFinalConstant81 implements FinalConstantsInterface2
{
    public const FOO = 'FOO';

    public const OVERRIDDEN_FINAL_INTERFACE = 'O_OVERRIDDEN_FINAL_INTERFACE';

    public const OVERRIDDEN_NOT_FINAL_INTERFACE = 'O_OVERRIDDEN_NOT_FINAL_INTERFACE';

    public const OVERRIDDEN_FINAL_INTERFACE_2 = 'O_OVERRIDDEN_FINAL_INTERFACE_2';

    public const OVERRIDDEN_NOT_FINAL_INTERFACE_2 = 'O_OVERRIDDEN_NOT_FINAL_INTERFACE_2';

    private const CCC = 'CCC';
}
