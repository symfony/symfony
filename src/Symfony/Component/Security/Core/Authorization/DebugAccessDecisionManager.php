<?php


namespace Symfony\Component\Security\Core\Authorization;

/**
 * @internal
 * @deprecated The DebugAccessDecisionManager class has been renamed and is deprecated since version 3.3 and will be removed in 4.0. Use the TraceableAccessDecisionManager class instead.
 * 
 * This is a placeholder for the old class, that got renamed; this is not a BC break since the class is internal, this 
 * placeholder is here just to help backward compatibility with older SecurityBundle versions. 
 */
class_exists(TraceableAccessDecisionManager::class);
