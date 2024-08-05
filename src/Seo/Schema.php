<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Seo;  

use \Luminova\Time\Time;

final class Schema
{
    /**
     * @var string $link application view link.
    */
    private static string $link = '';

    /**
     * @var array $manifest meta object json.
    */
    private static ?array $manifest = null;

    /**
     * @var array $defaultConfig default configuration
    */
    private static array $defaultConfig = [];

    /**
     * @var array $extendedConfig user passed configurations
    */
    private static array $extendedConfig = [];

    /**
     * @var static $instance class static singleton instance
    */
    private static ?Schema $instance = null;

    /**
     * Initialize class constructor and load configurations.
     * @see /app/Config/Meta.php
     * @see https://luminova.ng/docs/3.0.2/configs/schema
    */
    public function __construct()
    {
        static::$manifest ??= static::loadSchema();
        static::$link = APP_URL;
        static::defaultConfig();
    }

    /**
     * Get shared instance singleton class.
     * 
     * @return static $instance Class instance.
     */
    public static function getInstance(): static
    {
        return static::$instance ??= new static();
    }

    /**
     * Sets the link URL for the web page.
     *
     * @param string $link The link URL.
     * 
     * @return self This Class instance.
    */
    public function setLink(string $link): self
    {
        static::$defaultConfig["link"] = $link;

        return $this;
    }

     /**
     * Sets the configuration for the Schema instance.
     *
     * @param array $config The extended configuration.
     * 
     * @return void
     */
    public function setConfig(array $config): void
    {
       static::$extendedConfig = $config;
    }

    /**
     * Sets the page title for SEO purposes.
     *
     * @param string $title The page title.
     * @return void
     */
    public function setTitle(string $title): void
    {
        static::$defaultConfig["title"] = str_contains($title, '| ' . APP_NAME) ? $title : "{$title} | " . APP_NAME;
    }

    /**
     * Sets the canonical URL for SEO purposes.
     *
     * @param string $link The canonical URL.
     * @param string $view The view URI segments.
     * 
     * @return void
     */
    public function setCanonical(string $link, string $view = ''): void
    {
        static::$defaultConfig["canonical"] = $link . $view;
        static::$defaultConfig["link"] = $link . $view;
    }

    /**
     * Gets the current page title.
     *
     * @return string The page title.
    */
    public function getTitle(): string
    {
        return static::getConfig("title") ?? '';
    }

     /**
     * Gets the current page link.
     *
     * @return string The page link url.
    */
    public function getLink(): string
    {
        return static::getConfig("link") ?? '';
    }

    /**
     * Converts a date string to ISO 8601 format.
     *
     * @param string $date The input date string.
     * 
     * @return string The date in ISO 8601 format.
     */
    private static function toDate(string $date): string
    {
        return Time::parse($date)->format('Y-m-d\TH:i:sP');
    }

    /**
     * Retrieves a configuration value by key and appends a query string if necessary.
     *
     * @param string $key The key of the configuration value.
     * @param mixed $default Default value.
     * 
     * @return mixed The configured value with an optional query string.
     */
    private static function getConfig(string $key, mixed $default = null): mixed
    {
        $config = array_replace(static::$defaultConfig, array_filter(static::$extendedConfig));
        $param = $config[$key] ?? '';

        if(is_array($param)){
            $value = $param;
        }else{
            if(static::shouldAddParam($key, $param)){
                $param .= '?' . static::getQuery();
            }

            $value = rtrim($param, "/");
        }
    
        if($key == 'image_assets'){
            return "{$value}/";
        }
        
        return $value ?? $default;
    }

    /**
     * Determines whether to add a query parameter to a URL.
     *
     * @param string $key The key of the parameter.
     * @param string $param The parameter value.
     * @return bool True if the parameter should be added, false otherwise.
     */
    private static function shouldAddParam(string $key, string $param): bool 
    {
        return (in_array($key, ['link', 'canonical']) && !static::has_query_parameter($param) && (static::getQuery() !== null && static::getQuery() !== ''));
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
     * Converts the schema data to JSON string format.
     *
     * @return string The JSON representation of the schema data.
     */
    public function getGraph(): string 
    {
        return json_encode($this->getSchema(), JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    }

    /**
     * Get the HTML script tag containing the schema for embedding on a web page.
     * 
     * @return string The HTML code for embedding the schema.
     */
    public function getScript(): string 
    {
        return '<script type="application/ld+json">' . $this->getGraph() . '</script>';
    }

    /**
     * Generates HTML Schema meta tags for SEO purposes.
     * 
     * @return string The HTML meta tags.
    */
    public function getMeta(): string 
    {
        $meta = '<meta name="keywords" content="' . implode(', ', (array) static::getConfig("keywords")) . '">
            <meta name="description" content="' . static::getConfig("page_description") . '" />';
        
        if (!empty(static::getConfig("canonical"))) {
            $meta .= '<!-- Canonical link tag --> 
                <link rel="canonical" href="' . static::getConfig("canonical") . '" />';
        }

        if (static::getConfig("isArticle")) {
            $meta .= '<meta property="article:publisher" content="' . static::getConfig("company_name") . '" />
                <meta property="article:published_time" content="' . static::toDate(static::getConfig("published_date")) . '" />
                <meta property="article:modified_time" content="' . static::toDate(static::getConfig("modified_date")) . '" />';
        }

        $meta .= '<meta property="og:locale" content="' . static::getManifest('locale', 'en') . '" />
            <meta property="og:type" content="website" />
            <meta property="og:title" content="' . static::getConfig("title") . '" />
            <meta property="og:description" content="' . static::getConfig("page_description") . '" />
            <meta property="og:url" content="' . static::getConfig("link") . '" />
            <meta property="og:site_name" content="' . static::getConfig("name") . '" />
            <meta property="og:image" content="' . static::getConfig("image_assets") . static::getConfig("image_name") . '" />
            <meta property="og:image:width" content="' . static::getConfig("image_width") . '" />
            <meta property="og:image:height" content="' . static::getConfig("image_height") . '" />
            <meta property="og:image:type" content="' . static::getConfig("image_type") . '" />';
        
        $meta .= '<meta name="twitter:card" content="summary" />
            <meta name="twitter:site" content="@' . static::getManifest('twitter_name', '') . '" />
            <meta name="twitter:label1" content="Est. reading time" />
            <meta name="twitter:data1" content="37 minutes" />';

        return $meta;
    }

    /**
     * Generate structured data schema for SEO purposes.
     *
     * @return array<string, mixed> The structured data schema.
    */
    public function getSchema(): array
    {
        $breadcrumbs = (array) static::getConfig("breadcrumbs");
        $schema = [];

        array_unshift($breadcrumbs, [
            "link" => static::$link,
            "home" => true,
            "name" => "Home Page",
            "description" => static::getConfig("company_description"),
        ]);

        $breadcrumbs[] = [
            "link" => static::getConfig("link"),
            "name" => static::getConfig("title"),
            "description" => static::getConfig("page_description"),
        ];

        $schema["organisation"] = [
            "@type" => "Organization",
            "@id" => static::getManifest('site_id', '') . '/#organization',
            "name" => static::getConfig("company_name"),
            "url" => static::$link . '/',
            "brand" => static::getConfig("company_brands"),
            "duns" => static::getConfig("company_duns"),
            "email" => static::getConfig("company_email"),
            "sameAs" => (array) static::getManifest('social_media', []),
            "logo" => [
                "@type" => "ImageObject",
                "inLanguage" => static::getManifest('language', 'en'),
                "@id" => static::getManifest('site_id', '') . '/#logo',
                "url" => static::getConfig("image_assets") . static::getManifest('logo_image_name', ''),
                "contentUrl" => static::getConfig("image_assets") .static::getManifest('logo_image_name', ''),
                "width" => static::getManifest('logo_image_width', 0),
                "height" => static::getManifest('logo_image_height', 0),
                "caption" => static::getConfig("title")
            ],
            "image" => [
                "@id" => static::getManifest('site_id', '') . '/#logo'
            ],
            "address" => [
                "@type" => "PostalAddress",
                "addressLocality" => static::getConfig("address_locality"),
                "addressCountry" => static::getConfig("address_country"),
                "postalCode" => static::getConfig("address_postalcode"),
                "streetAddress" => static::getConfig("address_street")
            ]
        ];

        $schema["website"] = [
            "@type" => "WebSite",
            "@id" => static::getManifest('site_id', '') . '/#website',
            "url" => static::$link . '/',
            "name" => static::getConfig("name"),
            "description" => static::getConfig("company_description"),
            "publisher" => [
                "@id" => static::getManifest('site_id', '') . '/#organization'
            ],
            "potentialAction" => [
                [
                    "@type" => "SearchAction",
                    "target" => [
                        "@type" => "EntryPoint",
                        "urlTemplate" => static::$link . static::getConfig("search_query")
                    ],
                    "query-input" => 'required name=' . static::getConfig("search_input")
                ]
            ],
            "inLanguage" => static::getManifest('language', 'en'),
        ];

        $schema["webpage"] = [
            "@type" => "WebPage",
            "@id" => static::getConfig("link") . '/#webpage',
            "url" => static::getConfig("link"),
            "name" => static::getConfig("title"),
            "isPartOf" => [
                "@id" => static::getManifest('site_id', '') . '/#website'
            ],
            "about" => [
                "@id" => static::getConfig("link") . '/#about'
            ],
            "primaryImageOfPage" => [
                "@id" => static::getConfig("link") . '/#primaryimage'
            ],
            "image" => [
                "@id" => static::getConfig("link") . '/#primaryimage'
            ],
            "thumbnailUrl" => static::getConfig("image_assets") . static::getConfig("image_name"),
            "description" => static::getConfig("page_description"),
            "breadcrumb" => [
                "@id" => static::getConfig("link") . '/#breadcrumb'
            ],
            "inLanguage" => static::getManifest('language', 'en'),
            "potentialAction" => [
                "@type" => "ReadAction",
                "target" => [
                    static::getConfig("link")
                ]
            ]
        ];

        $schema["image"] = [
            "@type" => "ImageObject",
            "inLanguage" => static::getManifest('language', 'en'),
            "@id" => static::getConfig("link") . '/#primaryimage',
            "url" => static::getConfig("image_assets") . static::getConfig("image_name"),
            "contentUrl" => static::getConfig("image_assets") . static::getConfig("image_name"),
            "width" => static::getConfig("image_width"),
            "height" => static::getConfig("image_height")
        ];

        $schema["breadcrumb"] = [
            "@type" => "BreadcrumbList",
            "@id" => static::getConfig("link") . '/#breadcrumb',
            "itemListElement" => static::breadcrumbs($breadcrumbs)
        ];

        if (static::getConfig("isArticle")) {
            $authorId = kebab_case(static::getConfig("author"));

            $schema["article"] = [
                "@type" => static::getConfig("article_type"),
                "@id" => static::getConfig("link") . '/#article',
                "isPartOf" => [
                    "@id" => static::getConfig("link") . '/#webpage'
                ],
                "author" => [
                    "@type" => "Person",
                    "@id" => static::getManifest('site_id', '') . '#/schema/person/' .$authorId,
                    "name" => static::getConfig("author"),
                    "image" => [
                        "@type" => "ImageObject",
                        "@id" => static::getManifest('site_id', '') . '/author/' . $authorId . '/#personlogo',
                        "inLanguage" => static::getManifest('language', 'en'),
                        "url" => static::getConfig("image_assets") . "logo-square-dark.png",
                        "contentUrl" => static::getConfig("image_assets") . "logo-square-dark.png",
                        "caption" => static::getConfig("author")
                    ],
                    "url" => static::$link . '/author/' . $authorId
                ],
                "headline" => static::getConfig("headline"),
                "description" => static::getConfig("page_description"),
                "name" => static::getConfig("title"),
                "datePublished" => static::toDate(static::getConfig("datePublished")),
                "dateModified" => static::toDate(static::getConfig("modified_date")),
                "mainEntityOfPage" => [
                    "@id" => static::getConfig("link") . '/#webpage'
                ],
                "wordCount" => (int) static::getConfig("word_count"),
                "commentCount" => (int) static::getConfig("total_comments"),
                "publisher" => [
                    "@id" => static::getManifest('site_id', '') . '/#organization'
                ],
                "image" => [
                    "@id" => static::getConfig("link") . '/#primaryimage'
                ],
                "thumbnailUrl" => static::getConfig("image_assets") . static::getConfig("image_name"),
                "keywords" => (array) static::getConfig("article_keywords", []) + static::getConfig("keywords", []),
                "articleSection" => (array) static::getConfig("article_section", 'Blog'),
                "inLanguage" => static::getManifest('language', 'en'),
                "potentialAction" => [
                    [
                        "@type" => "CommentAction",
                        "name" => "Comment",
                        "target" => [static::getConfig("link") . '/#respond']
                    ]
                ],
                "copyrightYear" => date("Y", strtotime(static::getConfig("datePublished"))),
                "copyrightHolder" => [
                    "@id" => static::getManifest('site_id', '') . '/#organization'
                ]
            ];
        }

        if (static::getConfig("isProduct")) {
            $schema["product"] = [
                "@type" => static::getConfig("product_type"),
                "name" => static::getConfig("title"),
                "description" => static::getConfig("page_description"),
                "category" => static::getConfig("category"),
                "url" => static::getConfig("link"),
                "image" => static::getConfig("product_image_link"),
                "brand" => [
                    "@type" => "Brand",
                    "name" => static::getConfig("brand")
                ],
                "offers" => [
                    "@type" => "Offer",
                    "priceCurrency" => static::getConfig("currency"),
                    "price" => static::getConfig("price"),
                    "availability" => static::getConfig("availability")
                ]
            ];
        }

        $finalSchema = [
            "@context" => "https://schema.org",
            "@graph" => [
                array_values($schema)
            ]
        ];

        return $finalSchema;
    }

    /**
     * Create and return breadcrumb list array 
     * 
     * @param array $breadcrumbs
     * 
     * @return array<int, mixed>
    */
    private static function breadcrumbs(array $breadcrumbs): array
    {
        $itemListElement = [];

        foreach ($breadcrumbs as $index => $page) {
            $item = [
                "@type" => "ListItem",
                "position" => $index + 1
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
                "@type" => "WebPage",
                "@id" => $page['link'] . '/#webpage',
                "name" => $page['name'],
                "description" => $page['description'] ?? static::getConfig("company_description"),
                "url" => $page['link']
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
        static::$defaultConfig = [
            "link" => static::getManifest('start_url'),
            "canonical" => static::$link,
            "breadcrumbs" => [],
            'image_assets' => static::getManifest('image_assets'),
            'name' => static::getManifest('name'),
            'company_brands' => static::getManifest('company_brands', [static::getManifest('name')]),
            'company_duns'  => static::getManifest('duns'),
            "company_name" => static::getManifest('company_name'),
            "company_email" => static::getManifest('company_email'),
            "company_description" => static::getManifest('company_description'),
            "address_locality" => static::getManifest('address_locality', ''),
            "address_country" => static::getManifest('address_country', ''),
            "address_postalcode" => static::getManifest('address_postalcode', ''),
            "address_street" => static::getManifest('address_street', ''),
            "page_description" => static::getManifest('page_description'),
            "title" => static::getManifest('title'),
            "headline" => static::getManifest('title'),
            "image_name" =>static::getManifest('image_name'),
            "image_width" => static::getManifest('image_width'),
            "image_height" => static::getManifest('image_height'),
            "image_type" => static::getManifest('image_type'),
            "site_published_date" => null,
            "site_modified_date" => null,
            "published_date" => static::getManifest('published_date'),
            "modified_date" => static::getManifest('modified_date'),
            "keywords" => static::getManifest('keywords', ''),
            "isArticle" => false,
            "article_type" => "Article",
            "article_keywords" => [],
            "article_section" => [],
            "word_count" => 1500,
            "total_comments" => 0,
            "author" => "Author Name",
            "isProduct" => false,
            "product_type" => "Product",
            "product_image_link" => '',
            "product_category" => "Electronics",
            "twitter_name" => static::getManifest('twitter_name', ''),
            "search_query" => "/search?q={search_term_string}",
            "search_input" => "search_term_string",
            "availability" => "InStock",
            "currency" => "NGN",
            "price" => "0.00",
            "brand" => ''
        ];
    }

    /**
     * Get item from manifest
     * 
     * @param string $key Key of the item.
     * @param mixed $default Default
     * 
     * @return mixed
    */
    private static function getManifest(string $key, mixed $default = null): mixed
    {
        return static::$manifest[$key]??$default;
    }
    
    /**
     * Checks if a URL has query parameters.
     *
     * @param string $url The URL to check.
     * 
     * @return bool True if the URL has query parameters, otherwise false.
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
     * @return array The manifest content as an object.
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