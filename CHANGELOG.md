# Retcon Changelog


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
