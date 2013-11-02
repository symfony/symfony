CHANGELOG for 2.4.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.4 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.4.0...v2.4.1

* 2.4.0-BETA2 (2013-10-30)

 * bug #9408 [Form] Fixed failing FormDataExtractorTest (bschussek)
 * bug #9397 [BUG][Form] Fix nonexistent key id in twig of data collector (francoispluchino)
 * bug #9395 [HttpKernel] fixed memory limit display in MemoryDataCollector (hhamon)
 * bug #9168 [FrameworkBundle] made sure that the debug event dispatcher is used everywhere (fabpot)
 * bug #9388 [Form] Fixed: The "data" option is taken into account even if it is NULL (bschussek)
 * bug #9394 [Form] Fixed form debugger to work even when no view variables are logged (bschussek)
 * bug #9391 [Serializer] Fixed the error handling when decoding invalid XML to avoid a Warning (stof)
 * feature #9365 prevent PHP from magically setting a 302 header (lsmith77)
 * feature #9252 [FrameworkBundle] Only enable CSRF protection when enabled in config (asm89)
 * bug #9378 [DomCrawler] [HttpFoundation] Make `Content-Type` attributes identification case-insensitive (matthieuprat)
 * bug #9354 [Process] Fix #9343 : revert file handle usage on Windows platform (romainneutron)
 * bug #9335 [Form] Improved FormTypeCsrfExtension to use the type class as default intention if the form name is empty (bschussek)
 * bug #9334 [Form] Improved FormTypeCsrfExtension to use the type class as default intention if the form name is empty (bschussek)
 * bug #9333 [Form] Improved FormTypeCsrfExtension to use the type class as default intention if the form name is empty (bschussek)
 * bug #9338 [DoctrineBridge] Added type check to prevent calling clear() on arrays (bschussek)
 * bug #9330 [Config] Fixed namespace when dumping reference (WouterJ)
 * bug #9329 [Form] Changed FormTypeCsrfExtension to use the form's name as default token ID (bschussek)
 * bug #9328 [Form] Changed FormTypeCsrfExtension to use the form's name as default intention (bschussek)
 * bug #9327 [Form] Changed FormTypeCsrfExtension to use the form's name as default intention (bschussek)
 * bug #9316 [WebProfilerBundle] Fixed invalid condition in form panel (bschussek)
 * bug #9308 [DoctrineBridge] Loosened CollectionToArrayTransformer::transform() to accept arrays (bschussek)
 * bug #9297 [Form] Add missing use in form renderer (egeloen)
 * bug #9309 [Routing] Fixed unresolved class (francoispluchino)
 * bug #9274 [Yaml] Fixed the escaping of strings starting with a dash when dumping (stof)
 * bug #9270 [Templating] Fix in ChainLoader.php (janschoenherr)
 * bug #9246 [Session] fixed wrong started state (tecbot)
 * bug #9234 [Debug] Fixed `ClassNotFoundFatalErrorHandler` (tPl0ch)
 * bug #9259 [Process] Fix latest merge from 2.2 in 2.3 (romainneutron)
 * bug #9237 [FrameworkBundle] assets:install command should mirror .dotfiles (.htaccess) (FineWolf)
 * bug #9223 [Translator] PoFileDumper - PO headers (Padam87)
 * bug #9257 [Process] Fix 9182 : random failure on pipes tests (romainneutron)
 * bug #9236 [Form] fix missing use statement for exception UnexpectedTypeException (jaugustin)
 * bug #9222 [Bridge] [Propel1] Fixed guessed relations (ClementGautier)
 * bug #9214 [FramworkBundle] Check event listener services are not abstract (lyrixx)
 * bug #9207 [HttpKernel] Check for lock existence before unlinking (ollietb)
 * bug #9184 Fixed cache warmup of paths which contain back-slashes (fabpot)
 * bug #9192 [Form] remove MinCount and MaxCount constraints in ValidatorTypeGuesser (franek)

* 2.4.0-BETA1 (2013-10-07)

 * first beta release

