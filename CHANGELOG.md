# Retcon Changelog

## 2.7.5 - 2023-11-11

### Fixed

- Fixed a PHP exception that could be thrown when using the `retconTransform` or `retconSrcset` with Imager (not Imager
  X) installed

## 2.7.4 - 2023-10-19

### Fixed

- Fixed an issue where the `retconSrcset` filter could use the wrong height value for the base64 placeholder `src`
  attribute

## 2.7.3 - 2023-08-01

### Fixed

- Fixed a regression introduced in Retcon 2.7.0, where Retcon would return unparsed reference tags from
  Redactor/CKEditor output, in cases where there were no nodes matching the given selector.

## 2.7.2 - 2023-07-30

### Fixed

- Fixed a regression introduced in Retcon 2.7.0, where Imager/Imager X would fail to transform external images, if the
  image URL contained a query string or was missing a file extension

## 2.7.1 - 2023-07-23

### Fixed

- Fixed a PHP exception that could occur if Retcon operated on markup containing `<svg>` nodes
- Fixed an issue where the `retconRemoveEmpty` filter could remove `<svg>`, `<iframe>` and `<object>` tags

### Changed

- Retcon now requires `symfony/dom-crawler` v. 4.4.0 or later
- Retcon now requires `symfony/css-selector` v. 3.4.0 or later
- Retcon now requires `masterminds/html5` v. 2.6.0 or later

## 2.7.0.1 - 2023-07-23

### Fixed

- Fixed a regression introduced in 2.7.0, that could remove text content from nodes returned by the `retconOnly` filter

## 2.7.0 - 2023-07-22

### Added

- Added a new `retconDimensions` filter, which can be used to set missing `width` and `height` attributes for image
  nodes.
- Added a new `$allowFilenameAsAltText` parameter to the `retconAutoAlt` filter (default `true`; will be default `false`
  in Retcon 3.0).
- Added `RetconTransformedImage` model.

### Improved

- Improved support for CKEditor.

### Changed

- The `autoAlt` filter now uses the native `alt` attribute if it has a value (Craft 4 only).
- When using Imager (X) to transform images, the `retconTransform` and `retconSrcset` filters now respect
  Imager's [`safeFileFormats`](https://imager-x.spacecat.ninja/configuration.html#safefileformats-array) configuration
  setting, i.e. these filters will only attempt to transform jpgs, gifs and pngs out of the box, unless additional
  formats have been added in Imager's config file.

### Fixed

- Fixed an issue where the `retconRemoveEmpty` filter would remove self-closing tags.
- Fixed an issue where DOMDocument could wrap text nodes in `<p>` tags.
- Fixed an issue where the `retconUnwrap` filter could unwrap root nodes.
- Fixed an issue where the `autoAlt` filter was unable to retrieve alt value from the source asset.
- Fixed an issue where the `retconAutoAlt` filter could use base64 `src` placeholders could be used for the alt value.
- Fixed a PHP exception that would occur if using `retconTransform` or `retconSrcset` with Imager X named transforms, if
  the named transform consisted of multiple transforms. Retcon will now use the first transform whenever Imager returns
  multiple transforms.
- Fixed an issue where the `retconTransform` and `retconSrcset` filters could upscale images even if
  Craft's `upscaleImages` setting was set to `false`.

## 2.6.1 - 2023-02-17

### Fixed

- Fixed a PHP exception that would be thrown when using the `retconChange` filter on empty DOM nodes

## 2.6.0 - 2022-10-12

### Changed

- Updated version constraints for the `symfony/dom-crawler` and `symfony/css-selector` dependencies, allowing their 6.0
  packages

## 2.5.0 - 2022-08-23

### Fixed

- Fixed a PHP exception that could occur when Retcon was unable to get the dimensions for an image being transformed
- Fixed a bug where the `retconTransform` filter would not apply missing `width` and `height` attributes to the `img`
  tags being transformed

### Added

- The `retconSrcset` filter now sets `img` tags `width` and `height` attributes, if they are missing in the markup and
  Retcon is able to read out the dimensions

## 2.4.3 - 2022-08-17

### Changed

- The `selector`, `sizes`, `base64src`, `className`, `attributeName`, `imagerTransformDefaults`
  and `imagerConfigOverrides` parameters are now nullable for the `retconTransform`, `retconLazy` and `retconSrcset`
  filters

## 2.4.2 - 2022-04-27

### Fixed

- Fixes an exception that would be thrown when setting `style` and `class` attributes with the `retconAttr` filter,
  using values returned by Twig macros

## 2.4.1 - 2022-04-19

### Fixed

- Reverted a breaking change in 2.4.0 where HTML entities in attribute values created with the `retconAttr` would be
  encoded

### Changed

- Array values in HTML attributes created with the `retconAttr` filter are now always JSON-encoded, excepting
  the `class` and `style` attributes, similarly to how the native `attr` filter works

## 2.4.0 - 2022-04-18

### Added

- Added support for Craft 4.0
- Added support for Imager X named transforms
- Added support for passing objects and arrays to the `|retconAttr` filter, similarly to the native `|attr` filter

### Fixed

- Fixed a couple of instances where Retcon could return unescaped HTML

### Changed

- Retcon now requires Craft 3.7+

## 2.3.0 - 2021-10-22

### Changed

- Update version dependencies for `symfony/dom-crawler` and `symfony/css-selector`.

### Fixed

- Fixed docs (closes #28)

## 2.2.3 - 2020-05-05

### Fixed

- Fixes an issue where Retcon wouldn't transform images with a `jpeg` file extension. Thanks @sweetroll 👍

## 2.2.2 - 2020-04-27

### Fixed

- Fixes an issue where an exception could be thrown if Retcon was unable to read the extension for a transformed image.
  Thanks @lenvanessen! Fixes #31

## 2.2.1 - 2020-07-19

### Fixed

- Fixes an issue where the `retconOnly` filter could throw an exception

### Improved

- The `retconOnly` filter now returns an empty string if there are no nodes matching the selector

### Changed

- Retcon now uses caret version range for the DomCrawler and CssSelector components

## 2.2.0 - 2020-07-19

### Fixed

- Fixes an issue where using the `retconAttr` filter on `<script>` tags could result in invalid HTML. Fixes #25
- Retcon no longer decodes HTML entities, fixing an issue where particular characters could result in mangled content
  output. Fixes #24

## 2.1.0 - 2020-03-08

### Added

- Adds support for Imager X

### Fixed

- Fixes an issue where the `retconAutoAlt` filter would always use the image filename for the `alt` attribute
- Fixes an issue where Retcon would throw an exception if the `retconLazy` filter was used on an `<img>` tag with an
  invalid `src` attribute

## 2.0.12 - 2018-11-09

### Improved

- The `retconRemoveEmpty` will no longer remove `<br/>` tags, unless the second parameter `$removeBr` is set to `true`

## 2.0.11 - 2018-10-08

### Fixed

- Fixes an issue where the `retconOnly` filter could throw an exception if the HTML contained tags that weren't
  well-formed (e.g. `img` tags that aren't explicitly closed). Thanks a lot @jcdarwin!

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

- The `overwrite` parameter for the `attr()` method can now be set to a string `'prepend'`, to _prepend_ rather than
  append values to existing attributes

## 2.0.7 - 2018-08-07

### Fixed

- Fixes an issue where Retcon could throw an exception if given a NULL value instead of a string (e.g. if a Redactor
  field had been added to a Field Layout, without re-saving the entries)

## 2.0.6 - 2018-08-05

### Fixed

- Fixes an issue where Retcon would attempt to use Imager for transforms, even if Imager was not installed or
  deactivated

## 2.0.5 - 2018-07-28

### Fixed

- Fixes a bug where the `transform` method would return an empty string if Retcon would fail to transform an image
- Fixes various issues related to the project config file (i.e. `/config/retcon.php`) and plugin settings

### Improved

- Improves image path handling for the `srcset` and `transform` methods

## 2.0.4 - 2018-07-25

### Fixed

- Fixes a recently introduced regression error in the "retcon" catch-all method, affecting the "srcset" method and
  others.

## 2.0.3 - 2018-07-21

### Fixed

- Retcon no longers attempt to decode quote entities, to fix an issue where serialized data in HTML attributes were
  getting butchered

## 2.0.2 - 2018-07-18

### Fixed

- Various minor issues fixed

## 2.0.1 - 2018-07-18

### Fixed

- Fixes various issues with the `retcon` catch-all filter
- Fixes an issue where Retcon would entity encode HTML tags when no nodes matched the given selector for methods such
  as `srcset`, `transform` and others
- Fixes an issue where Retcon would entity encode non-breaking spaces

## 2.0.0 - 2018-07-18

### Added

- New filter `retconRemoveEmpty` added, which will remove empty DOM nodes (e.g. `<p>` tags without text content)

### Improved

- Selectors are much, _much_ more flexible (almost all CSS selectors work, which means that Retcon is basically jQuery
  now!) due to the magic of Symfony's DomCrawler component
- **Full HTML5 support**
- The `retconAutoAlt` filter will use the Asset's `title` for the `alt` attribute, if Retcon is fed markup with Craft
  CMS reference tags
- The `retconTransform`, `retconLazy` and `retconSrcSet` filters now take an additional parameter – `selector` (defaults
  to `'img'`)

## 1.0.1 - 2018-06-07

### Improved

- Adds support for the `limit` parameter in the "replace" filter (`preg_replace` wrapper)

## 1.0.0-beta1 - 2018-03-05

### Added

- Beta release
