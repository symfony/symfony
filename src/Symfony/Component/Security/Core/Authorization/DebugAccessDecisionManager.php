<?php


namespace Symfony\Component\Security\Core\Authorization;

/**
 * @author Alessandro Lai <alessandro.lai85@gmail.com>
 *
 * @internal
 * @deprecated The DebugAccessDecisionManager class is deprecated since version 3.3 and will be removed in 4.0. Use the Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager class instead.
 * 
 * This is a placeholder for the old class, that got renamed; this is not a BC break since the class is internal, this 
 * placeholder is here just to help backward compatibility with older SecurityBundle versions. 
 */
class_alias(TraceableAccessDecisionManager::class, DebugAccessDecisionManager::class);
