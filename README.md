# Retcon plugin for Craft CMS

Retcon is a tiny Craft CMS plugin adding a series of powerful Twig filters for modifying HTML content. **Here are some of the things Retcon can do:**

* Add attributes (e.g. `class="foobar"`, `style="color:red;"` or `data-foo`) using CSS selectors (e.g. `'img'`, `'div.foobar'`, `'p:first-child'` etc)  
* Append values to existing attributes
* Remove attributes completely (e.g. removing all inline `style` attributes)
* Transform inline images (it even uses [Imager](https://github.com/aelvan/Imager-Craft), if installed)
* Add srcset or lazyloading to inline images (again, using Imager if installed)
* Remove or unwrap DOM nodes
* Wrap DOM nodes (e.g. wrap all `<span>` tags in a `<p>`)
* Extract DOM nodes (e.g. remove everything except `<img>` tags)
* Inject strings or HTML content
* Change tag names (e.g. change all occurrences of `<p>` to `<span>`, or change `div.foobar` to `p.foobar`)

...and much more!

## Requirements

This plugin requires Craft CMS 3.7.0 or 4.0.0-beta.4.  

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

`cd /path/to/project`

2. Add Retcon as a dependency to your project using Composer:

`composer require mmikkel/retcon`

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Retcon, or use the `craft` executable:

`./craft plugin/install retcon`

Alternatively, Retcon can be installed from the Craft CMS Plugin Store inside the Craft Control Panel.

## How does it work?

Retcon uses PHP's native [DOMDocument](http://php.net/manual/en/class.domdocument.php) class to parse and modify HTML. Additionally, [Masterminds' HTML5 library](https://github.com/Masterminds/html5-php) is used for HTML5 support, and Symfony's [DomCrawler](https://symfony.com/doc/3.3/components/dom_crawler.html) and [CssSelector](https://symfony.com/doc/3.3/components/css_selector.html) components are used to enable the powerful jQuery-like selector syntax.

## Changes in Retcon 2.x

Retcon 2.x is almost completely backwards compatible with Retcon 1.x. These are the big changes:

* Symfony's [DomCrawler](https://symfony.com/doc/3.3/components/dom_crawler.html) and [CssSelector](https://symfony.com/doc/3.3/components/css_selector.html) components have been added for _much_ more powerful selector capabilities (almost all CSS selectors work). Retcon is basically jQuery now! **Note: all existing selectors in your code will still work, if you're upgrading from Retcon 1.x.**

* Retcon now has full HTML5 support, courtesy of [Masterminds](https://github.com/Masterminds/html5-php).

* The `autoAlt` method now uses the Asset's native `alt` (Craft 4 only) or `title` attributes for the `alt` attribute, if Retcon is fed HTML with Craft CMS [reference tags](https://docs.craftcms.com/v2/reference-tags.html) (typically, content from a Redactor or CKEditor field).  

* The `transform`, `lazy` and `srcset` now takes an optional `$selector` parameter (`'img'` by default)

* The `lazy` method has new default values for its `$className` and `$attributeName` parameters. The new defaults are `'lazyload'` and `'src'` (i.e. `'data-src'`). This change is done to mirror the defaults in the popular [lazysizes](https://github.com/aFarkas/lazysizes) library.

* The new `removeEmpty` method removes empty nodes (i.e., nodes that have no text content). Those pesky clients hitting Enter again and again won't know what hit them.

* The `replace` method has been removed. So sorry, it just didn't belong...  

* A new `dimensions` filter has been added, which can add missing `width` and `height` attributes to image nodes (Retcon 2.7+).  

## Basic usage

Retcon exposes a series of different [methods](#methods) for modifying HTML. Most methods take a `selector` parameter (i.e. the selector(s) for the elements you want to modify, e.g. `'img'`, `'p'` or `'div.foobar'`), and some take additional parameters for further configuration.

Note that it doesn't matter if your HTML is from a WYSIWYG field (Redactor or CK Editor) or just a regular ol' string. If it's HTML, Retcon will eat it.

### Twig filters

All of Retcon's methods are exposed as Twig filters, which is how Retcon is primarily designed to be used. For example, if you wanted to add a classname `'image'` to all images in a Redactor field called `body`, here's how that'd look:

```twig
{{ entry.body | retconAttr('img', { class: 'image' }) }}
```

Note that for the Twig filters, the prefix `retcon` is added to the method name – i.e. the `attr` method becomes the `retconAttr` filter, the `transform` method becomes the `retconTransform` filter, etc.

#### Filter tag pair

For use cases where your HTML is not in a field or variable, the _apply tag pair_ syntax works nicely – the following example adds `rel="noopener noreferrer"` to all `<a>` tags with `target="_blank"`:

```twig
{% apply retconAttr('a[target="_blank"]:not([rel])', { rel: 'noopener noreferrer' }) %}
    {# A whole bunch of HTML in here #}
    ....
{% endapply %}
```

#### Catch-all filter

Being Twig filters, _chaining_ multiple Retcon methods will of course work:

```twig
{{ entry.body | retconChange('h1,h2,h4,h5,h6', 'h3') | retconAttr('h3', { class: 'heading') }}
```

Another option is to use the "catch-all" filter `retcon`, which takes a single array containing the names of the methods you want to run in sequence, and their parameters:

```twig
{{ entry.body | retcon([
    ['change', 'h1,h2,h4,h5,h6', 'h3'],
    ['attr', 'h3', { class: 'heading' }]
]) }}
```

### PHP

If you want to use Retcon in a Craft plugin or module, all methods are also available through the `mmikkel/retcon/Retcon::getInstance()->retcon` service (note that unlike the Twig filters, the `retcon` prefix is missing from the service method names – in other words, `retconAttr` is just `attr()`):

```php
use mmikkel\retcon\Retcon;
echo Retcon::getInstance()->retcon->attr($entry->body, ['class' => 'image']);
```

For an actual use case example; here's how the `rel="noopener noreferrer"` example could look in a module (basically, the below code would add `rel="noopener noreferrer"` automatically to _all_ `<a target="_blank">` tags in your templates (unless they've already got a `rel` attribute set, of course):

```php
use mmikkel\retcon;

public function init() {

    Event::on(
        View::class,
        View::EVENT_AFTER_RENDER_TEMPLATE,
        function (TemplateEvent $event) {
            if (!Craft::$app->getRequest()->getIsSiteRequest()) {
                return;
            }

            if ($event->output && Craft::$app->getPlugins()->getPlugin('retcon')) {
                $event->output =
                    Retcon::getInstance()->retcon->attr(
                        $event->output,
                        'a[target="_blank"]:not([rel])', [
                            'rel' => 'noopener noreferrer',
                        ]);
            }

        }
    );
}
```

### Selectors

A "selector" in Retcon is the same thing as a selector in CSS – i.e. something like `'img'`, `'.foo'` or `h3 + p`.

In Retcon 2.x, _almost all CSS selectors_ will work – see the [CssSelector](https://symfony.com/doc/3.3/components/css_selector.html) docs for further details on selectors.

#### Multiple selectors

Multiple selectors can be defined as a comma-separated string (i.e. `'p, span'`) or as an array (i.e. `['p', 'span']`).

### Methods

**[transform](https://github.com/mmikkel/Retcon-Craft/wiki/Transform)**
Apply a named or inline image transform to all images. **If installed, Retcon uses [Imager](https://github.com/aelvan/Imager-Craft) to apply the transform.**
**New:** Retcon also supports Imager's successor, [Imager X](https://github.com/spacecatninja/craft-imager-x).

**[srcset](https://github.com/mmikkel/Retcon-Craft/wiki/srcset)**
Apply an array of named or inline image transform to all images, for simple srcset support. **If installed, Retcon uses [Imager](https://github.com/aelvan/Imager-Craft) to apply the transforms.**  

**[lazy](https://github.com/mmikkel/Retcon-Craft/wiki/Lazy)**
Replaces the _src_ attribute of image tags with a transparent, base64 encoded SVG (retaining the original image's aspect ratio); putting the original src URL in a data-attribute

**[dimensions](https://github.com/mmikkel/Retcon-Craft/wiki/Dimensions)**
Adds `width` and `height` attributes to image nodes, if they are missing (and the image referenced in the image nodes' `src` attribute is a local image file). **NEW**    

**[autoAlt](https://github.com/mmikkel/Retcon-Craft/wiki/AutoAlt)**
Adds Asset title or filename as alternative text for images missing `alt` tags

**[attr](https://github.com/mmikkel/Retcon-Craft/wiki/Attr)**
Adds, replaces, appends to or removes a set of attributes and attribute values – e.g. `class`. **Can be used to remove inline styles.**

**[renameAttr](https://github.com/mmikkel/Retcon-Craft/wiki/renameAttr)**
Renames existing attributes for matching selectors, retaining the attribute values

**[wrap](https://github.com/mmikkel/Retcon-Craft/wiki/Wrap)**
Wraps stuff in other stuff (e.g. put all `<span>` tags in `<p>` tags)

**[unwrap](https://github.com/mmikkel/Retcon-Craft/wiki/Unwrap)**
Removes parent node for matching elements; retaining their content

**[remove](https://github.com/mmikkel/Retcon-Craft/wiki/Remove)**
Removes all elements matching the given selector(s)

**[removeEmpty](https://github.com/mmikkel/Retcon-Craft/wiki/RemoveEmpty)**
Removes all empty elements. **NEW**

**[only](https://github.com/mmikkel/Retcon-Craft/wiki/Only)**
Removes everything except the elements matching the given selector(s)  

**[change](https://github.com/mmikkel/Retcon-Craft/wiki/Change)**
Changes tag type for all elements matching the given selector(s). Can also be used to remove tags completely, but retaining their content.  

**[inject](https://github.com/mmikkel/Retcon-Craft/wiki/Inject)**
Inject strings or HTML

### Disclaimer & support
Retcon is provided free of charge. The author is not responsible for data loss or any other problems resulting from the use of this plugin.
Please see [the Wiki page](https://github.com/mmikkel/Retcon-Craft/wiki) for documentation and examples. and report any bugs, feature requests or other issues [here](https://github.com/mmikkel/Retcon-Craft).
As Retcon is a hobby project, no promises are made regarding response time, feature implementations or bug amendments.
