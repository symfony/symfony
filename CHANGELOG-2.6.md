CHANGELOG for 2.6.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.6 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.6.0...v2.6.1

* 2.6.0 (2014-11-28)

 * bug #12553 [Debug] fix error message on double exception (nicolas-grekas)
 * bug #12550 [FrameworkBundle] backport #12489 (xabbuh)
 * bug #12437  [Validator] make DateTime objects represented as strings in the violation message (hhamon)
 * bug #12575 [WebProfilerBundle] Remove usage of app.request in search bar template (jeromemacias)
 * bug #12570 Fix initialized() with aliased services (Daniel Wehner)

* 2.6.0-BETA2 (2014-11-23)

 * bug #12555 [Debug] fix ENT_SUBSTITUTE usage (nicolas-grekas)
 * feature #12538 [FrameworkBundle] be smarter when guessing the document root (xabbuh)
 * bug #12539 [TwigBundle] properly set request attributes in controller test (xabbuh)
 * bug #12267 [Form][WebProfiler] Empty form names fix (kix)
 * bug #12137 [FrameworkBundle] cache:clear command fills *.php.meta files with wrong data (Strate)
 * bug #12525 [Bundle][FrameworkBundle] be smarter when guessing the document root (xabbuh)
 * bug #12296 [SecurityBundle] Authentication entry point is only registered with firewall exception listener, not with authentication listeners (rjkip)
 * bug #12446 [Twig/DebugBundle] move dump extension registration (nicolas-grekas)
 * bug #12489 [FrameworkBundle] Fix server run in case the router script does not exist (romainneutron)
 * feature #12404 [Form] Remove timezone options from DateType and TimeType (jakzal)
 * bug #12487 [DomCrawler] Added support for 'link' tags in the Link class (StephaneSeng)
 * bug #12490 [FrameworkBundle] Fix server start in case the PHP binary is not found (romainneutron)
 * bug #12443 [HttpKernel] Adding support for invokable controllers in the RequestDataCollector (jameshalsall)
 * bug #12393 [DependencyInjection] inlined factory not referenced (boekkooi)
 * bug #12411 [VarDumper] Use Unicode Control Pictures (nicolas-grekas)
 * bug #12436 [Filesystem] Fixed case for empty folder (yosmanyga)

* 2.6.0-BETA1 (2014-11-03)

 * first beta release

