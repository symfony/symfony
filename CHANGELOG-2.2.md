CHANGELOG for 2.2.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.2 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.2.0...v2.2.1

* 2.2.0-RC3 (2013-02-24)

 * b2080c4: [HttpFoundation] Remove Cache-Control when using https download via IE<9 (fixes #6750)
 * b7bd630: [Form] Fixed TimeType not to render a "size" attribute in select tags
 * 368f62f: Expanded fault-tolerance for unusual cookie dates
 * 171cff0: [FrameworkBundle] Fix a BC for Hinclude global template
 * 3e40c17: [HttpKernel] fixed locale management when exiting sub-requests
 * 3933912: fixed HInclude renderer (closes #7113)
 * 189fba6: Removed some leaking deprecation warning in the Form component
 * d0e4b76: [HttpFoundation] fixed, overwritten CONTENT_TYPE
 * 609636e: [Config] tweaked dumper to indent multi-line info
 * 0eff68f: Fix REMOTE_ADDR for cached subrequests
 * 54d7d25: [HttpKernel] hinclude fragment renderer must escape URIs properly to return valid html
 * f842ae6: [FrameworkBundle] CSRF should be on by default
 * cb319ac: [HttpKernel] added error display suppression when using the ErrorHandler (if not, errors are displayed twice, refs #6254)
 * de0f7b7: [HttpFoundation] Added getter for httpMethodParameterOverride state
