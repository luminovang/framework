<?php
/**
 * Luminova Framework SEO schema definition and generator.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Components\Seo;  

use \Luminova\Time\Time;
use \Luminova\Http\Request;
use \Luminova\Interface\LazyObjectInterface;
use function \Luminova\Funcs\{import, kebab_case};

/**
 * Structured Data and Schema Objects Generator for Webpages 
 * 
 * @link https://luminova.ng/docs/3.0.2/configs/structured-data
 * @link https://luminova.ng/docs/3.0.2/website-optimizations/seo-structured-data
 */
final class Schema implements LazyObjectInterface
{
    /**
     * Application URL link.
     * 
     * @var string $url
     */
    private static string $url = '';

    /**
     * Meta object json.
     * 
     * @var array<string,mixed> $default
     */
    private static ?array $default = null;

    /**
     * Default configuration.
     * 
     * @var array $extends
     */
    private static array $extends = [];

    /**
     * Class static singleton instance.
     * 
     * @var ?self $instance
     */
    private static ?Schema $instance = null;

    /**
     * Creates a Schema instance with default configuration.
     *
     * Loads schema defaults from your app config, sets the base URL for the
     * current page, and prepares value filters used to remove empty entries
     * from structured data.
     *
     * @param array<mixed>|null $filterValues Values to treat as empty and remove
     *   from schema data. If null, the (default: `["", 0, null, [], [""]]`).
     * 
     * @see /app/Config/Schema.php Application schema configuration.
     * @see https://luminova.ng/docs/0.0.0/configs/structured-data Schema configuration guide.
     */
    public function __construct(private ?array $filterValues = null)
    {
        $this->filterValues ??= ['', 0, null, [], ['']];
        self::$default ??= import('app:Config/Schema.php') ?? [];
        self::$url = APP_URL;
        
        $this->intExtendsForWebpage();
    }

    /**
     * Returns the shared singleton instance of this class.
     *
     * Creates a new instance on first call and reuses the same instance 
     * for subsequent calls.
     *
     * @return self Return a shared singleton instance.
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Marks the current schema as an article.
     *
     * @param bool $article Whether this schema represents an article (default: true).
     * @return self Returns the current Schema instance.
     */
    public function isArticle(bool $article = true): self
    {
        self::$extends['isArticle'] = $article;
        return $this;
    }

    /**
     * Marks the current schema as a product.
     *
     * @param bool $product Whether this schema represents a product (default: true).
     * @return self Returns the current Schema instance.
     */
    public function isProduct(bool $product = true): self
    {
        self::$extends['isProduct'] = $product;
        return $this;
    }

    /**
     * Sets the URL for the current schema.
     *
     * Use to link the schema object to a specific page or resource.
     *
     * @param string $url Base URL (e.g. `https://example.com`).
     * @param string $uri Optional path to append to the base URL.
     * @return self Returns the current Schema instance.
     */
    public function setUrl(string $url, string $uri = ''): self
    {
        self::$extends['url'] = $uri 
            ? rtrim($url, '/') . '/' . ltrim($uri, '/')
            : $url;

        return $this;
    }

    /**
     * Merges custom schema data into the current page object.
     *
     * Use this when you want to populate or override schema properties
     * for the current page using an associative array. By default, 
     * empty values in the array are ignored, but you can choose to retain them.
     *
     * @param array<string,mixed> $object Key-value pairs representing schema properties 
     *                               (e.g., `['article' => ['headline' => 'My Page']`).
     * @param bool $keepEmpty Whether to retain empty values (default: false).
     * 
     * @return self Return the instance of schema.
     * @see setValue() To set a single property.
     * 
     * @link https://luminova.ng/docs/0.0.0/configs/structured-data
     */
    public function setObject(array $object, bool $keepEmpty = false): self
    {
        self::$extends = array_replace(
            self::$extends, 
            $keepEmpty ? $object : array_filter($object)
        );
        return $this;
    }

    /**
     * Sets or overrides a single schema property for the current page.
     *
     * Use this when you need to update a specific schema value 
     * without replacing the entire configuration array.
     *
     * @param string $key The schema property name (e.g., `'product' => []`).
     * @param mixed  $value The value to assign to this property.
     * 
     * @return self Return the instance of schema.
     * @see setObject() To set multiple properties at once.
     * 
     * @link https://luminova.ng/docs/0.0.0/configs/structured-data
     */
    public function setValue(string $key, mixed $value): self
    {
        self::$extends[$key] = $value;
        return $this;
    }

    /**
     * Sets the current page title with automatic site branding.
     *
     * Appends the application name to the title if it's not already included.
     * Useful for consistent SEO page titles across your application.
     *
     * @param string $title The base title of the page (without app name).
     * 
     * @return self Return the instance of schema.
     */
    public function setTitle(string $title): self
    {
        self::$extends['title'] = str_contains($title, APP_NAME) 
            ? $title 
            : "{$title} | " . APP_NAME;
            
        return $this;
    }

    /**
     * Sets the current page description for schema object.
     *
     * Adds or updates the meta description that search engines display 
     * in search results. Keep this concise (around 150–160 characters).
     *
     * @param string $description The meta description for the page.
     * 
     * @return self Return the instance of schema.
     */
    public function setDescription(string $description): self
    {
        self::$extends['description'] = $description;
        return $this;
    }

    /**
     * Sets article page headline for SEO and structured data.
     *
     * The headline is often used by search engines and schema.org markup
     * to summarize the content. It should be short and clear.
     *
     * @param string $headline The headline text for the page.
     * 
     * @return self Return the instance of schema.
     */
    public function setHeadline(string $headline): self
    {
        self::$extends['article']['headline'] = $headline;
        return $this;
    }

    /**
     * Adds a canonical URL to avoid duplicate content issues.
     *
     * Canonical URLs tell search engines which version of a page is 
     * authoritative. You may add multiple canonicals if necessary 
     * (e.g., for multilingual pages).
     *
     * @param string $url The base canonical URL (e.g., `https://example.com`).
     * 
     * @return self Return the instance of schema.
     *
     * @note If multiple canonicals are added, ensure they are all valid 
     *       and relevant to avoid conflicting signals to search engines.
     */
    public function setCanonical(string $url): self
    {
        self::$extends['canonicals'][] = $url;
        return $this;
    }

    /**
     * Retrieve a schema configuration value by key with optional query string processing.
     *
     * Looks up a value from the both default and dynamic schema configuration.  
     * If the value is an array, it is returned as-is.  
     * If the value is a link or canonical, it may have query parameters appended.
     *
     * @param string $key The configuration key to retrieve.
     * @param mixed  $default Default value to return if the key is not found (default: `null`).
     * 
     * @return mixed Return the resolved configuration value, or $default if not found.
     */
    public function getObject(string $key, mixed $default = null): mixed
    {
        $value = self::$extends[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        if (is_array($value)) {
            return $value;
        }

        return rtrim($this->addQueryParams($value, $key), '/');
    }

    /**
     * Get the current page title set in the schema.
     *
     * Uses the internal schema data to fetch the 'title' key.  
     * Returns an empty string if not set.
     *
     * @return string Return the current page title.
     */
    public function getTitle(): string
    {
        return $this->getObject('title', '');
    }

    /**
     * Get the current page link (URL) set in the schema.
     *
     * Uses the internal schema data to fetch the 'url' key.  
     * Returns an empty string if not set.
     *
     * @return string Return the current page link URL.
     */
    public function getUrl(): string
    {
        return $this->getObject('url', '');
    }

    /**
     * Get the JSON-LD representation of the schema.
     *
     * Converts the current schema data to a JSON string, using unescaped slashes 
     * and pretty-print formatting for readability.
     *
     * @return string Return the schema as a JSON-LD string.
     */
    public function getJsonLdString(): string 
    {
        return json_encode($this->toJsonLd(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Get the JSON-LD schema as an HTML <script> tag.
     *
     * Useful for embedding structured data in a web page.  
     * Should be called once per page to output the schema object 
     * inside an application/ld+json script tag.
     *
     * @param string|null $id Optional ID attribute for the <script> tag.
     * 
     * @return string Return the HTML <script> element containing the schema.
     */
    public function getJsonLdScript(?string $id = null): string 
    {
        return '<script type="application/ld+json" ' 
            . ($id ? ' id="' . $id . '"' : '') 
            . '>' . $this->getJsonLdString() . '</script>';
    }

    /**
     * Generates SEO header tags including standard, Open Graph, and Twitter data.
     *
     * Combines keywords, description, canonical URLs, social metadata and custom tags
     * into properly formatted HTML `<meta>` or `<link>` tags. Should be called once per 
     * page to embed SEO information. 
     *
     * @return string Return the generated HTML meta tags.
     */
    public function getHeadTags(): string 
    {
        $meta = '<meta name="keywords" content="' . $this->escape(implode(', ', (array) $this->getObject('keywords', []))) . '" />'
            . '<meta name="description" content="' . $this->escape($this->getObject('description', '')) . '" />';

        foreach ((array) $this->getObject('canonicals', []) as $canonical) {
            $meta .= '<link rel="canonical" href="' . $this->escape(
                $this->addQueryParams($canonical, 'canonicals')
            ) . '" />';
        }

        if ($this->getObject('isArticle') && ($article = $this->getObject('article', []))) {
            $author = $article['author'] ?? [];

            if(($author['name'] ?? null)){
                $meta .= '<meta name="author" content="' . $author['name'] . '">';
            }

            $publisher = $article['publisher'] 
                ?? ($this->getObject('organization', [])['name'] ?? '');
            $readingTime = $article['reading_time'] ?? null;

            if($publisher){
                $meta .= '<meta property="article:publisher" content="' . $this->escape($publisher) . '" />';
            }

            if($readingTime){
                $meta .= '<meta name="reading-time" content="' . $this->escape($readingTime) . '" />';
            }

            $meta .= '<meta property="article:published_time" content="' . $this->toDate($article['published_date'] ?? '') . '" />'
                . '<meta property="article:modified_time" content="' . $this->toDate($article['modified_date'] ?? '') . '" />';
        }

        $meta .= $this->addOpenGraph('og', (array) $this->getObject('facebook', []));
        $meta .= $this->addOpenGraph('twitter', (array) $this->getObject('twitter', []));

        if (($tags = (array) $this->getObject('tags', []))) {
            $meta .= $this->buildHeaderTags($tags);
        }

        //return preg_replace('/<meta\b[^>]*\bcontent\s*=\s*(["\'])\s*(?:@)?\s*\1[^>]*>/i','', $meta);
        return $meta;
    }

    /**
     * Get the complete structured data schema for the current page.
     *
     * Builds and returns the schema.org data as an associative array.  
     * This should typically be called internally or once per page 
     * to generate the JSON-LD or meta tags for SEO.
     *
     * @return array<string,mixed> Return the structured data schema array.
     */
    public function toJsonLd(): array
    {
        $breadcrumbs = (array) $this->getObject('breadcrumbs', []);
        $image = $this->getObject('image', []);
        $organization = $this->getObject('organization', []);
        $description = $this->escape($this->getObject('description', ''));
        $orgDescription = $this->escape($organization['description'] ?? '');
        $imageUrl = $this->toImageUrl($image['url'] ?? '');

        // Request URL
        $url = $this->getObject('url');
        $language = $this->getDefault('language', 'en');
        $title = $this->getObject('title', '');

        // Default ID
        $id = rtrim($this->getDefault('id', APP_URL), '/');
        $urlId = rtrim($url, '/');
        $schema = [];

        // Ensure home and current page in breadcrumbs
        if($breadcrumbs && rtrim(self::$url, '/') !== $urlId){
            array_unshift($breadcrumbs, [
                'url' => self::$url,
                'home' => true,
                'name' => 'Home Page',
                'description' => $orgDescription,
            ]);
        }

        // Ensure current page is last in breadcrumbs
        $breadcrumbs[] = [
            'url' => $url,
            'name' => $title,
            'description' => $description,
        ];

        $schema['organization'] = array_merge([
            '@type' => 'Organization',
            '@id' => $id. '/#organization',
            'name' => $organization['name'] ?? '',
            'url' => self::$url,
            'brand' => (array) ($organization['brands'] ?? []),
            'duns' => $organization['duns'] ?? null,
            'email' => $organization['email'] ?? null,
            'sameAs' => (array) ($organization['links'] ?? []),
            'logo' => array_merge([
                '@type' => 'ImageObject',
                'inLanguage' => $language,
                '@id' => $id . '/#logo',
            ], $this->toImageObject($organization['logo'] ?? null)),
            'image' => ['@id' => $id . '/#logo'],
        ], $organization['entities'] ?? []);

        $address = $organization['address'] ?? null;
        if(!empty($address)){
            $schema['organization']['address'] = [
                '@type' => 'PostalAddress',
                'addressLocality' => $address['locality'] ?? null,
                'addressCountry' => $address['country'] ?? null,
                'postalCode' => $address['postcode'] ?? null,
                'streetAddress' => $address['street'] ?? null,
            ];
        }

        $search = $this->getObject('search');
        $schema['website'] = array_merge([
            '@type' => 'WebSite',
            '@id' => $id . '/#website',
            'url' => self::$url,
            'name' => $this->getObject('name', $title),
            'description' => $orgDescription,
            'publisher' => ['@id' => $id . '/#organization'],
            'potentialAction' => [
                [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => self::$url . ($search['query'] ?? '/search?q={search_term_string}')
                    ],
                    'query-input' => 'required name=' . ($search['input'] ?? 'search_term_string')
                ]
            ],
            'inLanguage' => $language,
        ], $this->getObject('website.entities', []) ?? []);

        $schema['webpage'] = array_merge([
            '@type' => 'WebPage',
            '@id' => $urlId . '/#webpage',
            'url' => $url,
            'name' => $title,
            'thumbnailUrl' => $this->toImageUrl($image['thumbnail'] ?? $image['url'] ?? ''),
            'description' => $description,
            'inLanguage' => $language,
            'potentialAction' => [
                '@type' => 'ReadAction',
                'target' => [$url]
            ],
            'about' => ['@id' => $urlId . '/#about'],
            'isPartOf' => ['@id' => $id . '/#website'],
            'image' => ['@id' => $urlId . '/#primaryimage'],
            'breadcrumb' => ['@id' => $urlId . '/#breadcrumb'],
            'primaryImageOfPage' => ['@id' => $urlId . '/#primaryimage'],
        ], $this->getObject('webpage.entities', []) ?? []);

        if($imageUrl){
            $schema['image'] = array_merge([
                '@type' => 'ImageObject',
                'inLanguage' => $language,
                '@id' => $urlId . '/#primaryimage',
            ], $this->toImageObject($image));
        }

        if ($this->getObject('isArticle')) {
            $article = $this->getObject('article', []);
            $author = $article['author'] ?? [];
            $articleDescription = $this->escape($article['description'] ?? $description ?? $orgDescription);
    
            self::$extends['keywords'] = array_merge(
                (array) (self::$extends['keywords'] ?? []),
                (array) ($article['keywords'] ?? [])
            );

            $schema['article'] = array_merge([
                '@type' => $article['type'] ?? 'Article',
                '@id' => $urlId . '/#article',
                'headline' => $article['headline'] ?? $articleDescription,
                'description' => $articleDescription,
                'name' => $article['title'] ?? $title,
                'datePublished' => $this->toDate($article['published_date'] ?? ''),
                'dateModified' => $this->toDate($article['modified_date'] ??  ''),
                'wordCount' => (int) ($article['word_count'] ?? 0),
                'commentCount' => (int) ($article['comment_count'] ?? 0),
                'thumbnailUrl' => $this->toImageUrl($article['thumbnail'] ?? $image['url'] ?? ''),
                'keywords' => (array) $this->getObject('keywords', []),
                'articleSection' => (array) ($article['section'] ??  []),
                'inLanguage' => $article['language'] ?? $language,
                'image' => $this->toImageObject($article['image'] ?? null, true) 
                    ?? ['@id' => $urlId . '/#primaryimage'],
                'potentialAction' => [
                    [
                        '@type' => 'CommentAction',
                        'name' => 'Comment',
                        'target' => [$urlId . '/#respond']
                    ]
                ],
                'copyrightYear' => $this->toYear($article['published_date'] ??  null),
                'isPartOf' => ['@id' => $urlId . '/#webpage'],
                'mainEntityOfPage' => ['@id' => $urlId . '/#webpage'],
                'publisher' => ['@id' => $id . '/#organization'],
                'author' => ['@id' => $id . '/#organization'],
                'copyrightHolder' => ['@id' => $id . '/#organization'],
                'citation' => $article['citation'] ?? null,
                'license' => $article['license'] ?? null,
            ], $article['entities'] ?? []);

            if(!empty($author)){
                $authorId = kebab_case($author['id'] ?? $author['name'] ?? '');
                $author['url'] ??= self::$url . "/author/{$authorId}";
                
                $schema['article']['author'] = array_merge([
                    '@type' => 'Person',
                    '@id' => $id . '#/schema/person/' . $authorId,
                    'name' => $author['name'] ?? $author['id'] ?? '',
                    'url' => $author['url'] ?? '',
                    'image' => array_merge([
                        '@type' => 'ImageObject',
                        '@id' => $id . "/author/{$authorId}/#personlogo",
                        'inLanguage' => $language,
                    ], $this->toImageObject($author['image'] ?? null)),
                ], $author['entities'] ?? []);
            }
        }

        if ($this->getObject('isProduct')) {
            $product = $this->getObject('product', []);
            $offers = $product['offers'] ?? [];
            $price = $offers['price'] ?? [];

            self::$extends['keywords'] = array_merge(
                (array) (self::$extends['keywords'] ?? []),
                (array) ($product['keywords'] ?? [])
            );

            $schema['product'] = array_merge([
                '@type' => $product['type'] ?? 'Product',
                'name' => $product['name'] ?? $title,
                'description' => $this->escape($product['description'] ?? $description ?? $orgDescription),
                'category' => $product['category'] ?? '',
                'url' => $product['url'] ?? $url,
                'image' => $this->toImageObject($product['image'] ?? null, true) 
                    ?? ['@id' => $urlId . '/#primaryimage'],
                'brand' => [
                    '@type' => 'Brand',
                    'name' => (string) ($product['brand']['name'] ?? '')
                ],
                'offers' => [
                    '@type' => 'Offer',
                    'priceCurrency' => $price['currency'] ?? 'USD',
                    'price' => (string) ($price['price'] ?? '0.00'),
                    'priceValidUntil' => $this->toDate($price['validUntil'] ?? null),
                    'availability' => $offers['availability'] ?? 'InStock',
                    'itemCondition' => $offers['condition'] ?? 'NewCondition'
                ],
            ], $product['entities'] ?? []);
        }

        $schema['global'] = $this->getObject('global.entities', []);
        $schema['breadcrumb'] = [
            '@type' => 'BreadcrumbList',
            '@id' => $urlId . '/#breadcrumb',
            'itemListElement' => $this->breadcrumbs($breadcrumbs, $orgDescription)
        ];

        return [
            '@context' => 'https://schema.org',
            '@graph' => $this->filter($schema)
        ];
    }

    /**
     * Filters schema data by removing keys with unwanted values and dropping empty items.
     *
     * Iterates through each schema block and removes any values that match
     * the predefined filter list stored in $this->filterValues. 
     * Empty arrays are skipped entirely to prevent adding invalid or useless data 
     * to the resulting @graph structure.
     *
     * @param array $schema Input schema data to be cleaned.
     * @return array Cleaned schema data, ready for inclusion in the structured data graph.
     */
    private function filter(array $schema): array 
    {
        $graph = [];
        foreach ($schema as $item) {
            if($item === []){
                continue;
            }

            $clean = array_filter($item, fn(mixed $v) => !in_array($v, $this->filterValues, true));

            if ($clean !== []) {
                $graph[] = $clean;
            }
        }

        return $graph;
    }

    /**
     * Safely escape a string for HTML output.
     *
     * Converts special characters to HTML entities to prevent XSS attacks
     * when rendering dynamic content in HTML.
     *
     * @param string $input The raw input string.
     * 
     * @return string Return the escaped string safe for HTML output.
     */
    private function escape(?string $input): string 
    {
        return $input 
            ? htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false)
            : (string) $input;
    }

    /**
     * Convert an image value into a URL string, ImageObject, or a list of ImageObjects.
     *
     * Accepts either a string (direct image URL), an associative array (structured image data),
     * or a list of image URLs/objects if `$allowList` is enabled.
     *
     * @param string|array|null $image The image data (URL, array, or list of images).
     * @param bool $allowList Allow array lists for multiple images.
     *
     * @return string|array|null Returns a single URL string, an ImageObject array, 
     *                           a list of ImageObjects, or null if no image is provided.
     */
    private function toImageObject(string|array|null $image, bool $allowList = false): string|array|null
    {
        if(!$image){
            return $allowList ? null : [];
        }

        if(is_string($image)){
            return $this->toImageUrl($image);
        }

        if($allowList && array_is_list($image)){
            $list = [];

            foreach($image as $url){
                $list[] = $this->toImage($url);
            }

            return $list;
        }

        return $this->toImage($image) ?: ($allowList ? null : []);
    }

    /**
     * Normalize a single image value into an ImageObject.
     *
     * Builds a structured ImageObject array from a URL string or associative array.
     * Adds optional caption, height, width, and merges custom entity properties.
     *
     * @param array|string|null $item Image URL or structured image array.
     *
     * @return array|string|null Returns an ImageObject array or URL string, 
     *                           or null if no valid image is found.
     */
    private function toImage(array|string|null $item): mixed
    {
        $image = $item['url'] ?? null;

        if(!$image){
            return $this->toImageUrl($item);
        }

        $url = isset($item['url']) ? $this->toImageUrl($item['url']) : $this->toImageUrl($item);
        if (!$url) {
            return null;
        }

        $object = array_merge([
            'url' => $url,
            'contentUrl' => $url,
        ], $image['entities'] ?? []);

        if (!empty($item['caption'])) {
            $object['caption'] = $item['caption'];
        }
        if (!empty($item['width'])) {
            $object['width'] = $item['width'];
        }
        if (!empty($item['height'])) {
            $object['height'] = $item['height'];
        }

        return $object;
    }

    /**
     * Build valid <meta> and <link> tags from a schema config.
     *
     * @param array $items Array of tag definitions.
     * 
     * @return string HTML string with head tags only.
     */
    private function buildHeaderTags(array $items): string 
    {
        $allowed = ['meta', 'link'];
        $html = '';

        foreach ($items as $item) {
            if (!isset($item['@type'], $item['attributes']) || !is_array($item['attributes'])) {
                continue; 
            }

            $tag = strtolower(trim($item['@type']));

            if (!in_array($tag, $allowed, true)) {
                continue;
            }

            $attr = '';
            foreach ($item['attributes'] as $key => $value) {
                if ($value === false || $value === null){
                    continue;
                }

                $attr .= $this->escape($key) . '="';

                if ($value === true) {
                    $attr .=  $this->escape($key) . '" ';
                } else {
                    $attr .= $this->escape((string) $value) . '" ';
                }
            }

            $html .= "<{$tag} {$attr}/>\n";
        }

        return $html;
    }

    /**
     * Build OpenGraph or Twitter meta tags.
     *
     * @param string $graph      Either 'og' or 'twitter'
     * @param array  $properties Key/value pairs of graph data
     * @return string HTML meta tags string
     */
    private function addOpenGraph(string $graph, array $properties): string 
    {
        $tag = '';
        $properties += [
            'title' => $this->getObject("title", ''),
            'description' => $this->getObject("description", '')
        ]; 

        if($graph === 'og'){
            $properties['url'] ??= $this->getObject('url', '');
            $properties['locale'] ??= $this->getDefault('locale', 'en');
            $properties['site_name'] ??= $this->getObject("name", '');
        }

        if(!isset($properties['image']['url']) && ($image = $this->getObject('image', []))){
            $properties['image'] = $image;
        }

        foreach($properties as $property => $content){
            if($content !== 0 && empty($content)){
                continue;
            }

            $isArray = is_array($content);

            if($property === 'image' && $isArray){
                foreach($content as $key => $value){
                    $value = $this->escape(is_array($value) ? implode(', ', $value) : $value);
                    $idx = ":$key" ;

                    if($key === 'url'){
                        $idx = '';
                        $value = $this->toImageUrl($value);
                    }
      
                    $tag .= '<meta property="' . $graph . ':image' . $idx .'" content="'. $value .'" />';
                }

                continue;
            }

            if($graph === 'twitter' && ($property === 'site' || $property === 'creator')){
                $content = '@' . ltrim($content, '@');
            }

            $key = "$graph:$property";

            if($graph === 'og' && $property === 'pages'){
                $key = "fb:pages";
            }

            $value = $this->escape($isArray ? implode(', ', $content) : $content);

            $tag .= '<meta property="' . $key .'" content="'. $value .'" />';
        }

        return $tag;
    }

    /**
     * Retrieve an absolute image URL for schema or meta tags.
     *
     * If the given key contains an absolute URL (http/https), it is returned as-is.  
     *
     * @param string|null $image Image url or name.
     * 
     * @return string Return the full image URL or an empty string if unavailable.
     */
    private function toImageUrl(?string $image): string 
    {
        if(empty($image)){
            return '';
        }

        if ((str_starts_with($image, 'http://') || str_starts_with($image, 'https://'))) {
            return $image;
        }

        $imageObject = $this->getObject('image', []);

        if($imageObject === []){
            return $image;
        }

        $assets = rtrim((string) ($imageObject['assets'] ?? ''), '/');

        return ($assets === '') 
            ? $image 
            : $assets . '/' . ltrim($image, '/');
    }

    /**
     * Converts a date string to ISO 8601 format.
     *
     * @param string $key The key to convert it's value to date.
     * 
     * @return string Return the date in ISO 8601 format.
     */
    private function toDate(?string $date): string
    {
        if(empty($date)){
            return '';
        }

        try{
            return Time::parse($date)->format('Y-m-d\TH:i:sP');
        }catch(\Throwable){}

        return $date;
    }

    /**
     * Get year from publish or modified date.
     * 
     * @param string $date The date to get the year from.
     * 
     * @return string Return year from publish or modified date.
     */
    private function toYear(?string $date): string 
    {
        return ($date === null) ? '' : date('Y', strtotime($date));
    }

    /**
     * Create and return breadcrumb list array.
     * 
     * @param array $breadcrumbs The current page breadcrumbs.
     * 
     * @return array<int,mixed> Return the breadcrumb list.
     */
    private function breadcrumbs(array $breadcrumbs, ?string $orgDescription): array
    {
        $listItem = [];

        foreach ($breadcrumbs as $index => $page) {
            $position = $index + 1;
            $item = [
                '@type' => 'ListItem',
                'position' => $position,
            ];

            $previous = ($index > 0) ? $breadcrumbs[$index - 1] : false;
            $next = ($index < count($breadcrumbs) - 1) ? $breadcrumbs[$position] : false;

            if($next){
                $item['nextItem'] = rtrim($next['url'], '/') . '/#listItem';
            }

            if($previous){
                $item['previousItem'] = rtrim($previous['url'], '/') . '/#listItem';
            }

            $description = $page['description'] ?? null;

            if(!$description){
                $item['name'] = $page['name'] ?? '';
                $item['item'] = $page['url'] ?? '';
                $listItem[] = $item;
                continue;
            }
            
            $item['item'] = [
                '@type' => 'WebPage',
                '@id' => rtrim($page['url'] ?? '', '/') . '/#webpage',
                'name' => $page['name'] ?? '',
                'description' => $this->escape($page['description'] ?? $orgDescription ?? ''),
                'url' => $page['url'] ?? ''
            ];

            $listItem[] = $item;
        }

        return $listItem;
    }

    /**
     * Loads the default configuration values for SEO meta data.
     *
     * @return void
     */
    private function intExtendsForWebpage(): void
    {
        self::$extends = [
            'id' => $this->getDefault('id', APP_URL),
            'name' => $this->getDefault('name', APP_NAME),
            'url' => $this->getDefault('url'),
            'language' => $this->getDefault('language', 'en-US'),
            'locale' => $this->getDefault('locale', 'en'),
            'title' => $this->getDefault('title'),
            'image' => $this->getDefault('image', []),
            'keywords' => $this->getDefault('keywords', []),
            'isArticle' => false,
            'isProduct' => false,
            'article' => [],
            'product' => [],
            'canonicals' => [],
            'breadcrumbs' => [],
            'global.entities' => [],
            'website.entities' => [],
            'webpage.entities' => [],
            'organization' => $this->getDefault('organization', []),
            'description' => $this->getDefault('description'),
            'facebook' => $this->getDefault('facebook', []),
            'twitter' => $this->getDefault('twitter', []),
            'search' => $this->getDefault('search', []),
        ];
    }

    /**
     * Get item from manifest.
     * 
     * @param string $key The key to return it's value.
     * @param mixed $default The default value (default: null).
     * 
     * @return mixed Return the values of the provided key.
     */
    private function getDefault(string $key, mixed $default = null): mixed
    {
        return self::$default[$key] ?? $default;
    }
    
    /**
     * Add URL query parameters if any.
     *
     * @param string $url The URL to add.
     * @param string $key The Key to validate.
     * 
     * @return string Return URL with query param.
     */
    private function addQueryParams(string $url, string $key): string 
    {
        $query = Request::getInstance()->getQuery();

        if(empty($query) || !in_array($key, ['url', 'canonicals'])){
            return $url;
        }

        return str_contains($url, '?') ? "{$url}&{$query}" : "{$url}?{$query}";
    }
}