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
    private string $link = '';

    /**
     * @var object $manifest meta object json.
    */
    private ?object $manifest = null;

    /**
     * @var array $defaultConfig default configuration
    */
    private array $defaultConfig = [];

    /**
     * @var array $extendedConfig user passed configurations
    */
    private array $extendedConfig = [];

    /**
     * @var string $appName application name
    */
    private $appName = '';

     /**
     * @var static $instance class static singleton instance
    */
    private static $instance = null;

    /**
     * Meta constructor.
    */
    public function __construct()
    {
    }

    /**
     * Singleton class
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
     * Create object
     *
     * @param string $appName The name of the application.
     * @param string $rootDir The root directory of the application.
     * @param string $baseUrl The base URL of the application.
     */
    public function create(string $appName, string $rootDir, string $baseUrl): void
    {
        $this->manifest = $this->readManifest($rootDir);
        $this->appName = $appName;
        $this->link = $baseUrl;
        $this->loadDefaultConfig();
    }

     /**
     * Sets the link URL for the web page.
     *
     * @param string $link The link URL.
     * @return self
     */
    public function setLink(string $link): self{
        $this->defaultConfig["link"] = $link;
        return $this;
    }

     /**
     * Sets the configuration for the Meta instance.
     *
     * @param array $config The extended configuration.
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->extendedConfig = $config;
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
     * @return void
     */
    public function setCanonical(string $canonical): void
    {
        $this->defaultConfig["canonical"] = $canonical;
        $this->defaultConfig["link"] = $canonical;
    }

    /**
     * Sets the canonical version of the URL for SEO purposes.
     *
     * @param string $link The link URL.
     * @param string $view The view URL.
     * @return void
     */
    public function setCanonicalVersion(string $link, string $view): void
    {
        $this->defaultConfig["canonical"] = $link . $view;
        $this->defaultConfig["link"] = $link . $view;
    }

     /**
     * Sets the page title for SEO purposes.
     *
     * @param string $title The page title.
     * @return void
     */
    public function setPageTitle(string $title): void
    {
        if (strpos($title, "| {$this->appName}") === false) {
            $this->defaultConfig["title"] = "{$title} | {$this->appName}";
        } else {
            $this->defaultConfig["title"] = $title;
        }
    }

    /**
     * Converts a string to kebab case.
     *
     * @param string $string The input string.
     * @return string The kebab-cased string.
     */
    private function toKebabCase(string $string): string 
    {
        return kebab_case($string);
    }

    /**
     * Converts a date string to ISO 8601 format.
     *
     * @param string $date The input date string.
     * 
     * @return string The date in ISO 8601 format.
     */
    private function toDate(string $date): string
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
    private function getConfig(string $key): mixed
    {
        $config = array_replace($this->defaultConfig, array_filter($this->extendedConfig));
        
        $param = $config[$key] ?? '';
        if(is_array($param)){
            $value = $param;
        }else{
            if($this->shouldAddParam($key, $param)){
                $param .= "?{$this->getQuery()}";
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
    private function shouldAddParam(string $key, string $param): bool 
    {
        return (in_array($key, ["link", "canonical"]) && !$this->has_query_parameter($param) && !empty($this->getQuery()));
    }

    /**
     * Retrieves the query string from the current request URI.
     *
     * @return ?string The query string or null if not present.
     */
    private function getQuery(): ?string 
    {
        $queryString = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        return $queryString;
    }

    /**
     * Converts the schema data to JSON format.
     *
     * @return string The JSON representation of the schema data.
     */
    public function toJson(): string 
    {
        return json_encode($this->generateScheme(), JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Loads the default configuration values for SEO meta data.
     *
     * @return void
     */
    private function loadDefaultConfig(): void
    {
        $this->defaultConfig = [
            "link" => $this->manifest->start_url,
            "canonical" => $this->link,
            "previous_page" => "",
            'image_assets' => $this->manifest->image_assets,
            'company' => "Company",
            "company_name" => $this->manifest->company_name,
            "description" => $this->manifest->description,
            "company_description" => $this->manifest->company_description,
            "title" => $this->manifest->title,
            "caption" => $this->manifest->title,
            "image_name" => $this->manifest->image_name,
            "image_width" => $this->manifest->image_width,
            "image_height" => $this->manifest->image_height,
            "image_type" => $this->manifest->image_type,
            "datePublished" => $this->manifest->datePublished,
            "dateModified" => $this->manifest->dateModified,
            "keywords" => $this->manifest->keywords,
            "isArticle" => false,
            "isProduct" => false,
            "article_keywords" => [],
            "article_category" => "",
            "author" => "Author Name",
            "twitter_name" => $this->manifest->twitter_name,
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
    public function generateScheme(): array
    {
        $previousPage = strtolower($this->getConfig("previous_page"));
        
        $schema = [
            "@context" => "https://schema.org",
            "@graph" => [
                [
                    "@type" => "Organization",
                    "@id" => "{$this->manifest->site_id}/#organization",
                    "name" => $this->getConfig("company"),
                    "url" => "{$this->link}/",
                    "sameAs" => (array) $this->manifest->social_media,
                    "logo" => [
                        "@type" => "ImageObject",
                        "inLanguage" => $this->manifest->locale,
                        "@id" => "{$this->manifest->site_id}/#logo",
                        "url" => $this->getConfig("image_assets") . $this->manifest->logo_image_name,
                        "contentUrl" => $this->getConfig("image_assets") . $this->manifest->logo_image_name,
                        "width" => $this->manifest->logo_image_width,
                        "height" => $this->manifest->logo_image_height,
                        "caption" => $this->getConfig("caption")
                    ],
                    "image" => [
                        "@id" => "{$this->manifest->site_id}/#logo"
                    ]
                ],
                [
                    "@type" => "WebSite",
                    "@id" => "{$this->manifest->site_id}/#website",
                    "url" => "{$this->link}/",
                    "name" => $this->getConfig("company"),
                    "description" => $this->getConfig("company_description"),
                    "publisher" => [
                        "@id" => "{$this->manifest->site_id}/#organization"
                    ],
                    "potentialAction" => [
                        [
                            "@type" => "SearchAction",
                            "target" => [
                                "@type" => "EntryPoint",
                                "urlTemplate" => "{$this->link}/?s={search_term_string}"
                            ],
                            "query-input" => "required name=search_term_string"
                        ]
                    ],
                    "inLanguage" => $this->manifest->locale
                ],
                [
                    "@type" => "WebPage",
                    "@id" => "{$this->getConfig("link")}/#webpage",
                    "url" => $this->getConfig("link"),
                    "name" => $this->getConfig("title"),
                    "isPartOf" => [
                        "@id" => "{$this->manifest->site_id}/#website"
                    ],
                    "about" => [
                        "@id" => "{$this->getConfig("link")}/#about"
                    ],
                    "primaryImageOfPage" => [
                        "@id" => "{$this->getConfig("link")}/#primaryimage"
                    ],
                    "image" => [
                        "@id" => "{$this->getConfig("link")}/#primaryimage"
                    ],
                    "thumbnailUrl" => $this->getConfig("image_assets") . $this->getConfig("image_name"),
                    "datePublished" => $this->toDate($this->getConfig("datePublished")),
                    "dateModified" => $this->toDate($this->getConfig("dateModified")),
                    "description" => $this->getConfig("description"),
                    "breadcrumb" => [
                        "@id" => "{$this->getConfig("link")}/#breadcrumb"
                    ],
                    "inLanguage" => $this->manifest->locale,
                    "potentialAction" => [
                        "@type" => "ReadAction",
                        "target" => [
                            $this->getConfig("link")
                        ]
                    ]
                ],
                [
                    "@type" => "ImageObject",
                    "inLanguage" => $this->manifest->locale,
                    "@id" => "{$this->getConfig("link")}/#primaryimage",
                    "url" => $this->getConfig("image_assets") . $this->getConfig("image_name"),
                    "contentUrl" => $this->getConfig("image_assets") . $this->getConfig("image_name"),
                    "width" => $this->getConfig("image_width"),
                    "height" => $this->getConfig("image_height")
                ],
                [
                    "@type" => "BreadcrumbList",
                    "@id" => "{$this->getConfig("link")}/#breadcrumb",
                    "itemListElement" => [
                        [
                            "@type" => "ListItem",
                            "position" => 1,
                            // "nextItem" => $this->link . $this->getConfig("previous_page") . "/#listItem",
                            "item" => [
                                "@type" => "WebPage",
                                "@id" => $this->manifest->site_id,
                                "name" => "Home",
                                "description" => $this->getConfig("company_description"),
                                "url" => $this->link
                            ]
                        ]
                    ]
                ]
            ]
        ];

        if ($this->getConfig("isArticle")) {
            if (!empty($this->getConfig("previous_page"))) {
                $schema["@graph"][4]["itemListElement"][] = [
                    "@type" => "ListItem",
                    "position" => 2,
                    "nextItem" => "{$this->getConfig("link")}/#listItem",
                    "previousItem" => "{$this->manifest->site_id}/#listItem",
                    "item" => [
                        "@type" => "WebPage",
                        "@id" => "{$this->manifest->site_id}/" . $previousPage,
                        "name" => ucfirst($previousPage),
                        "description" => $this->getConfig("company_description"),
                        "url" => "{$this->link}/" . $previousPage
                    ]
                ];
            }
            $schema["@graph"][] = [
                "@type" => "Article",
                "@id" => "{$this->getConfig("link")}/#article",
                "isPartOf" => [
                    "@id" => "{$this->getConfig("link")}/#webpage"
                ],
                "author" => [
                    "@type" => "Person",
                    "@id" => "{$this->manifest->site_id}/#/schema/person/{$this->toKebabCase($this->getConfig("author"))}",
                    "name" => $this->getConfig("author"),
                    "image" => [
                        "@type" => "ImageObject",
                        "@id" => "{$this->manifest->site_id}/author/{$this->toKebabCase($this->getConfig("author"))}/#personlogo",
                        "inLanguage" => $this->manifest->locale,
                        "url" => $this->getConfig("image_assets") . "logo-square-dark.png",
                        "contentUrl" => $this->getConfig("image_assets") . "logo-square-dark.png",
                        "caption" => $this->getConfig("author")
                    ],
                    "url" => "{$this->link}/author/{$this->toKebabCase($this->getConfig("author"))}"
                ],
                "headline" => $this->getConfig("title"),
                "datePublished" => $this->toDate($this->getConfig("datePublished")),
                "dateModified" => $this->toDate($this->getConfig("dateModified")),
                "mainEntityOfPage" => [
                    "@id" => "{$this->getConfig("link")}/#webpage"
                ],
                "wordCount" => 7279,
                "commentCount" => 0,
                "publisher" => [
                    "@id" => "{$this->manifest->site_id}/#organization"
                ],
                "image" => [
                    "@id" => "{$this->getConfig("link")}/#primaryimage"
                ],
                "thumbnailUrl" => $this->getConfig("image_assets") . $this->getConfig("image_name"),
                "keywords" => $this->getConfig("article_keywords"),
                "articleSection" => [$this->getConfig("article_category")],
                "inLanguage" => $this->manifest->locale,
                "potentialAction" => [
                    [
                        "@type" => "CommentAction",
                        "name" => "Comment",
                        "target" => ["{$this->getConfig("link")}/#respond"]
                    ]
                ],
                "copyrightYear" => date("Y", strtotime($this->getConfig("datePublished"))),
                "copyrightHolder" => [
                    "@id" => "{$this->manifest->site_id}/#organization"
                ]
            ];
        }

        $schema["@graph"][4]["itemListElement"][] = [
            "@type" => "ListItem",
            "position" => count($schema["@graph"][4]["itemListElement"]) + 1,
            "previousItem" => "{$this->link}/{$previousPage}/#listItem",
            "item" => [
                "@type" => "WebPage",
                "@id" => $this->getConfig("link"),
                "name" => $this->getConfig("title"),
                "description" => $this->getConfig("description"),
                "url" => $this->getConfig("link")
            ]
        ];

        if ($this->getConfig("isProduct")) {
            $schema["@graph"][count($schema["@graph"]) + 1] = [
                "@type" => "Product",
                "name" => $this->getConfig("title"),
                "description" => $this->getConfig("description"),
                "category" => $this->getConfig("category"),
                "url" => $this->getConfig("link"),
                "image" => $this->getConfig("image_link"),
                "brand" => [
                    "@type" => "Brand",
                    "name" => $this->getConfig("brand")
                ],
                "offers" => [
                    "@type" => "Offer",
                    "priceCurrency" => $this->getConfig("currency"),
                    "price" => $this->getConfig("price"),
                    "availability" => $this->getConfig("availability")
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
        $meta = '<meta name="keywords" content="' . $this->getConfig("keywords") . '">
            <meta name="description" content="' . $this->getConfig("description") . '" />';

        if (!empty($this->getConfig("canonical"))) {
            $meta .= '<link rel="canonical" href="' . $this->getConfig("canonical") . '" />';
        }

        if ($this->getConfig("isArticle")) {
            $meta .= '<meta property="article:publisher" content="' . $this->getConfig("company") . '" />
                <meta property="article:published_time" content="' . $this->toDate($this->getConfig("datePublished")) . '" />
                <meta property="article:modified_time" content="' . $this->toDate($this->getConfig("dateModified")) . '" />';
        }

        $meta .= '<meta property="og:locale" content="' . $this->manifest->facebook_local . '" />
            <meta property="og:type" content="website" />
            <meta property="og:title" content="' . $this->getConfig("title") . '" />
            <meta property="og:description" content="' . $this->getConfig("description") . '" />
            <meta property="og:url" content="' . $this->getConfig("link") . '" />
            <meta property="og:site_name" content="' . $this->getConfig("company_name") . '" />
            <meta property="og:image" content="' . $this->getConfig("image_assets") . $this->getConfig("image_name") . '" />
            <meta property="og:image:width" content="' . $this->getConfig("image_width") . '" />
            <meta property="og:image:height" content="' . $this->getConfig("image_height") . '" />
            <meta property="og:image:type" content="' . $this->getConfig("image_type") . '" />
            <meta name="twitter:card" content="summary" />
            <meta name="twitter:site" content="@' . $this->manifest->twitter_name . '" />
            <meta name="twitter:label1" content="Est. reading time" />
            <meta name="twitter:data1" content="37 minutes" />';

        return $meta;
    }

    
    /**
     * Checks if a URL has query parameters.
     *
     * @param string $url The URL to check.
     * @return bool True if the URL has query parameters, otherwise false.
     */
    private function has_query_parameter(string $url): bool {
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
        return '<script type="application/ld+json">' . $this->toJson() . '</script>';
    }

    /**
     * Reads the manifest file and returns its content as an object.
     *
     * @param string $rootDir The root directory.
     * @return object The manifest content as an object.
     */
    private function readManifest($rootDir): object
    {
        $jsonString = file_get_contents($rootDir . DIRECTORY_SEPARATOR . 'meta.config.json');
        $config = json_decode($jsonString);

        if ($config === null) {
            return (object)[];
        }
        return $config;
    }
}
