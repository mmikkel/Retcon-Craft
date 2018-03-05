# Retcon plugin for Craft CMS 3.x

A collection of powerful Twig filters for modifying HTML. Here are some of the things Retcon can do:  

* Add attributes (e.g. `class="foobar"`, `style="color:red;"` or `data-foo`) to any selector (e.g. `p`, `img`, `span.foobar` etc)
* Remove attributes for any given selector (e.g. remove `style` attribute for all `p` tags)
* Append to attributes
* Remove DOM nodes by selector (e.g. remove all `span` tags)
* Remove _everything but_ given selectors (e.g. remove everything except `img` tags)
* Transform inline images (it even uses [Imager](https://github.com/aelvan/Imager-Craft), if installed)
* Add srcset or lazyloading to inline images
* Inject strings or HTML content
* Change tag names for given selectors (e.g. change all occurrences of `<p>` to `<span>`)  

...and much more!

## Requirements

This plugin requires Craft CMS 3.0.0-RC1 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require mmikkel/retcon

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Retcon.

## Got WYSIWYG?

Retcon is a small [Craft CMS](http://buildwithcraft.com) plugin offering a series of easy-to-use Twig filters for manipulating HTML content, e.g. adding attributes, injecting strings or HTML content, transforming inline images etc.  

It doesn't matter if your HTML is from a WYSIWYG field (Redactor or CK Editor) or just a regular ol' string. If it's HTML, Retcon will eat it!  

## Basic usage

_Please see the [Wiki page](https://github.com/mmikkel/Retcon-Craft/wiki) for documentation, featureset overview and code examples._

Retcon uses [DOMDocument](http://php.net/manual/en/class.domdocument.php) to rewrite HTML. It includes a series of different methods, exposed as Twig filters:

```twig
{{ entry.body | retconTransform('someImageTransform') }}
```

All methods, however, can also be called as template methods:

```twig
{{ craft.retcon.transform(entry.body, 'someImageTransform') }}
```

If you prefer, Retcon also includes a "catch-all" filter, taking the filter name as its first argument:

```twig
{{ entry.body | retcon('transform', 'someImageTransform') }}
```

And finally, you'll also be able to apply several operations in one go (for a _theoretical_ performance gain). Each index in the operation array will be either a String value (filter name) if there are no arguments, or an array of arguments (where the filter name should be the first index).

```twig
{{ entry.body | retcon([
    ['transform', 'someImageTransform'],
    'lazy',
    ['attr', '.foo', {'class' : 'bar'}]
]) }}
```

### Methods

**[transform](https://github.com/mmikkel/Retcon-Craft/wiki/Transform)**
Apply a named or inline image transform to all images. **If installed, Retcon uses [Imager](https://github.com/aelvan/Imager-Craft) to apply the transform.**  

**[srcset](https://github.com/mmikkel/Retcon-Craft/wiki/srcset)**
Apply an array of named or inline image transform to all images, for simple srcset support. **If installed, Retcon uses [Imager](https://github.com/aelvan/Imager-Craft) to apply the transforms.**  

**[lazy](https://github.com/mmikkel/Retcon-Craft/wiki/Lazy)**
Replaces the _src_ attribute of image tags with a transparent, base64 encoded SVG (retaining the original image's aspect ratio); putting the original src URL in a data-attribute

**[autoAlt](https://github.com/mmikkel/Retcon-Craft/wiki/AutoAlt)**
Adds filename as alternative text for images missing alt tags

**[attr](https://github.com/mmikkel/Retcon-Craft/wiki/Attr)**
Adds, replaces or removes a set of attributes and attribute values – e.g. `class`. Can be used to remove inline styles.  

**[renameAttr](https://github.com/mmikkel/Retcon-Craft/wiki/renameAttr)**
Renames existing attributes for matching selectors, retaining the attribute values.  

**[wrap](https://github.com/mmikkel/Retcon-Craft/wiki/Wrap)**
Wraps stuff in other stuff  

**[unwrap](https://github.com/mmikkel/Retcon-Craft/wiki/Unwrap)**
Removes parent node for matching elements  

**[remove](https://github.com/mmikkel/Retcon-Craft/wiki/Remove)**
Removes all elements matching the given selector(s)  

**[only](https://github.com/mmikkel/Retcon-Craft/wiki/Only)**
Removes everything but the elements matching the given selector(s)  

**[change](https://github.com/mmikkel/Retcon-Craft/wiki/Change)**
Changes tag type for all elements matching the given selector(s). Can also remove the tag(s) completely, retaining inner content  

**[inject](https://github.com/mmikkel/Retcon-Craft/wiki/Inject)**
Inject strings or HTML

**[replace](https://github.com/mmikkel/Retcon-Craft/wiki/Replace)**
Replace stuff with ```preg_replace``

### Disclaimer & support
Retcon is provided free of charge. The author is not responsible for data loss or any other problems resulting from the use of this plugin.
Please see [the Wiki page](https://github.com/mmikkel/Retcon-Craft/wiki) for documentation and examples. and report any bugs, feature requests or other issues [here](https://github.com/mmikkel/Retcon-Craft).
As Retcon is a hobby project, no promises are made regarding response time, feature implementations or bug amendments.
