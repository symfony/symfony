CHANGELOG for 2.3.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.3 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.3.0...v2.3.1

* 2.3.0-BETA2 (2013-05-10)

 * 97bee20: Pass exceptions from the ExceptionListener to Monolog
 * be42dbc: [HttpFoundation][File][UploadedFile] Fix guessClientExtension() method
 * a5441b2: Fixed parsing of leading blank lines in folded scalars. Closes #7989.
 * e8d5d16: Fixed Loader import
 * bd0c48c: [Console] moved the IO configuration to its own method
 * fdb4b1f: [Console] moved --help support to allow proper behavior with other passed options
 * dd0e138: Eased translationNodeVisitor overriding in TranslationExtension
 * 853f681: fixed request scope issues (refs #7457)
 * 60edc58: Fixed fatal error in normalize/denormalizeObject.
 * 78e3710: ProxyManager Bridge
 * 41805c0: [Crawler] Add proper validation of node argument of method add
 * 7933971: [Form] Added radio button for empty value to expanded single-choice fields
 * 0586c7e: made some optimization when parsing YAML files
 * 1856df3: [Security] fixed wrong merge (refs #4776)
 * 5b7e1e6: added a missing check for the provider key
 * f1c2ab7: [DependencyInjection] Add a method map to avoid computing method names from service names
 * ea633f5: [HttpKernel] Avoid updating the context if the request did not change
 * 997d549: [HttpFoundation] Avoid a few unnecessary str_replace() calls
 * f5e7f24: [HttpFoundation] Optimize ServerBag::getHeaders()
 * 59b78c7: [Validator] Fixed: $traverse and $deep is passed to the visitor from Validator::validate()
 * bcb5400: [Form] Fixed transform()/reverseTransform() to always throw TransformationFailedExceptions
 * 7b2ebbf: [Form] Fixed: String validation groups are never interpreted as callbacks
 * 0610750: if the repository method returns an array ensure that it's internal poin...
 * dcced01: [Form] Improved multi-byte handling of NumberToLocalizedStringTransformer
 * 90a20d7: [Translation] Made translation domain defaults in Translator consistent with TranslatorInterface
 * 549a308: [Form] Fixed CSRF error messages to be translated and added "csrf_message" option
