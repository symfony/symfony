<?php

namespace Symfony\Component\AccessControl;

/**
 * @experimental
 */
enum DecisionVote: string
{
    case ACCESS_GRANTED = 'ACCESS_GRANTED';
    case ACCESS_DENIED= 'ACCESS_DENIED';
    case ACCESS_ABSTAIN= 'ACCESS_ABSTAIN';
}
