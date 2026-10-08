<?php

namespace App\Services;

use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Prepares newsletter HTML for sending: inlines the small set of editor
 * utility classes used on <img> elements (position/sizing presets) so the
 * message does not depend on a stylesheet that is not shipped with it.
 * CSS already present in <style> blocks is inlined by the converter as well;
 * authored inline styles keep precedence over these utility defaults.
 */
class NewsletterHtmlFormatter
{
    private const IMAGE_UTILITY_CSS = <<<'CSS'
img.float-left{float:left}
img.float-right{float:right}
img.float-none{float:none}
img.mr-4{margin-right:1rem}
img.ml-4{margin-left:1rem}
img.mb-2{margin-bottom:0.5rem}
img.mx-auto{margin-left:auto;margin-right:auto}
img.block{display:block}
img.w-full{width:100%}
CSS;

    public function format(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        return (new CssToInlineStyles())->convert($html, self::IMAGE_UTILITY_CSS);
    }
}
