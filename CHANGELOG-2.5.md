CHANGELOG for 2.5.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.5 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.5.0...v2.5.1

* 2.5.0-BETA2 (2014-04-29)

 * bug #10803 [Debug] fix ErrorHandlerTest when context is not an array (nicolas-grekas)
 * bug #10801 [Debug] ErrorHandler: remove $GLOBALS from context in PHP5.3 fix #10292 (nicolas-grekas)
 * bug #10799 [Debug] less intrusive work around for https://bugs.php.net/54275 (nicolas-grekas)
 * bug #10797 [HttpFoundation] Allow File instance to be passed to BinaryFileResponse (anlutro)
 * bug #10798 [Console] Fix #10795: Allow instancing Console Application when STDIN is not declared (romainneutron)
 * bug #10643 [TwigBridge] Removed strict check when found variables inside a translation (goetas)
 * bug #10605 [ExpressionLanguage] Strict in_array check in Parser.php (parnas)
 * bug #10789 [Console] Fixed the rendering of exceptions on HHVM with a terminal width (stof)
 * bug #10773 [WebProfilerBundle ] Fixed an edge case on WDT loading (tucksaun)
 * feature #10786 [FrameworkBundle] removed support for HHVM built-in web server as it is deprecated now (fabpot)
 * bug #10784 [Security] removed $csrfTokenManager type hint from SimpleFormAuthenticationListener constructor argument (choonge)
 * bug #10776 [Debug] fix #10771 DebugClassLoader can't load PSR4 libs (nicolas-grekas)
 * bug #10763 [Process] Disable TTY mode on Windows platform (romainneutron)
 * bug #10772 [Finder] Fix ignoring of unreadable dirs in the RecursiveDirectoryIterator (jakzal)
 * bug #10757 [Process] Setting STDIN while running should not be possible (romainneutron)
 * bug #10749 Fixed incompatibility of x509 auth with nginx (alcaeus)
 * feature #10725 [Debug] Handled errors (nicolas-grekas)
 * bug #10735 [Translation] [PluralizationRules] Little correction for case 'ar' (klyk50)
 * bug #10720 [HttpFoundation] Fix DbalSessionHandler  (Tobion)
 * bug #10721 [HttpFoundation] status 201 is allowed to have a body (Tobion)
 * bug #10728 [Process] Fix #10681, process are failing on Windows Server 2003 (romainneutron)
 * bug #10733 [DomCrawler] Textarea value should default to empty string instead of null. (Berdir)
 * bug #10723 [Security] fix DBAL connection typehint (Tobion)
 * bug #10715 [Debug] Fixed ClassNotFoundFatalErrorHandler on windows. (lyrixx)
 * bug #10700 Fixes various inconsistencies in the code (fabpot)
 * bug #10697 [Translation] Make IcuDatFileLoader/IcuResFileLoader::load invalid resource compatible with HHVM. (idn2104)

* 2.5.0-BETA1 (2014-04-11)

 * first beta release

