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

class Meta
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
    private static ?Meta $instance = null;

     /**
     * Initialize constructor
     * 
     */
    public function __construct()
    {
        static::$manifest ??= static::loadMeta();
        static::$link = APP_URL;
        static::loadDefaultConfig();
    }

    /**
     * Singleton class
     * 
     * @return static $instance 
     */
    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Sets the link URL for the web page.
     *
     * @param string $link The link URL.
     * 
     * @return self
     */
    public function setLink(string $link): self
    {
        static::$defaultConfig["link"] = $link;

        return $this;
    }

     /**
     * Sets the configuration for the Meta instance.
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
        $this->setPageTitle($title);
    }

     /**
     * Sets the canonical URL for SEO purposes.
     *
     * @param string $canonical The canonical URL.
     * 
     * @return void
     */
    public function setCanonical(string $canonical): void
    {
        static::$defaultConfig["canonical"] = $canonical;
        static::$defaultConfig["link"] = $canonical;
    }

    /**
     * Sets the canonical version of the URL for SEO purposes.
     *
     * @param string $link The link URL.
     * @param string $view The view URL.
     * 
     * @return void
     */
    public function setCanonicalVersion(string $link, string $view): void
    {
        static::$defaultConfig["canonical"] = $link . $view;
        static::$defaultConfig["link"] = $link . $view;
    }

     /**
     * Sets the page title for SEO purposes.
     *
     * @param string $title The page title.
     * @return void
     */
    public function setPageTitle(string $title): void
    {
        if (strpos($title, "| " . APP_NAME) === false) {
            static::$defaultConfig["title"] = "{$title} | " . APP_NAME;
        } else {
            static::$defaultConfig["title"] = $title;
        }
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
        $dateTime = Time::parse($date)->format('Y-m-d\TH:i:sP');

        return $dateTime;
    }

    /**
     * Retrieves a configuration value by key and appends a query string if necessary.
     *
     * @param string $key The key of the configuration value.
     * @return mixed The configured value with an optional query string.
     */
    private static function getConfig(string $key): mixed
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
    
        if($key == "image_assets"){
            return "{$value}/";
        }
        return $value;
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
        return (in_array($key, ["link", "canonical"]) && !static::has_query_parameter($param) && !empty(static::getQuery()));
    }

    /**
     * Retrieves the query string from the current request URI.
     *
     * @return ?string The query string or null if not present.
     */
    private static function getQuery(): ?string 
    {
        $queryString = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);

        return $queryString;
    }

    /**
     * Converts the schema data to JSON format.
     *
     * @return string The JSON representation of the schema data.
     */
    public static function toJson(): string 
    {
        return json_encode(static::generateScheme(), JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Loads the default configuration values for SEO meta data.
     *
     * @return void
     */
    private static function loadDefaultConfig(): void
    {
        static::$defaultConfig = [
            "link" => static::$manifest['start_url'],
            "canonical" => static::$link,
            "previous_page" => "",
            'image_assets' => static::$manifest['image_assets'],
            'company' => "Company",
            "company_name" => static::$manifest['company_name'],
            "description" => static::$manifest['description'],
            "company_description" => static::$manifest['company_description'],
            "title" => static::$manifest['title'],
            "caption" => static::$manifest['title'],
            "image_name" => static::$manifest['image_name'],
            "image_width" => static::$manifest['image_width'],
            "image_height" => static::$manifest['image_height'],
            "image_type" => static::$manifest['image_type'],
            "datePublished" => static::$manifest['datePublished'],
            "dateModified" => static::$manifest['dateModified'],
            "keywords" => static::$manifest['keywords'],
            "isArticle" => false,
            "isProduct" => false,
            "article_keywords" => [],
            "article_category" => "",
            "author" => "Author Name",
            "twitter_name" => static::$manifest['twitter_name'],
            "image_link" => '',
            "category" => "Electronics",
            "availability" => "InStock",
            "currency" => "USD",
            "price" => "0.00",
            "brand" => ''
        ];
    }

    /**
     * Generate structured data schema for SEO purposes.
     *
     * @return array The structured data schema.
     */
    public static function generateScheme(): array
    {
        $previousPage = strtolower(static::getConfig("previous_page"));
        
        $schema = [
            "@context" => "https://schema.org",
            "@graph" => [
                [
                    "@type" => "Organization",
                    "@id" => static::$manifest['site_id'] . '/#organization',
                    "name" => static::getConfig("company"),
                    "url" => static::$link . '/',
                    "sameAs" => (array) static::$manifest['social_media'],
                    "logo" => [
                        "@type" => "ImageObject",
                        "inLanguage" => static::$manifest['language'],
                        "@id" => static::$manifest['site_id'] . '/#logo',
                        "url" => static::getConfig("image_assets") . static::$manifest['logo_image_name'],
                        "contentUrl" => static::getConfig("image_assets") . static::$manifest['logo_image_name'],
                        "width" => static::$manifest['logo_image_width'],
                        "height" => static::$manifest['logo_image_height'],
                        "caption" => static::getConfig("caption")
                    ],
                    "image" => [
                        "@id" => static::$manifest['site_id'] . '/#logo'
                    ]
                ],
                [
                    "@type" => "WebSite",
                    "@id" => static::$manifest['site_id'] . '/#website',
                    "url" => static::$link . '/',
                    "name" => static::getConfig("company"),
                    "description" => static::getConfig("company_description"),
                    "publisher" => [
                        "@id" => static::$manifest['site_id'] . '/#organization'
                    ],
                    "potentialAction" => [
                        [
                            "@type" => "SearchAction",
                            "target" => [
                                "@type" => "EntryPoint",
                                "urlTemplate" => static::$link . '/?s={search_term_string}'
                            ],
                            "query-input" => 'required name=search_term_string'
                        ]
                    ],
                    "inLanguage" => static::$manifest['language'],
                ],
                [
                    "@type" => "WebPage",
                    "@id" => static::getConfig("link") . '/#webpage',
                    "url" => static::getConfig("link"),
                    "name" => static::getConfig("title"),
                    "isPartOf" => [
                        "@id" => static::$manifest['site_id'] . '/#website'
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
                    "datePublished" => static::toDate(static::getConfig("datePublished")),
                    "dateModified" => static::toDate(static::getConfig("dateModified")),
                    "description" => static::getConfig("description"),
                    "breadcrumb" => [
                        "@id" => static::getConfig("link") . '/#breadcrumb'
                    ],
                    "inLanguage" => static::$manifest['language'],
                    "potentialAction" => [
                        "@type" => "ReadAction",
                        "target" => [
                            static::getConfig("link")
                        ]
                    ]
                ],
                [
                    "@type" => "ImageObject",
                    "inLanguage" => static::$manifest['language'],
                    "@id" => static::getConfig("link") . '/#primaryimage',
                    "url" => static::getConfig("image_assets") . static::getConfig("image_name"),
                    "contentUrl" => static::getConfig("image_assets") . static::getConfig("image_name"),
                    "width" => static::getConfig("image_width"),
                    "height" => static::getConfig("image_height")
                ],
                [
                    "@type" => "BreadcrumbList",
                    "@id" => static::getConfig("link") . '/#breadcrumb',
                    "itemListElement" => [
                        [
                            "@type" => "ListItem",
                            "position" => 1,
                            // "nextItem" => static::$link . static::getConfig("previous_page") . "/#listItem",
                            "item" => [
                                "@type" => "WebPage",
                                "@id" => static::$manifest['site_id'],
                                "name" => "Home",
                                "description" => static::getConfig("company_description"),
                                "url" => static::$link
                            ]
                        ]
                    ]
                ]
            ]
        ];

        if (static::getConfig("isArticle")) {
            if (!empty(static::getConfig("previous_page"))) {
                $schema["@graph"][4]["itemListElement"][] = [
                    "@type" => "ListItem",
                    "position" => 2,
                    "nextItem" => static::getConfig("link") . '/#listItem',
                    "previousItem" => static::$manifest['site_id'] . "/#listItem",
                    "item" => [
                        "@type" => "WebPage",
                        "@id" => static::$manifest['site_id'] . '/' . $previousPage,
                        "name" => ucfirst($previousPage),
                        "description" => static::getConfig("company_description"),
                        "url" => static::$link . '/' . $previousPage
                    ]
                ];
            }
            $authorId = kebab_case(static::getConfig("author"));

            $schema["@graph"][] = [
                "@type" => "Article",
                "@id" => static::getConfig("link") . '/#article',
                "isPartOf" => [
                    "@id" => static::getConfig("link") . '/#webpage'
                ],
                "author" => [
                    "@type" => "Person",
                    "@id" => static::$manifest['site_id'] . '#/schema/person/' .$authorId,
                    "name" => static::getConfig("author"),
                    "image" => [
                        "@type" => "ImageObject",
                        "@id" => static::$manifest['site_id'] . '/author/' . $authorId . '/#personlogo',
                        "inLanguage" => static::$manifest['language'],
                        "url" => static::getConfig("image_assets") . "logo-square-dark.png",
                        "contentUrl" => static::getConfig("image_assets") . "logo-square-dark.png",
                        "caption" => static::getConfig("author")
                    ],
                    "url" => static::$link . '/author/' . $authorId
                ],
                "headline" => static::getConfig("title"),
                "datePublished" => static::toDate(static::getConfig("datePublished")),
                "dateModified" => static::toDate(static::getConfig("dateModified")),
                "mainEntityOfPage" => [
                    "@id" => static::getConfig("link") . '/#webpage'
                ],
                "wordCount" => 7279,
                "commentCount" => 0,
                "publisher" => [
                    "@id" => static::$manifest['site_id'] . '/#organization'
                ],
                "image" => [
                    "@id" => static::getConfig("link") . '/#primaryimage'
                ],
                "thumbnailUrl" => static::getConfig("image_assets") . static::getConfig("image_name"),
                "keywords" => static::getConfig("article_keywords"),
                "articleSection" => [static::getConfig("article_category")],
                "inLanguage" => static::$manifest['language'],
                "potentialAction" => [
                    [
                        "@type" => "CommentAction",
                        "name" => "Comment",
                        "target" => [static::getConfig("link") . '/#respond']
                    ]
                ],
                "copyrightYear" => date("Y", strtotime(static::getConfig("datePublished"))),
                "copyrightHolder" => [
                    "@id" => static::$manifest['site_id'] . '/#organization'
                ]
            ];
        }

        $schema["@graph"][4]["itemListElement"][] = [
            "@type" => "ListItem",
            "position" => count($schema["@graph"][4]["itemListElement"]) + 1,
            "previousItem" => static::$link . "/{$previousPage}/#listItem",
            "item" => [
                "@type" => "WebPage",
                "@id" => static::getConfig("link"),
                "name" => static::getConfig("title"),
                "description" => static::getConfig("description"),
                "url" => static::getConfig("link")
            ]
        ];

        if (static::getConfig("isProduct")) {
            $schema["@graph"][count($schema["@graph"]) + 1] = [
                "@type" => "Product",
                "name" => static::getConfig("title"),
                "description" => static::getConfig("description"),
                "category" => static::getConfig("category"),
                "url" => static::getConfig("link"),
                "image" => static::getConfig("image_link"),
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

        return $schema;
    }

    /**
     * Generates HTML meta tags for SEO purposes.
     *
     * @return string The HTML meta tags.
     */
    public function getMetaTags(): string 
    {
        $meta = '<meta name="keywords" content="' . static::getConfig("keywords") . '">
            <meta name="description" content="' . static::getConfig("description") . '" />';

        if (!empty(static::getConfig("canonical"))) {
            $meta .= '<link rel="canonical" href="' . static::getConfig("canonical") . '" />';
        }

        if (static::getConfig("isArticle")) {
            $meta .= '<meta property="article:publisher" content="' . static::getConfig("company") . '" />
                <meta property="article:published_time" content="' . static::toDate(static::getConfig("datePublished")) . '" />
                <meta property="article:modified_time" content="' . static::toDate(static::getConfig("dateModified")) . '" />';
        }

        $meta .= '<meta property="og:locale" content="' . static::$manifest['locale'] . '" />
            <meta property="og:type" content="website" />
            <meta property="og:title" content="' . static::getConfig("title") . '" />
            <meta property="og:description" content="' . static::getConfig("description") . '" />
            <meta property="og:url" content="' . static::getConfig("link") . '" />
            <meta property="og:site_name" content="' . static::getConfig("company_name") . '" />
            <meta property="og:image" content="' . static::getConfig("image_assets") . static::getConfig("image_name") . '" />
            <meta property="og:image:width" content="' . static::getConfig("image_width") . '" />
            <meta property="og:image:height" content="' . static::getConfig("image_height") . '" />
            <meta property="og:image:type" content="' . static::getConfig("image_type") . '" />
            <meta name="twitter:card" content="summary" />
            <meta name="twitter:site" content="@' . static::$manifest['twitter_name'] . '" />
            <meta name="twitter:label1" content="Est. reading time" />
            <meta name="twitter:data1" content="37 minutes" />';

        return $meta;
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
        if (strpos($url, '?') === false) {
            return false;
        }
        $path_and_query = explode('?', $url);

        if ($path_and_query[1] === '') {
            return false;
        }

        return true;
    }

    /**
     * Gets the HTML code for embedding the schema in a web page.
     *
     * @return string The HTML code for embedding the schema.
     */
    public function getObjectGraph(): string 
    {
        return '<script type="application/ld+json">' . static::toJson() . '</script>';
    }

    /**
     * Reads the manifest file and returns its content as an object.
     * 
     * @return array The manifest content as an object.
     */
    private static function loadMeta(): array
    {
        $path = path('controllers') . 'Config' . DIRECTORY_SEPARATOR . 'Meta.php';
    
        if (file_exists($path)) {
            return require $path;
        }
    
        return [];
    }
}