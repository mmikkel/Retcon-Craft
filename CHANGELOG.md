# Retcon Changelog

## 2.0.12 - 2018-11-09
### Improved
- The `retconRemoveEmpty` will no longer remove `<br/>` tags, unless the second parameter `$removeBr` is set to `true`

## 2.0.11 - 2018-10-08
### Fixed
- Fixes an issue where the `retconOnly` filter could throw an exception if the HTML contained tags that weren't well-formed (e.g. `img` tags that aren't explicitly closed). Thanks a lot @jcdarwin!

## 2.0.10 - 2018-08-17
### Fixed
- Fixes an issue where Retcon would return escaped HTML if no matching selectors were found

## 2.0.9 - 2018-08-16
### Fixed
- Fixes an issue where using Retcon on a string with only whitespace could throw an exception

## 2.0.8 - 2018-08-13
### Fixed
- Various minor issues ironed out (thanks, Scrutinizer)
### Improved
- The `overwrite` parameter for the `attr()` method can now be set to a string `'prepend'`, to _prepend_ rather than append values to existing attributes

## 2.0.7 - 2018-08-07
### Fixed
- Fixes an issue where Retcon could throw an exception if given a NULL value instead of a string (e.g. if a Redactor field had been added to a Field Layout, without re-saving the entries)

## 2.0.6 - 2018-08-05
### Fixed
- Fixes an issue where Retcon would attempt to use Imager for transforms, even if Imager was not installed or deactivated

## 2.0.5 - 2018-07-28
### Fixed
- Fixes a bug where the `transform` method would return an empty string if Retcon would fail to transform an image
- Fixes various issues related to the project config file (i.e. `/config/retcon.php`) and plugin settings

### Improved
- Improves image path handling for the `srcset` and `transform` methods

## 2.0.4 - 2018-07-25
### Fixed
- Fixes a recently introduced regression error in the "retcon" catch-all method, affecting the "srcset" method and others.

## 2.0.3 - 2018-07-21
### Fixed
- Retcon no longers attempt to decode quote entities, to fix an issue where serialized data in HTML attributes were getting butchered

## 2.0.2 - 2018-07-18
### Fixed
- Various minor issues fixed

## 2.0.1 - 2018-07-18
### Fixed
- Fixes various issues with the `retcon` catch-all filter
- Fixes an issue where Retcon would entity encode HTML tags when no nodes matched the given selector for methods such as `srcset`, `transform` and others
- Fixes an issue where Retcon would entity encode non-breaking spaces

## 2.0.0 - 2018-07-18
### Added
- New filter `retconRemoveEmpty` added, which will remove empty DOM nodes (e.g. `<p>` tags without text content)

### Improved
- Selectors are much, _much_ more flexible (almost all CSS selectors work, which means that Retcon is basically jQuery now!) due to the magic of Symfony's DomCrawler component
- **Full HTML5 support**
- The `retconAutoAlt` filter will use the Asset's `title` for the `alt` attribute, if Retcon is fed markup with Craft CMS reference tags
- The `retconTransform`, `retconLazy` and `retconSrcSet` filters now take an additional parameter – `selector` (defaults to `'img'`)

## 1.0.1 - 2018-06-07
### Improved
- Adds support for the `limit` parameter in the "replace" filter (`preg_replace` wrapper)

## 1.0.0-beta1 - 2018-03-05
### Added
- Beta release
