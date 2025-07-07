<?php 
/**
 * Luminova Framework XML and HTML Generator Class
 * 
 * This class provides static methods to dynamically generate HTML elements.
 * Methods can be called statically to create various HTML tags without 
 * explicitly defining each method in the class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Builder;

use \Luminova\Builder\Document;
use function \Luminova\Funcs\escape;

/**
 * Example usage:
 * - Xhtml::div('Content', ['class' => 'my-class']);
 * - Xhtml::p('Paragraph content', ['id' => 'my-paragraph']);
 *
 * Supported HTML elements can be referenced by the method name, 
 * which is converted to lowercase for use as an HTML tag name.
 *
 * @method static string div(string $content = '', bool $closeElement = true, array $attributes = [])
 * @method static string p(string $content = '', bool $closeElement = true, array $attributes = [])
 * @method static string span(string $content = '', bool $closeElement = true, array $attributes = [])
 * @method static string br(string $content = null, bool $closeElement = false, array $attributes = [])
 * @method static string h{1-6}(string $content = '', bool $closeElement = true, array $attributes = [])
 */
class Xhtml extends Document
{
    /**
     * Dynamically generates an HTML element using static method calls.
     *
     * @param string $method The name of the method being called, converted to lowercase to use as HTML tag name.
     * @param array $arguments The arguments passed to the method.
     *                         The arguments are unpacked as follows:
     *                         - string $content The string content to be passed to the element (default: '').
     *                         - bool $closeElement Whether the element should be self-closing (default: false).
     *                         - array<string,string> $attributes Additional HTML attributes for the element.
     *
     * @return string Returns the generated HTML element as a string.
     */
    public static function __callStatic(string $method, array $arguments): string
    {
        [$content, $closeElement, $attributes] = $arguments + [null, false, []];

        return self::element($method, $content, $closeElement, $attributes);
    }

    /**
     * Generates an HTML element with optional attributes and content.
     *
     * @param string $tag The HTML tag to be generated (e.g., 'div', 'span', 'p').
     * @param string|null $content Optional content to be placed inside the element (default: ''). 
     * @param bool $closeElement Whether to close the tag with content or self-close it (default: true).
     * @param array $attributes Optional associative array of attributes to be added 
     *        to the HTML tag (e.g., ['class' => 'my-class', 'id' => 'my-id']).
     *
     * @return string Returns the generated HTML element as a string.
     */
    public static function element(
        string $tag,
        ?string $content = null, 
        bool $closeElement = true,
        array $attributes = []
    ): string {
        $tag = self::$xhtmlStrictTagNames 
            ? strtolower(parent::esc($tag)) 
            : parent::esc($tag);
        return sprintf(
            '<%s%s%s',
            $tag,
            parent::attributes($attributes),
            $closeElement ? '>' . ($content ?? '') . '</' . $tag . '>' : ' />'
        );
    }

    /**
     * Generates multiple HTML elements based on an array of element specifications.
     *
     * @param array $elements Array of elements where each element is defined with keys ('tag', 'content', 'closeElement', and 'attributes').
     *
     * @return string Returns the generated HTML elements as a string.
     * 
     * Each element in the array should be an associative array containing the following keys:
     * 
     * - **tag** (string): The HTML tag to generate (e.g., 'div', 'p', 'span').
     * - **content** (string): The content to be placed inside the HTML tag (default: empty string).
     * - **closeElement** (bool): Whether to close the tag as a block element (default: true if content is provided).
     * - **attributes** (array): Optional associative array of HTML attributes for the tag (default: empty array).
     */
    public static function elements(array $elements): string
    {
        $html = '';

        foreach ($elements as $element) {
            $tag = ($element['tag'] ?? null);

            if($tag === null){
                continue;
            }

            $content = ($element['content'] ?? null);
            $html .= self::element(
                $tag, 
                $content, 
                $element['closeElement'] ?? ($content !== null), 
                $element['attributes'] ?? []
            ) . PHP_EOL;
        }

        return $html;
    }

    /**
     * Generates multiple non-self-closing HTML elements of a specific tag, 
     * based on an array of element attributes.
     *
     * @param string $tag The HTML tag to generate (e.g., 'div', 'p', 'span').
     * @param array<int,array<string,mixed>> $attributes Array of element attributes, 
     *        where each entry is an associative array of attributes for a single element.
     *
     * @return string Returns the generated HTML elements as a string.
     */
    public static function tags(string $tag, array $attributes): string 
    {
        if($tag === '' || $attributes === []){
            return '';
        }

        $html = '';

        foreach ($attributes as $attribute) {
            if (!is_array($attribute)) {
                continue;
            }

            $html .= self::element($tag, null, false, $attribute) . PHP_EOL;
        }

        return $html;
    }

    /**
     * Generate an inline comment block or conditional comment with the given content.
     * 
     * @param string $content The content to include within the comment.
     * @param bool $hide Enable to hide scripts from browsers without support for scripts, so it doesn't show as plain text.
     * @param string|null $conditions Optional conditions for generating a conditional comment (default: null).
     *
     * @return string Return he generated HTML comment string.
     */
    public static function comment(string $content, bool $hide = false, ?string $conditions = null): string 
    {
        if ($conditions) {
            return sprintf(
                '<!--[if %s]>' . PHP_EOL .
                '    %s' . PHP_EOL .
                '<![endif]-->',
                str_replace(
                    ['&&', '||'], 
                    [' AND ', ' OR '],
                    parent::esc($conditions)
                ),
                $content
            );
        }
        
        return '<!--' . $content . ($hide ? '//' : '') . '-->';
    }

    /**
     * Generate an inline style element with the given CSS content.
     * 
     * @param string $content The CSS content to be included within the style tag.
     * @param array<string,string> $attributes Additional HTML attributes for the style element (e.g., class, id).
     *
     * @return string Return the generated HTML style element as a string.
     */
    public static function css(string $content, array $attributes = []): string 
    {
        return self::element('style', escape($content, 'css'), true, $attributes);
    }

    /**
     * Generate an inline script element with the given JavaScript content.
     * 
     * @param string $content The JavaScript content to be included within the script tag.
     * @param array<string,string> $attributes Additional HTML attributes for the script element (e.g., async, defer).
     *
     * @return string Return the generated HTML script element as a string.
     */
    public static function js(string $content, array $attributes = []): string 
    {
        return self::element('script', escape($content, 'js'), true, $attributes);
    }

    /**
     * Generate a hidden div element with the given content.
     *
     * This method sets the display style to 'none' to hide the element from view.
     *
     * @param string $content The content to be included within the div.
     * @param array<string,string> $attributes Additional HTML attributes for the div (e.g., class, id).
     *
     * @return string Return the generated HTML div element as a string.
     */
    public static function hidden(string $content, array $attributes = []): string 
    {
        $attributes['style'] = 'display:none;' . $attributes['style'] ?? '';
        return self::element('div', $content, true, $attributes);
    }

    /**
     * Generate an invisible div element with the given content.
     *
     * @param string $content The content to be included within the div.
     * @param bool $focusable Whether the element should be focusable or not.
     * @param array<string,string> $attributes Additional HTML attributes for the div (e.g., class, id).
     *
     * @return string Return the generated HTML div element as a string.
     */
    public static function invisible(string $content, bool $focusable = true, array $attributes = []): string 
    {
        $attributes['style'] = ($focusable 
            ? 'position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;'
            : 'visibility: hidden;'
        ) . $attributes['style'] ?? '';

        return self::element('div', $content, true, $attributes);
    }

    /**
     * Generate an HTML list (unordered or ordered) from a set of items.
     *
     * @param array<int,string|array<string,string>>|string $items The items to be included in the list; can be a single string or an array of items.
     *                            Each item can be a string or an associative array with 'content' and 'attributes'.
     * @param string $type The type of list to generate ('ul' for unordered, 'ol' for ordered). Default is 'ul'.
     * @param array<string,string> $attributes Additional HTML attributes for the list element (e.g., class, id).
     *
     * @return string Return the generated HTML list as a string.
     */
    public static function list(array|string $items, string $type = 'ul', array $attributes = []): string 
    {
        if (is_array($items)) {
            $html = '';

            foreach ($items as $item) {
                $content = is_array($item) ? $item['content'] ?? '' : $item;
                $attr = is_array($item) ? ($item['attributes'] ?? []) : [];

                $html .= self::element('li', $content, true, $attr) . PHP_EOL;
            }

            return self::element($type, PHP_EOL . $html, true, $attributes);
        }

        return self::element($type, $items, true, $attributes);
    }

    /**
     * Generates an ordered list (ol) with items.
     *
     * @param string $list List items.
     * @param array $attributes Optional HTML attributes for the list.
     *
     * @return string Return the generated ordered list.
     * @see https://www.w3schools.com/TAGS/tag_ol.asp
     */
    public static function ol(string $list, array $attributes = []): string 
    {
        return self::element('ol', $list, true, $attributes);
    }

    /**
     * Generates an unordered list (ul) with items.
     *
     * @param string $list List items.
     * @param array $attributes Optional HTML attributes for the list.
     *
     * @return string Return the generated unordered list.
     * @see https://www.w3schools.com/TAGS/tag_ul.asp
     */
    public static function ul(string $list, array $attributes = []): string 
    {
        return self::element('ul', $list, true, $attributes);
    }

    /**
     * Generates a link element (a).
     *
     * @param string $url The URL for the link.
     * @param string|null $text The visible text for the link or null to display url as text.
     * @param array $attributes Optional HTML attributes for the link.
     *
     * @return string Return the generated link.
     */
    public static function href(string $url, ?string $text = null, array $attributes = []): string 
    {
        $attributes['href'] = $url;
        return self::element('a', $text ?? $url, true, $attributes);
    }

    /**
     * Generates an image element (img).
     *
     * @param string $src The source URL for the image.
     * @param array $attributes Optional HTML attributes for the image.
     *
     * @return string Return the generated image element.
     */
    public static function image(string $src, array $attributes = []): string 
    {
        $attributes['src'] = $src;
        $attributes['alt'] = $attributes['alt'] ?? 'Image';
        return self::element('img', null, false, $attributes);
    }

    /**
     * Generates a picture element with a fallback img.
     *
     * @param string $src The source URL for the image.
     * @param array|string $source The source(s) for the picture.
     * @param array $attributes Optional attributes for the `img` and `picture` tags.
     *                          - 'image'  => (array) Attributes for the fallback `<img>` tag (e.g., 'alt', 'style').
     *                          - 'picture' => (array) Attributes for the `<picture>` tag.
     *
     * @return string Returns the generated `<picture>` element with an image and source.
     */
    public static function picture(
        string $src, 
        array|string $source, 
        array $attributes = ['image' => [], 'picture' => []]
    ): string 
    {
        $attributes['image'] += [
            'style' => 'width:auto;',
            'alt'   => 'Picture Image'
        ];

        $image = self::image($src, $attributes['image']);
        $source = is_array($source) 
            ? self::tags('source', $source)
            : $source;

        return self::element('picture', $source . PHP_EOL . $image, true, $attributes['picture'] ?? []);
    }

    /**
     * Generates a figure element containing an image with an optional caption.
     *
     * @param string $src The URL of the image.
     * @param string $caption The caption text for the image.
     * @param array $attributes Optional attributes for the `img` and `figure` tags.
     *                          - 'image'  => (array) Attributes for the `<img>` tag (e.g., 'alt', 'style').
     *                          - 'figure' => (array) Attributes for the `<figure>` tag.
     *
     * @return string Returns the generated `<figure>` element with an image and caption.
     */
    public static function figure(
        string $src, 
        string $caption, 
        array $attributes = ['image' => [], 'figure' => []]
    ): string
    {
        $attributes['image'] += [
            'style' => 'width:100%;',
            'alt'   => 'Figure Image'
        ];

        $image = self::image($src, $attributes['image']);
        $caption = self::element('figcaption', $caption);

        return self::element('figure', $image . PHP_EOL . $caption, true, $attributes['figure'] ?? []);
    }

    /**
     * Generates an HTML <source> element for media content, such as <video> or <audio> tags.
     *
     * @param string $src  The URL of the media file.
     * @param string $type The MIME type of the media file (e.g., 'video/mp4', 'audio/mpeg').
     * @param array $attributes Optional attributes for the source element.
     *
     * @return string Return the generated HTML <source> element as a string.
     * @see https://www.w3schools.com/TAGS/tag_source.asp
     */
    public static function source(string $src, string $type = 'audio/mpeg', array $attributes = []): string 
    {
        $attributes['src'] = $src;
        $attributes['type'] = $type;
        return self::element('source', null, false, $attributes);
    }

    /**
     * Generates an HTML <track> element for media content, such as <video> or <audio> tags.
     *
     * @param string $src  The URL of the media file.
     * @param string $kind Specifies the kind of text track (e.g., 'captions', 'chapters', `descriptions`, `metadata` or `subtitles`).
     * @param array $attributes Optional attributes for the source element.
     *
     * @return string Return the generated HTML <track> element as a string.
     * @see https://www.w3schools.com/TAGS/tag_track.asp
     */
    public static function track(string $src, string $kind = 'subtitles', array $attributes = []): string 
    {
        $attributes['src'] = $src;
        $attributes['kind'] = $kind;
        return self::element('track', null, false, $attributes);
    }

    /**
     * Generates an HTML <param> element for media content, such as <object> tags.
     *
     * @param string $name  The name of a parameter (e.g, `autoplay`).
     * @param string $kind The value of the parameter (e.g, `true`).
     *
     * @return string Return the generated HTML <param> element as a string.
     * @see https://www.w3schools.com/TAGS/tag_param.asp
     */
    public static function param(string $name, string $value): string 
    {
        $attributes = [];
        $attributes['name'] = $name;
        $attributes['value'] = $value;
        return self::element('param', null, false, $attributes);
    }

    /**
     * Generates a video element.
     *
     * @param array|string $source The source(s) for the video.
     * @param array|string $tracks The track(s) for the video.
     * @param array $attributes Optional attributes for the video element.
     * @param string $placeholder Fallback text if the video is unsupported.
     *
     * @return string Return the generated video element.
     * @see https://www.w3schools.com/TAGS/tag_video.asp
     */
    public static function video(
        array|string $source, 
        array|string $tracks = [], 
        array $attributes = [], 
        string $placeholder = 'Your browser does not support the video tag.'
    ): string 
    {
        $source = is_array($source) 
            ? self::tags('source', $source)
            : $source;

        $tracks = is_array($tracks) 
            ? self::tags('track', $tracks)
            : $tracks;

        return self::element('video', $source . PHP_EOL . $tracks . PHP_EOL . $placeholder, true, $attributes);
    }

    /**
     * Generates an audio element.
     *
     * @param array|string $source The source(s) for the audio.
     * @param array|string $tracks The track(s) for the audio.
     * @param array $attributes Optional attributes for the audio element.
     * @param string $placeholder Fallback text if the audio is unsupported.
     *
     * @return string Return the generated audio element.
     * @see https://www.w3schools.com/TAGS/tag_audio.asp
     */
    public static function audio(
        array|string $source, 
        array|string $tracks = [], 
        array $attributes = [], 
        string $placeholder = 'Your browser does not support the audio tag.'
    ): string 
    {
        $source = is_array($source) 
            ? self::tags('source', $source)
            : $source;
        
        $tracks = is_array($tracks) 
            ? self::tags('track', $tracks)
            : $tracks;

        return self::element('audio', $source . PHP_EOL . $tracks . PHP_EOL . $placeholder, true, $attributes);
    }

    /**
     * Generates an iframe element.
     *
     * @param string $src The source URL for the iframe content.
     * @param string $placeholder The fallback text or content inside the iframe element.
     * @param array $attributes Optional HTML attributes for the iframe.
     *
     * @return string Return the generated iframe element.
     */
    public static function iframe(
        string $src, 
        string $placeholder = 'Your browser does not support the iframe tag.', 
        array $attributes = []
    ): string 
    {
        $attributes['src'] = $src;
        return self::element('iframe', $placeholder, true, $attributes);
    }

    /**
     * Generates an HTML image map with the specified areas and attributes.
     * Creates an <img> element with a usemap attribute, along with the associated <map> and <area> tags.
     *
     * @param string $src The source URL of the image to be used in the map.
     * @param string $name The name of the image map (used in the "usemap" attribute).
     * @param string|array $areas An array or string defining the areas within the map.
     * @param array<string,array> $attributes Optional attributes for the `img` and `map` elements.
     *                                 - 'image' => array for <img> element attributes
     *                                 - 'map' => array for <map> element attributes
     *
     * @return string Return the complete HTML string for the image and the associated map element.
     */
    public static function map(
        string $src, 
        string $name,
        string|array $areas,
        array $attributes = ['image' => [], 'map' => []]
    ): string 
    {
        $attributes['image'] += [
            'usemap' => '#' . $name,
            'alt'    => $name,
            'id'     => uniqid('img-map-'),
        ];

        $image = self::image($src, $attributes['image']);
        $attributes['map']['name'] = $name;
        $areas = is_array($areas) ? self::tags('area', $areas) : $areas;

        return $image . PHP_EOL . self::element('map', $areas, true, $attributes['map'] ?? []);
    }

    /**
     * Generates a full basic HTML document structure.
     *
     * @param string $content The HTML content of the page.
     * @param string $title The title of the page.
     * @param string $doctype The HTML document type declaration (default: 'html5').
     * @param string|array|null $headers Optional elements for the document's <head> (e.g., <meta>, <link>, <script>).
     * @param array $attributes Optional attributes for the <html> and <body> tags. 
     *                          - 'html' => attributes for the <html> tag.
     *                          - 'body' => attributes for the <body> tag.
     *
     * @return string Return the generated HTML document.
     *
     * For `$headers` argument, each element in the array should be an associative array containing the following keys:
     * 
     * - **tag** (string): The HTML tag to generate (e.g., 'meta', 'link', 'script').
     * - **content** (string): Optional content inside the tag (e.g., for <script>).
     * - **closeElement** (bool): Whether to close the tag as a block element (default: true if content is provided).
     * - **attributes** (array): Optional associative array of HTML attributes for the tag.
     */
    public static function document(
        string $content, 
        string $title, 
        string $doctype = 'html5', 
        string|array|null $headers = null, 
        array $attributes = ['html' => [], 'body' => []]
    ): string 
    {
        $headers = ($headers !== null && is_array($headers)) 
            ? self::elements($headers) 
            : ($headers ?? '');

        $head = sprintf(
            '%s%s<html%s>%s<head>%s<title>%s</title>%s%s</head>',
            parent::doctype($doctype) ?? '<!DOCTYPE html>',
            PHP_EOL,
            parent::attributes($attributes['html'] ?? []),
            PHP_EOL,
            PHP_EOL,
            parent::esc($title),
            PHP_EOL,
            $headers
        );

        $body = sprintf(
            '<body%s>%s</body>',
            parent::attributes($attributes['body'] ?? []),
            $content
        );
        
        return $head . PHP_EOL . $body . PHP_EOL . '</html>';
    }

    /**
     * Generates a full basic XML document structure with a flexible doctype and additional options.
     *
     * @param string $content The XML content of the document.
     * @param string $version The XML version (default: '1.0').
     * @param string $encoding The character encoding of the document (default: 'UTF-8').
     * @param bool $standalone Whether the XML document is standalone (default: true).
     * @param string $doctype The XML document type declaration (default: 'xhtml11').
     * @param array $attributes Optional attributes for the root element.
     *
     * @return string Return the generated XML document.
     */
    public static function xml(
        string $content, 
        string $version = '1.0', 
        string $encoding = 'UTF-8', 
        bool $standalone = true, 
        string $doctype = 'xhtml11', 
        array $attributes = []
    ): string {
        return sprintf('<?xml version="%s" encoding="%s" standalone="%s"?>%s%s%s<root%s>%s</root>', 
            $version, 
            $encoding, 
            $standalone ? 'yes' : 'no',
            PHP_EOL,
            parent::doctype($doctype) ?? '',
            PHP_EOL,
            parent::attributes($attributes), 
            $content
        );
    }

    /**
     * Generates a full basic SVG document structure with a flexible doctype.
     *
     * @param string $content The SVG content of the document.
     * @param string $doctype The SVG document type (default: 'svg10').
     * @param array $attributes Optional attributes for the `svg` tag.
     *
     * @return string Return the generated SVG document.
     */
    public static function svg(
        string $content, 
        string $doctype = 'svg10', 
        array $attributes = []
    ): string {
        return sprintf(
            '%s%s<svg%s>%s%s%s</svg>', 
            parent::doctype($doctype) ?? '',
            PHP_EOL,
            parent::attributes($attributes),
            PHP_EOL,
            $content,
            PHP_EOL
        );
    }

    /**
     * Generates an HTML table with headers, body, and footers.
     *
     * @param string|array $tbody The table body (string or array).
     * @param string|array|null $thead The table headers (string or array).
     * @param string|array|null $tfoot The table footers (string or array).
     * @param string|array|null $colgroup Attributes for table column group (string or array).
     * @param array $attributes Optional HTML attributes for the table.
     * @param string|null $caption Optional caption for the table (default: null).
     *
     * @return string Return the generated HTML table.
     */
    public static function table(
        string|array $tbody, 
        string|array|null $thead = null, 
        string|array|null $tfoot = null,
        string|array|null $colgroup = null, 
        array $attributes = [],
        ?string $caption = null
    ): string 
    {
        $table = ($caption ? self::element('caption', $caption, true) : '');
        $table .= ($colgroup !== null && is_array($colgroup)) ? self::tcol($colgroup) : ($colgroup ?? '');
        $table .= ($thead !== null && is_array($thead)) ? self::thead($thead) : ($thead ?? '');
        $table .= is_array($tbody) ? self::tbody($tbody) : $tbody;
        $table .= ($tfoot !== null && is_array($tfoot)) ? self::tfoot($tfoot) : ($tfoot ?? '');

        return self::element(
            'table', 
            PHP_EOL . $table . PHP_EOL, 
            true, 
            $attributes
        );
    }

    /**
     * Generates a table column group <colgroup> with type.
     *
     * @param array<int,array<string,mixed>> $columns An array of table columns attributes for each row.
     * @param array $attributes Optional attributes for the table column group `<colgroup>`.
     *
     * @return string Return the generated colgroup and columns element.
     */
    public static function tcol(array $columns, array $attribute = []): string 
    {
        return self::element('colgroup', self::tags('col', $columns), true, $attribute);
    }

    /**
     * Generates a table cell with type.
     *
     * @param string $parent Table cell parent element type (e.g, `thead`, `tbody`, `tfoot`).
     * @param string $type Table cell children type (e.g, `td`, `th`).
     * @param array $rows The rows of the table body. Each row can be:
     *                    - An array of strings (e.g., ['foo', 'bar'])
     *                    - An array of associative arrays (e.g., [['content' => 'Foo', 'attributes' => [...]], ...])
     * @param array $attributes Optional attributes for the table row `<TR>`.
     *
     * @return string Return the generated tcell element.
     */
    public static function tcell(string $parent, string $type, array $rows, array $attribute = []): string 
    {
        $html = '';
        
        foreach ($rows as $row) {
            $isArray = is_array($row);

            if($isArray && !isset($row['attributes']) && !isset($row['attributes'])){
                $rowHtml = '';
                foreach ($row as $cell) {
                    $rowHtml .= is_array($cell) 
                        ? self::element($type, $cell['content'] ?? null, true, $cell['attributes'] ?? [])
                        : self::element($type, $cell);
                }
                $html .= self::element('tr', $rowHtml, true, $attribute);
            }else{
                $html .= $isArray
                    ? self::element($type, $row['content'] ?? null, true, $row['attributes'] ?? [])
                    : self::element($type, $row);
            }
        }

        return self::element($parent, $html, true, $attribute);
    }

    /**
     * Generates a table body (tbody) with rows.
     *
     * @param array $rows The rows of the table body. Each row can be:
     *                    - An array of strings (e.g., ['foo', 'bar'])
     *                    - An array of associative arrays (e.g., [['value' => 'Foo', 'attributes' => [...]], ...]).
     * @param string $type The child element tag name of the table tbody (default: `td`).
     * @param array $attributes Optional HTML attributes for the tbody.
     *
     * @return string Return the generated tbody element.
     */
    public static function tbody(array $rows, string $type = 'td', array $attributes = []): string 
    {
        return self::tcell('tbody', $type, $rows, $attributes);
    }

    /**
     * Generates a table head (thead) with rows.
     *
     * @param array $rows The rows of the table head.
     * @param string $type The child element tag name of the table thead (default: `th`).
     * @param array $attributes Optional HTML attributes for the thead.
     *
     * @return string Return the generated thead element.
     */
    public static function thead(array $rows, string $type = 'th', array $attributes = []): string 
    {
        return self::tcell('thead', $type, $rows, $attributes);
    }

    /**
     * Generates a table footer (tfoot) with rows.
     *
     * @param array $rows The rows of the table footer.
     * @param string $type The child element tag name of the table tfoot (default: `td`).
     * @param array $attributes Optional HTML attributes for the tfoot.
     *
     * @return string Return the generated tfoot element.
     */
    public static function tfoot(array $rows, string $type = 'td', array $attributes = []): string 
    {
        return self::tcell('tfoot', $type, $rows, $attributes);
    }
}