<?php
/**
 * Luminova Framework SEO schema definition and generator.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng/docs/3.0.2/configs/schema
 */
namespace Luminova\Seo;  

use \Luminova\Interface\LazyInterface;
use \Luminova\Time\Time;

final class Schema implements LazyInterface
{
    /**
     * Application view link.
     * 
     * @var string $link
     */
    private static string $link = '';

    /**
     * Meta object json.
     * 
     * @var array<string,mixed> $manifest
     */
    private static ?array $manifest = null;

    /**
     * Default configuration.
     * 
     * @var array $defaultConfig
     */
    private static array $defaultConfig = [];

    /**
     * User passed configurations.
     * 
     * @var array $extendedConfig
     */
    private static array $extendedConfig = [];

    /**
     * Class static singleton instance.
     * 
     * @var static $instance
     */
    private static ?Schema $instance = null;

    /**
     * Initialize the Schema class constructor and load default configurations.
     * 
     * @see /app/Config/Meta.php
     * @see https://luminova.ng/docs/3.0.2/configs/schema
     */
    public function __construct()
    {
        self::$manifest ??= self::loadSchema();
        self::$link = APP_URL;
        self::defaultConfig();
    }

    /**
     * Initialize and retrieve shared instance singleton class.
     * 
     * @return self Return a static class instance.
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Sets the link URL for the web page.
     *
     * @param string $link The current page URL.
     * 
     * @return self Return schema class instance.
     */
    public function setLink(string $link): self
    {
        self::$defaultConfig['link'] = $link;

        return $this;
    }

    /**
     * Sets the current page information from array configuration.
     * This method allow you to set the current page information from an array.
     *
     * @param array<string,mixed> $config The extended configuration.
     * 
     * @return self Return schema class instance.
     */
    public function setConfig(array $config): self
    {
       self::$extendedConfig = $config;
       return $this;
    }

    /**
     * Sets the current page title for SEO purposes.
     *
     * @param string $title The page title.
     * 
     * @return self Return schema class instance.
     */
    public function setTitle(string $title): self
    {
        self::$defaultConfig['title'] = str_contains($title, '| ' . APP_NAME) 
            ? $title 
            : "{$title} | " . APP_NAME;
        return $this;
    }

    /**
     * Sets the current page description for SEO purposes.
     *
     * @param string $description The page description.
     * 
     * @return self Return schema class instance.
     */
    public function setDescription(string $description): self
    {
        self::$defaultConfig['page_description'] = $description;
        return $this;
    }


    /**
     * Sets the current page headline for SEO purposes.
     *
     * @param string $headline The page headline description.
     * 
     * @return self Return schema class instance.
     */
    public function setHeadline(string $headline): self
    {
        self::$defaultConfig['headline'] = $headline;
        return $this;
    }

    /**
     * Sets the canonical URL for SEO purposes.
     *
     * @param string $canonical The canonical URL.
     * @param string $view The view paths to prepend to canonical URL (default:blank-string).
     * 
     * @return self Return schema class instance.
     */
    public function setCanonical(string $canonical, string $view = ''): self
    {
        self::$defaultConfig['canonical'] = $canonical . $view;
        self::$defaultConfig['link'] = $canonical . $view;
        return $this;
    }

    /**
     * Gets the current page title.
     *
     * @return string Return the current page title.
     */
    public function getTitle(): string
    {
        return self::getConfig('title') ?? '';
    }

    /**
     * Gets the current page link.
     *
     * @return string Return the current page link URL.
     */
    public function getLink(): string
    {
        return self::getConfig('link') ?? '';
    }

    /**
     * Converts the schema data to JSON string format.
     *
     * @return string Return the JSON representation of the schema object.
     */
    public function getGraph(): string 
    {
        return json_encode($this->getSchema(), JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    }

    /**
     * Get the HTML script tag containing the schema for embedding on a web page.
     * Call this method once on page creation to generate schema object in javaScript tag for the current page.
     * 
     * @return string Return the HTML schema object for embedding.
     */
    public function getScript(): string 
    {
        return '<script type="application/ld+json">' . $this->getGraph() . '</script>';
    }

    /**
     * Generates HTML Schema meta tags for SEO purposes.
     * Call this method once on page creation to generate HTML meta tags for the current page.
     * 
     * @return string Return the HTML meta tags.
     */
    public function getMeta(): string 
    {
        $meta = '<meta name="keywords" content="' . implode(', ', (array) self::getConfig("keywords")) . '">
            <meta name="description" content="' . self::getConfig("page_description") . '" />';
        
        if (!empty(self::getConfig("canonical"))) {
            $meta .= '<!-- Canonical link tag --> 
                <link rel="canonical" href="' . self::getConfig("canonical") . '" />';
        }

        if (self::getConfig("isArticle")) {
            $meta .= '<meta property="article:publisher" content="' . self::getConfig("company_name") . '" />
                <meta property="article:published_time" content="' . self::toDate('published_date') . '" />
                <meta property="article:modified_time" content="' . self::toDate('modified_date') . '" />';
        }

        $meta .= '<meta property="og:locale" content="' . self::getManifest('locale', 'en') . '" />
            <meta property="og:type" content="website" />
            <meta property="og:title" content="' . self::getConfig("title") . '" />
            <meta property="og:description" content="' . self::getConfig("page_description") . '" />
            <meta property="og:url" content="' . self::getConfig("link") . '" />
            <meta property="og:site_name" content="' . self::getConfig("name") . '" />
            <meta property="og:image" content="' . self::getConfig("image_assets") . self::getConfig("image_name") . '" />
            <meta property="og:image:width" content="' . self::getConfig("image_width") . '" />
            <meta property="og:image:height" content="' . self::getConfig("image_height") . '" />
            <meta property="og:image:type" content="' . self::getConfig("image_type") . '" />';
        
        $meta .= '<meta name="twitter:card" content="summary" />
            <meta name="twitter:site" content="@' . self::getManifest('twitter_name', '') . '" />
            <meta name="twitter:label1" content="Est. reading time" />
            <meta name="twitter:data1" content="37 minutes" />';

        return $meta;
    }

    /**
     * Generate structured data schema for SEO purposes.
     * Call this method once on page load to generate structured data.
     *
     * @return array<string,mixed> Return the structured data schema.
     */
    public function getSchema(): array
    {
        $breadcrumbs = (array) self::getConfig('breadcrumbs');
        $schema = [];

        array_unshift($breadcrumbs, [
            'link' => self::$link,
            'home' => true,
            'name' => 'Home Page',
            'description' => self::getConfig('company_description'),
        ]);

        $breadcrumbs[] = [
            'link' => self::getConfig('link'),
            'name' => self::getConfig('title'),
            'description' => self::getConfig('page_description'),
        ];

        $schema['organization'] = [
            '@type' => 'Organization',
            '@id' => self::getManifest('site_id', '') . '/#organization',
            'name' => self::getConfig('company_name'),
            'url' => self::$link . '/',
            'brand' => self::getConfig('company_brands'),
            'duns' => self::getConfig('company_duns'),
            'email' => self::getConfig('company_email'),
            'sameAs' => (array) self::getManifest('social_media', []),
            'logo' => [
                '@type' => 'ImageObject',
                'inLanguage' => self::getManifest('language', 'en'),
                '@id' => self::getManifest('site_id', '') . '/#logo',
                'url' => self::getConfig('image_assets') . self::getManifest('logo_image_name', ''),
                'contentUrl' => self::getConfig('image_assets') . self::getManifest('logo_image_name', ''),
                'width' => self::getManifest('logo_image_width', 0),
                'height' => self::getManifest('logo_image_height', 0),
                'caption' => self::getConfig('title')
            ],
            'image' => [
                '@id' => self::getManifest('site_id', '') . '/#logo'
            ],
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => self::getConfig('address_locality'),
                'addressCountry' => self::getConfig('address_country'),
                'postalCode' => self::getConfig('address_postalcode'),
                'streetAddress' => self::getConfig('address_street')
            ]
        ];

        $schema['website'] = [
            '@type' => 'WebSite',
            '@id' => self::getManifest('site_id', '') . '/#website',
            'url' => self::$link . '/',
            'name' => self::getConfig('name'),
            'description' => self::getConfig('company_description'),
            'publisher' => [
                '@id' => self::getManifest('site_id', '') . '/#organization'
            ],
            'potentialAction' => [
                [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => self::$link . self::getConfig('search_query')
                    ],
                    'query-input' => 'required name=' . self::getConfig('search_input')
                ]
            ],
            'inLanguage' => self::getManifest('language', 'en'),
        ];

        $schema['webpage'] = [
            '@type' => 'WebPage',
            '@id' => self::getConfig('link') . '/#webpage',
            'url' => self::getConfig('link'),
            'name' => self::getConfig('title'),
            'isPartOf' => [
                '@id' => self::getManifest('site_id', '') . '/#website'
            ],
            'about' => [
                '@id' => self::getConfig('link') . '/#about'
            ],
            'primaryImageOfPage' => [
                '@id' => self::getConfig('link') . '/#primaryimage'
            ],
            'image' => [
                '@id' => self::getConfig('link') . '/#primaryimage'
            ],
            'thumbnailUrl' => self::getConfig('image_assets') . self::getConfig('image_name'),
            'description' => self::getConfig('page_description'),
            'breadcrumb' => [
                '@id' => self::getConfig('link') . '/#breadcrumb'
            ],
            'inLanguage' => self::getManifest('language', 'en'),
            'potentialAction' => [
                '@type' => 'ReadAction',
                'target' => [
                    self::getConfig('link')
                ]
            ]
        ];

        $schema['image'] = [
            '@type' => 'ImageObject',
            'inLanguage' => self::getManifest('language', 'en'),
            '@id' => self::getConfig('link') . '/#primaryimage',
            'url' => self::getConfig('image_assets') . self::getConfig('image_name'),
            'contentUrl' => self::getConfig('image_assets') . self::getConfig('image_name'),
            'width' => self::getConfig('image_width'),
            'height' => self::getConfig('image_height')
        ];

        $schema['breadcrumb'] = [
            '@type' => 'BreadcrumbList',
            '@id' => self::getConfig('link') . '/#breadcrumb',
            'itemListElement' => self::breadcrumbs($breadcrumbs)
        ];

        if (self::getConfig('isArticle')) {
            $authorId = kebab_case(self::getConfig('author'));

            $schema['article'] = [
                '@type' => self::getConfig('article_type'),
                '@id' => self::getConfig('link') . '/#article',
                'isPartOf' => [
                    '@id' => self::getConfig('link') . '/#webpage'
                ],
                'author' => [
                    '@type' => 'Person',
                    '@id' => self::getManifest('site_id', '') . '#/schema/person/' .$authorId,
                    'name' => self::getConfig('author'),
                    'image' => [
                        '@type' => 'ImageObject',
                        '@id' => self::getManifest('site_id', '') . '/author/' . $authorId . '/#personlogo',
                        'inLanguage' => self::getManifest('language', 'en'),
                        'url' => self::getConfig('image_assets') . 'logo-square-dark.png',
                        'contentUrl' => self::getConfig('image_assets') . 'logo-square-dark.png',
                        'caption' => self::getConfig('author')
                    ],
                    'url' => self::$link . '/author/' . $authorId
                ],
                'headline' => self::getConfig('headline'),
                'name' => self::getConfig('title'),
                'datePublished' => self::toDate('published_date'),
                'dateModified' => self::toDate('modified_date'),
                'mainEntityOfPage' => [
                    '@id' => self::getConfig('link') . '/#webpage'
                ],
                'wordCount' => (int) self::getConfig('word_count'),
                'commentCount' => (int) self::getConfig('total_comments'),
                'publisher' => [
                    '@id' => self::getManifest('site_id', '') . '/#organization'
                ],
                'image' => [
                    '@id' => self::getConfig('link') . '/#primaryimage'
                ],
                'thumbnailUrl' => self::getConfig('image_assets') . self::getConfig('image_name'),
                'keywords' => (array) self::getConfig('article_keywords', []) + self::getConfig('keywords', []),
                'articleSection' => (array) self::getConfig('article_section', 'Blog'),
                'inLanguage' => self::getManifest('language', 'en'),
                'potentialAction' => [
                    [
                        '@type' => 'CommentAction',
                        'name' => 'Comment',
                        'target' => [self::getConfig('link') . '/#respond']
                    ]
                ],
                'copyrightYear' => self::toYear('published_date'),
                'copyrightHolder' => [
                    '@id' => self::getManifest('site_id', '') . '/#organization'
                ],
                'citation' => self::getConfig('citation'),
                'license' => self::getConfig('license'),
            ];
        }

        if (self::getConfig('isProduct')) {
            $schema['product'] = [
                '@type' => self::getConfig('product_type'),
                'name' => self::getConfig('title'),
                'description' => self::getConfig('page_description'),
                'category' => self::getConfig('category'),
                'url' => self::getConfig('link'),
                'image' => self::getConfig('product_image_link'),
                'brand' => [
                    '@type' => 'Brand',
                    'name' => self::getConfig('brand')
                ],
                'offers' => [
                    '@type' => 'Offer',
                    'priceCurrency' => self::getConfig('currency'),
                    'price' => (string) self::getConfig('price'),
                    'availability' => self::getConfig('availability')
                ]
            ];
        }

        $finalSchema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                array_values($schema)
            ]
        ];

        return $finalSchema;
    }

    /**
     * Get item from extended setting or manifest.
     * 
     * @param string $key The key to return it's value.
     * @param mixed $default The default value (default: null).
     * 
     * @return mixed Return the values of the provided key.
     */
    public static function getMutable(string $key, mixed $default = null): mixed
    {
        return self::getConfig($key) ?? self::getManifest($key) ?? $default;
    }

    /**
     * Get item from extended setting or manifest.
     * 
     * @param string $key The key to return it's value.
     * @param string $fallback The fallback key to return it's value.
     * @param mixed $default The default value (default: null).
     * 
     * @return mixed Return the values of the provided key.
     */
    private static function getFallback(string $key, string $fallback, mixed $default = null): mixed
    {
        return self::getConfig($key) ?? self::getConfig($fallback) ?? $default;
    }

    /**
     * Converts a date string to ISO 8601 format.
     *
     * @param string $key The key to convert it's value to date.
     * 
     * @return string Return the date in ISO 8601 format.
     */
    private static function toDate(string $key): string
    {
        $date = self::getConfig($key);
        return ($date === null) ? '' : Time::parse($date)->format('Y-m-d\TH:i:sP');
    }

    /**
     * Get year from publish or modified date.
     * 
     * @param string $key The key to get the year from.
     * 
     * @return string Return year from publish or modified date.
     */
    private static function toYear(string $key): string 
    {
        $value = self::getConfig($key);
        return ($value === null) ? '' : date('Y', strtotime($value));
    }

    /**
     * Retrieves a configuration value by key and appends a query string if necessary.
     *
     * @param string $key The key of the configuration value.
     * @param mixed $default The default value (default: null).
     * 
     * @return mixed Return the configured value with an optional query string.
     */
    private static function getConfig(string $key, mixed $default = null): mixed
    {
        $config = array_replace(self::$defaultConfig, array_filter(self::$extendedConfig));
        $param = $config[$key] ?? '';
        $value = null;

        if($param !== '' && is_array($param)){
            $value = $param;
        }elseif($param !== ''){
            if(self::shouldAddParam($key, $param)){
                $param .= '?' . self::getQuery();
            }

            $value = rtrim($param, '/');
        }
    
        if($value !== null && $key == 'image_assets'){
            return "{$value}/";
        }
        
        return $value ?? $default;
    }

    /**
     * Determines whether to add a query parameter to a URL.
     *
     * @param string $key The key of the parameter.
     * @param string $param The parameter value.
     * 
     * @return bool Return true if the parameter should be added, false otherwise.
     */
    private static function shouldAddParam(string $key, string $param): bool 
    {
        return (in_array($key, ['link', 'canonical']) && !self::has_query_parameter($param) && (self::getQuery() !== null && self::getQuery() !== ''));
    }

    /**
     * Retrieves the query string from the current request URI.
     *
     * @return ?string The query string or null if not present.
     */
    private static function getQuery(): ?string 
    {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    }

    /**
     * Create and return breadcrumb list array.
     * 
     * @param array $breadcrumbs The current page breadcrumbs.
     * 
     * @return array<int,mixed> Return the breadcrumb list.
     */
    private static function breadcrumbs(array $breadcrumbs): array
    {
        $itemListElement = [];

        foreach ($breadcrumbs as $index => $page) {
            $item = [
                '@type' => 'ListItem',
                'position' => $index + 1
            ];

            $previous = (($index > 0) ? $breadcrumbs[$index - 1] : false);
            $next = (($index < count($breadcrumbs) - 1) ? $breadcrumbs[$index + 1] : false);

            if($next){
                $item['nextItem'] =  $next['link'] . '/#listItem';
            }

            if($previous){
                $item['previousItem'] = $previous['link'] . '/#listItem';
            }
            
            $item['item'] = [
                '@type' => 'WebPage',
                '@id' => $page['link'] . '/#webpage',
                'name' => $page['name'],
                'description' => $page['description'] ?? self::getConfig('company_description'),
                'url' => $page['link']
            ];

            $itemListElement[] = $item;
        }

        return  $itemListElement;
    }

    /**
     * Loads the default configuration values for SEO meta data.
     *
     * @return void
     */
    private static function defaultConfig(): void
    {
        self::$defaultConfig = [
            'link' => self::getManifest('start_url'),
            'canonical' => self::$link,
            'breadcrumbs' => [],
            'image_assets' => self::getManifest('image_assets'),
            'name' => self::getManifest('name'),
            'company_brands' => self::getManifest('company_brands', [self::getManifest('name')]),
            'company_duns'  => self::getManifest('duns'),
            'company_name' => self::getManifest('company_name'),
            'company_email' => self::getManifest('company_email'),
            'company_description' => self::getManifest('company_description'),
            'address_locality' => self::getManifest('address_locality', ''),
            'address_country' => self::getManifest('address_country', ''),
            'address_postalcode' => self::getManifest('address_postalcode', ''),
            'address_street' => self::getManifest('address_street', ''),
            'page_description' => self::getManifest('page_description'),
            'title' => self::getManifest('title'),
            'headline' => self::getFallback('headline', 'page_description'),
            'image_name' =>self::getManifest('image_name'),
            'image_width' => self::getManifest('image_width'),
            'image_height' => self::getManifest('image_height'),
            'image_type' => self::getManifest('image_type'),
            'site_published_date' => self::getMutable('site_published_date'),
            'site_modified_date' => self::getMutable('site_modified_date'),
            'published_date' => self::getMutable('published_date'),
            'modified_date' => self::getMutable('modified_date'),
            'keywords' => self::getMutable('keywords', []),
            'isArticle' => false,
            'article_type' => 'Article',
            'article_keywords' => self::getMutable('article_keywords', []),
            'article_section' => self::getMutable('article_section', []),
            'word_count' => 1500,
            'total_comments' => 0,
            'author' => 'Author Name',
            'isProduct' => false,
            'product_type' => 'Product',
            'product_image_link' => '',
            'product_category' => 'Electronics',
            'twitter_name' => self::getManifest('twitter_name', ''),
            'search_query' => self::getManifest('search_query', '/search?q={search_term_string}'),
            'search_input' => self::getManifest('search_input', 'search_term_string'),
            'availability' => 'InStock',
            'currency' => 'NGN',
            'price' => '0.00',
            'brand' => '',
            'citation' => '',
            'license' => '',
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
    private static function getManifest(string $key, mixed $default = null): mixed
    {
        return self::$manifest[$key]??$default;
    }
    
    /**
     * Checks if a URL has query parameters.
     *
     * @param string $url The URL to check.
     * 
     * @return bool Return true if the URL has query parameters, otherwise false.
     */
    private static function has_query_parameter(string $url): bool 
    {
        if (str_contains($url, '?')) {
            $path_and_query = explode('?', $url);

            if ($path_and_query[1] === '') {
                return false;
            }
        }
       
        return false;
    }

    /**
     * Reads the manifest file and returns its content as an object.
     * 
     * @return array<string,mixed> Return the manifest content as an object.
     */
    private static function loadSchema(): array
    {
        $path = root('/app/Config/') . 'Schema.php';
    
        if (file_exists($path)) {
            return require $path;
        }
    
        return [];
    }
}