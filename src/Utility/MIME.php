<?php
/**
 * Luminova Framework MIME types.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utility;

use \finfo;
use \Throwable;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\InvalidArgumentException;

final class MIME
{
    /**
     * File info object.
     *
     * @var finfo|null
     */
    private static ?finfo $finfo = null;

    /**
     * Custom MIME database files.
     *
     * @var array<string,bool> $files
     */
    private static array $files = [];

    /**
     * Custom magic database file path.
     *
     * @var string|null $magicDatabase
     */
    private static ?string $magicDatabase = null;

    /**
     * Internal MIME type database.
     *
     * @var array<string,string> $db
     */
    private static array $db = [
        // Text
        'txt'   => 'text/plain',
        'text'  => 'text/plain',
        'csv'   => 'text/csv',
        'html'  => 'text/html',
        'css'   => 'text/css',
        'md'    => 'text/markdown',
        'ics'   => 'text/calendar',
        'vcard' => 'text/vcard',
        'vcs'   => 'text/vcard',

        // Other Text (structured / scriptable)
        'json'   => 'application/json',
        'har'    => 'application/json',
        'jsonld' => 'application/ld+json',
        'xml'    => 'application/xml',
        'xhtml'  => 'application/xhtml+xml',
        'xsl'    => 'application/xslt+xml',
        'xsd'    => 'application/xml',
        'rdf'    => 'application/rdf+xml',
        'atom'   => 'application/atom+xml',
        'rss'    => 'application/rss+xml',

        // Images
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'bmp'  => 'image/bmp',
        'svg'  => 'image/svg+xml',
        'tiff' => 'image/tiff',
        'avif' => 'image/avif',
        'ico'  => 'image/x-icon',
        'pjp'  => 'image/jpeg',
        'jfif' => 'image/jpeg',
        'apng' => 'image/png',
        'pjpeg' => 'image/jpeg',

        // Documents
        'pdf'  => 'application/pdf',
        'rtf'  => 'application/rtf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

        // Audio
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',

        // Video
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'avi'  => 'video/x-msvideo',
        'mkv'  => 'video/x-matroska',
        'mov'  => 'video/quicktime',

        // Archives
        'zip'  => 'application/zip',
        'zipx' => 'application/x-zip-compressed',
        '7z'   => 'application/x-7z-compressed',
        'tar'  => 'application/x-tar',
        'tar.gz' => 'application/gzip',
        'tgz'  => 'application/gzip',
        'gz'   => 'application/gzip',
        'bz2'  => 'application/x-bzip2',
        'cab'  => 'application/vnd.ms-cab-compressed',
        'rar'  => 'application/x-rar-compressed',
        'ace'  => 'application/x-ace-compressed',
        'pea'  => 'application/x-pea',
        'zpaq' => 'application/x-zpaq',
        'paq'  => 'application/x-paq',

        // App / Runtime Packages
        'apk' => 'application/vnd.android.package-archive',
        'jar' => 'application/java-archive',
        'war' => 'application/java-archive',
        'aar' => 'application/java-archive',

        // Disk / Installer Images
        'psd'  => 'image/vnd.adobe.photoshop',
        'iso'  => 'application/x-iso9660-image',
        'dmg'  => 'application/x-apple-diskimage',

        // Executables / Scripts
        'exe' => 'application/x-msdownload',
        'dll' => 'application/x-msdownload',
        'dos' => 'application/x-dosexec',
        'sh'  => 'application/x-sh',

        // Programming Languages (Source Code)
        'php'   => 'application/x-php',
        'php8'  => 'application/x-php',
        'js'    => 'application/javascript',
        'ts'    => 'application/typescript',
        'sh'    => 'application/x-sh',
        'bash'  => 'application/x-sh',
        'sql'   => 'application/sql',
        'toml'  => 'application/toml',
        'bat'   => 'application/x-msdos-program',
        'cmd'   => 'application/x-msdos-program',
        'c'     => 'text/x-c',
        'h'     => 'text/x-c',
        'cpp'   => 'text/x-c++',
        'hpp'   => 'text/x-c++',
        'java'  => 'text/x-java-source',
        'kt'    => 'text/x-kotlin',
        'scala' => 'text/x-scala',
        'cs'    => 'text/plain',
        'go'    => 'text/x-go',
        'rs'    => 'text/x-rust',
        'py'    => 'text/x-python',
        'rb'    => 'text/x-ruby',
        'pl'    => 'text/x-perl',
        'swift' => 'text/x-swift',
        'dart'  => 'text/x-dart',
        'lua'   => 'text/x-lua',
        'r'     => 'text/plain',
        'ps1'   => 'text/plain',
        'asm'   => 'text/plain',
        'yaml'  => 'text/yaml',
        'yml'   => 'text/yaml',
        'ini'   => 'text/plain',
        'conf'  => 'text/plain',

        // Binary / Misc
        'bin'          => 'application/octet-stream',
        'dat'          => 'application/octet-stream',
        'octet-stream' => 'application/octet-stream',

        // Forms / Multipart
        'multipart' => 'multipart/form-data',
        'form'      => 'application/x-www-form-urlencoded',
    ];

    /**
     * MIME type.
     *
     * @var string|null
     */
    private ?string $type = null;

    /**
     * MIME extension type.
     *
     * @var string|null
     */
    private ?string $extension = null;

    /**
     * Constructor to initialize MIME type detection.
     *
     * @param string|resource $source The file path MIME string or data string to analyze.
     * @param string|null $mimeDatabase Optional path to MIME magic or custom database file.
     * 
     * @example - Examples:
     * ```php
     * // Create a new MIME instance for a file
     * $mime = new MIME('/path/to/file.jpg');
     * 
     * // Get the MIME type of the file
     * echo $mime->getType(); // Output: image/jpeg
     * 
     * // Get the file extension
     * echo $mime->getExtension(); // Output: jpg
     * 
     * // Check if the file is an image
     * var_dump($mime->isImage()); // Output: bool(true)
     * ```
     */
    public function __construct(mixed $source, ?string $mimeDatabase = null)
    {
        if($mimeDatabase !== null){
            self::database($mimeDatabase);
        }

        /**
         * Resolve the MIME type and extension based on the source.
         */
        $this->type = self::guess($source);
        $this->extension = self::guessExtension($source, $this->getType());
    }

    /**
     * Get the MIME type of the source.
     *
     * @return string|null The MIME type, or null if not determined.
     */
    public function getType(): ?string
    {
        if($this->type === null){
            return null;
        }

        return strtolower($this->type);
    }

    /**
     * Get the file extension associated with the MIME type.
     *
     * @return string|null The file extension (without the dot), or null if not determined.
     */
    public function getExtension(): ?string
    {
        if($this->extension === null){
            return null;
        }

        return strtolower($this->extension);
    }

    /**
     * Check if the MIME type represents text data.
     *
     * @return bool Returns true if the MIME type indicates text data, false otherwise.
     */
    public function isText(): bool
    {
        if($this->type === null && $this->extension === null){
            return false;
        }

        if($this->type && str_starts_with($this->getType(), 'text/')){
            return true;
        }

        if($this->extension === null){
            return (bool) preg_match(
                '/^(?:application\/(?:json|x-json|ld\+json|xml|x-xml|rdf\+xml|atom\+xml|rss\+xml|javascript|x-javascript|xhtml\+xml)|image\/svg\+xml)$/i',
                $this->getType()
            );
        }

        static $texts = [
            'json', 'har', 'jsonld', 'xml', 'xhtml',
            'xsl', 'xsd', 'rdf', 'atom', 'rss', 'svg'
        ];

        return in_array($this->getExtension(), $texts, true);
    }

    /**
     * Check if the MIME type represents binary data.
     *
     * @return bool Returns true if the MIME type indicates binary data, false otherwise.
     */
    public function isBinary(): bool
    {
        return !$this->isText();
    }

    /**
     * Determine whether the MIME type belongs to the image category.
     *
     * @return bool Returns true if the MIME type indicates an image, false otherwise.
     * @see self::isDiskImage() for non-renderable image or disk image.
     * 
     * > This checks only browser **renderable** images.
     */
    public function isImage(): bool
    {
        if($this->type === null){
            return false;
        }

        return str_starts_with($this->getType(), 'image/');
    }

    /**
     * Determine whether the file is a non-renderable image or disk image.
     *
     * This method identifies formats that are commonly classified as images
     * or image containers but are not directly viewable (e.g. PSD, ISO, DMG).
     *
     * @return bool Returns true if the file represents a disk or container image.
     * @see self::isImage() for renderable images.
     */
    public function isDiskImage(): bool
    {
        if ($this->extension === null) {
            return false;
        }

        return !$this->isImage() 
            && in_array($this->getExtension(), ['psd', 'iso', 'dmg'], true);
    }

    /**
     * Check if the MIME type represents video data.
     *
     * @return bool Returns true if the MIME type indicates video data, false otherwise.
     */
    public function isVideo(): bool
    {
        return $this->type !== null && str_starts_with($this->getType(), 'video/');
    }

    /**
     * Check if the MIME type represents audio data.
     *
     * @return bool Returns true if the MIME type indicates audio data, false otherwise.
     */
    public function isAudio(): bool
    {
        return $this->type !== null && str_starts_with($this->getType(), 'audio/');
    }

    /**
     * Determine whether the file is an archive or package.
     *
     * Includes traditional archives (zip, tar, 7z, rar, etc.)
     * and runtime packages (apk, jar, war, aar).
     *
     * @return bool Return true if the file is an archive or package.
     */
    public function isArchive(): bool
    {
        if ($this->extension === null) {
            return false;
        }

        static $archives = [
            'zip', 'zipx', '7z', 'tar', 'tar.gz', 'tgz', 'gz', 'bz2',
            'cab', 'rar', 'ace', 'pea', 'zpaq', 'paq',
            'apk', 'jar', 'war', 'aar',
        ];

        return in_array($this->getExtension(), $archives, true);
    }

    /**
     * Check if the MIME type matches the given type.
     *
     * @param string $mime The MIME type to compare against.
     * 
     * @return bool Returns true if the MIME types match, false otherwise.
     */
    public function is(string $mime): bool
    {
        $mime = trim($mime);

        if(!self::isMimeType($mime)){
            return false;
        }

        return $this->getType() === strtolower($mime);
    }

    /**
     * Determine if a string is a valid MIME type.
     *
     * Checks the format "type/subtype", optionally with suffixes like +xml.
     * This does NOT validate parameters (e.g., ; charset=utf-8) strictly RFC-compliant.
     *
     * @param string $mime The MIME type string to check.
     * @param int $type Maximum length for the type (default 63).
     * @param int $subtype Maximum length for the subtype (default 127).
     *
     * @return bool Return true if the string is a valid MIME type format, false otherwise.
     */
    public static function isMimeType(string $mime, int $type = 63, int $subtype = 127): bool
    {
        $mime = trim($mime);

        if($mime === ''){
            return false;
        }

        $pattern = "#^[a-z0-9][a-z0-9.+-]{0,{$type}}/[a-z0-9][a-z0-9.+-]{0,{$subtype}}$#i";

        return (bool) preg_match($pattern, $mime);
    }

    /**
     * Determine if a string is a valid file extension.
     *
     * Supports:
     * - Single-part extensions: "zip", "exe"
     * - Compound extensions: "tar.gz", "backup.2026"
     * - Each segment: 1–10 alphanumeric characters
     *
     * @param string $extension The file extension to check (without dot).
     * 
     * @return bool Return true if valid, false otherwise.
     */
    public static function isExtension(string $extension): bool
    {
        $extension = trim(strtolower($extension));

        if ($extension === '') {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9]{1,10}(?:\.[a-z0-9]{1,10})*$/', $extension);
    }

    /**
     * Get a list of all registered MIME database files.
     *
     * @return array<string> Returns an array of file paths.
     */
    public static function getDatabase(): array
    {
        if(self::$files === []){
            return [];
        }

        return array_keys(self::$files);
    }

    /**
     * Get all registered MIME types.
     *
     * @return array<string,string> Returns an array of MIME types and extension as key.
     */
    public static function getTypes(): array
    {
        return self::$db;
    }

    /**
     * Find the MIME type for a file extension or filename.
     *
     * @param string $from File extension, filename, or file path.
     *
     * @return string|null Return the matching MIME type, or null if not found.
     */
    public static function findType(string $from): ?string
    {
        $ext = trim(pathinfo($from, PATHINFO_EXTENSION) ?: $from, ' .');

        if($ext === ''){
            return null;
        }

        return self::$db[strtolower($ext)] ?? null;
    }

    /**
     * Find file extension(s) for a given MIME type.
     *
     * @param string $mime The MIME type to resolve.
     * @param bool $allExtensions Whether to return all or only the first (most common) extension (default: `false`).
     *
     * @return string|string[]|null Returns an extension, a list of extensions, or null if not found.
     */
    public static function findExtension(string $mime, bool $allExtensions = false): array|string|null
    {
        if (($pos = strpos($mime, ';')) !== false) {
            $mime = substr($mime, 0, $pos);
        }

        $mime = strtolower(trim($mime));
        $extensions = [];

        if('audio/webm' === $mime){
            return $allExtensions ? ['webm', 'webm'] : 'webm';
        }

        foreach (self::$db as $ext => $type) {
            if (strtolower($type) === $mime) {
                if(!$allExtensions){
                    return $ext;
                }

                $extensions[] = $ext;
            }
        }

        return $allExtensions ? $extensions : null;
    }

    /**
     * Register a new MIME type for a given file extension.
     *
     * @param string $extension The file extension (without the dot).
     * @param string $mime The corresponding MIME type.
     * 
     * @return void 
     * @throws InvalidArgumentException If the extension or MIME type is invalid.
     * 
     * @example - Example:
     * ```php
     * // Register a new MIME type for a custom file extension
     * MIME::register('custom', 'application/custom-type');
     * ```
     */
    public static function register(string $extension, string $mime): void
    {
        $extension = trim($extension, ' .');
        $mime = trim($mime);

        if(!self::isExtension($extension)){
            throw new InvalidArgumentException("Invalid extension name: {$extension}");
        }

        if ($mime && ($pos = strpos($mime, ';')) !== false) {
            $mime = substr($mime, 0, $pos);
        }

        if(!self::isMimeType($mime)){
            throw new InvalidArgumentException("Invalid MIME type: {$mime}");
        }

        self::$db[strtolower($extension)] = strtolower($mime);
    }

    /**
     * Load a custom MIME type database from a file.
     * 
     * Supported file formats:
     * - .mgc: Binary magic database file (used by PHP finfo).
     * - .json: JSON file with MIME type mappings `{'ext': 'mime/type', ...}`.
     * - .txt: Text file with lines in the format `'mime/type ext1 ext2 ext3'`. Lines starting with '#' are ignored.
     * - .php: PHP file that returns an array of MIME `return ['ext' => 'mime/type']`.
     *
     * @param string $file The path to the MIME magic or custom database file.
     * 
     * @return void
     * @throws RuntimeException If the file is not readable or cannot be processed.
     * @throws InvalidArgumentException If the file contains invalid extensions or MIME types.
     * 
     * @example - Example:
     * ```php
     * // Load a custom MIME database
     * MIME::database('/writable/custom/mime.json');
     * 
     * // Guess MIME types
     * MIME::guess('mime.txt');
     * ```
     * 
     * > **Note:**
     * > Multiple databases can be loaded by calling `MIME::database()` multiple times.
     * > Later entries merge with earlier ones, without overwriting previously registered extensions.
     */
    public static function database(string $file): void
    {
        if (isset(self::$files[$file])) {
            return;
        }

        if (!is_file($file)) {
            throw new RuntimeException(sprintf(
                'MIME database expects a file path, but "%s" is not a file.',
                $file
            ));
        }

        if (!is_readable($file)) {
            throw new RuntimeException("MIME DB file is not readable: {$file}");
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if ($extension === 'json') {
            self::loadJsonDatabase($file);
            return;
        }

        if ($extension === 'txt') {
            self::loadTextDatabase($file);
            return;
        }

        if ($extension === 'php') {
            $data = @include $file;

            if($data === 1 || !is_array($data)){
                return;
            }

            self::$db = array_merge(self::$db, $data);
            self::$files[$file] = true;
            return;
        }

        self::$magicDatabase = $file;
        self::$files[$file] = true;
    }

    /**
     * Export the current MIME type database to a file.
     *
     * @param string $destination The path to the destination file.
     * 
     * @return bool Returns true if the export was successful, false otherwise.
     * @throws InvalidArgumentException If the destination file format is unsupported.
     * 
     * @example - Example:
     * ```php
     * // Export the current MIME database to a JSON file
     * MIME::export('/writable/custom/mime.json');
     * ```
     */
    public static function export(string $destination): bool
    {
        $ext = strtolower(pathinfo($destination, PATHINFO_EXTENSION));

        if ($ext === 'json') {
            return file_put_contents(
                $destination, 
                json_encode(self::$db, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            ) !== false;
        } 
        
        if ($ext === 'txt') {
            $grouped = [];
            $length = 25;
            foreach (self::$db as $extension => $mime) {
                if (!$extension || !$mime) {
                    continue;
                }

                $size = strlen($mime);

                if($size > $length){
                    $length = $size;
                }

                $grouped[$mime][] = $extension;
            }

            foreach ($grouped as &$extList) {
                sort($extList, SORT_STRING);
            }

            unset($extList);

            $length += 20;

            $lines = [];
            $lines[] = '# MIME database exported on ' . date('Y-m-d H:i:s');
            $lines[] = str_pad('# MIME type', $length) . 'Extensions';
            $lines[] = '';

            foreach ($grouped as $mime => $extensions) {
                $lines[] = str_pad($mime, $length) . implode(' ', $extensions);
            }

            return file_put_contents($destination, implode(PHP_EOL, $lines)) !== false;
        }

        throw new InvalidArgumentException("Unsupported export format: {$ext}. Use .json or .txt");
    }

    /**
     * Guess the MIME type of a given source.
     * 
     * This method detects MIME type from a file path, stream resource, or raw data.
     * 
     * This method attempts MIME detection in a safe and predictable order:
     * 1. If a file path is provided, it detects the MIME type from the file.
     * 2. If a stream resource is provided, it first checks the file uri (if any).
     * 3. Stream fallback, using the first bytes of the stream without altering
     *    the stream position.
     *
     * The stream cursor is always restored to its original position, making this
     * method safe to use before or during file reads.
     *
     * @param string|resource $source The file path, resource, mime string, or data string to detect.
     * @param string|null $magicDatabase Optional path to a MIME magic database file.
     * 
     * @return string|null Returns the guessed MIME type, or null if it cannot be determined.
     * @throws InvalidArgumentException If the source type is invalid.
     * 
     * @see self::database() For loading custom MIME databases.
     * 
     * @example - Examples:
     * ```php
     * // Guess the MIME from a file with custom magic database
     * MIME::guess('audio.mp3', '/usr/share/mime/magic'); // 'audio/mpeg'
     * 
     * // Guess the MIME from filename
     * MIME::guess('mime.txt'); // 'text/plain'
     * 
     * // Guess the MIME from MIME
     * MIME::guess('text/plain'); // 'text/plain'
     * 
     * // Guess the MIME from content
     * MIME::guess('{"key":"value"}'); // 'application/json'
     * 
     * // Guess the MIME from file content
     * $content = file_get_contents('mime.txt');
     * MIME::guess($content); // 'text/plain'
     * 
     * // Guess the MIME from file
     * MIME::guess('/path/to/audio.mp3'); // 'audio/mpeg'
     * ```
     */
    public static function guess(mixed $source, ?string $magicDatabase = null): ?string
    {
        self::assertSource($source);

        if ($source === '') {
            return 'application/octet-stream';
        }

        if (is_resource($source)) {
            self::initFinfo($magicDatabase);

            return self::guessFromResource($source);
        }

        if (is_file($source)) {
            self::initFinfo($magicDatabase);

            return self::guessFromFile($source);
        }

        return self::guessFromString($source, $magicDatabase);
    }

    /**
     * Initialize the file info object with the specified magic database.
     *
     * @param string|null $magicDatabase The path to the custom magic database file.
     * 
     * @return void
     */
    private static function initFinfo(?string $magicDatabase): void
    {
        if (!self::$finfo instanceof finfo) {
            self::$finfo = new finfo(
                FILEINFO_MIME_TYPE, 
                $magicDatabase ?? self::$magicDatabase
            );
        }
    }

    /**
     * Guess the MIME type from a string value.
     *
     * @param string $value The string value to detect.
     * 
     * @return string|null Returns the guessed MIME type, or null if it cannot be determined.
     */
    private static function guessFromString(string $value, ?string $magicDatabase): ?string
    {
        if (json_validate($value)) {
            return 'application/json';
        }

        if(self::isMimeType($value)){
            return trim($value);
        }

        if (self::isFileBasename($value)) {
            $mime = self::guessFromExtension($value);
            
            if ($mime) {
                return $mime;
            }
        }

        self::initFinfo($magicDatabase);

        return self::$finfo->buffer($value) ?: null;
    }

    /**
     * Guess the MIME type from a file extension.
     *
     * @param string $value The file name or extension to detect.
     * 
     * @return string|null Returns the guessed MIME type, or null if it cannot be determined.
     */
    private static function guessFromExtension(string $value): ?string
    {
        $value = str_contains($value, '://') ? parse_url($value, PHP_URL_PATH) : $value;

        if (!$value) {
            return null;
        }

        $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));

        if ($ext === '') {
            return null;
        }

        return self::findType($ext);
    }

    /**
     * Guess the MIME type from a file path.
     *
     * @param string $file The file path to detect.
     * 
     * @return string|null Returns the guessed MIME type, or null if it cannot be determined.
     */
    private static function guessFromFile(string $file): ?string
    {
        $fallback = null;

        $mime = self::$finfo->file($file) ?: null;

        if ($mime === 'application/octet-stream') {
            $fallback = $mime;
            $mime = null;
        }

        if ($mime) {
            return $mime;
        }

        return self::guessFromExtension($file) ?? $fallback;
    }

    /**
     * Guess the MIME type from a stream resource.
     *
     * @param resource $stream The stream resource to detect.
     * 
     * @return string|null Returns the guessed MIME type, or null if it cannot be determined.
     */
    private static function guessFromResource($stream): ?string
    {
        $meta = stream_get_meta_data($stream);
        $uri = $meta['uri'] ?? null;

        if ($uri && is_file($uri)) {
            return self::guessFromFile($uri);
        }

        $pos = ftell($stream);
        if ($pos === false) {
            return null;
        }

        $chunk = fread($stream, 4096);
        fseek($stream, $pos);

        if ($chunk === false || $chunk === '') {
            return null;
        }

        return self::$finfo->buffer($chunk) ?: null;
    }

    /**
     * Assert that the source is a valid string content, file path, or stream resource.
     *
     * @param mixed $source The source to validate.
     * 
     * @throws InvalidArgumentException If the source type is invalid.
     */
    private static function assertSource(mixed $source): void
    {
        if (
            !is_string($source) &&
            !(is_resource($source) && get_resource_type($source) === 'stream')
        ) {
            throw new InvalidArgumentException(sprintf(
                'Invalid source type: %s, expected string content, file path, or stream resource.',
                gettype($source)
            ));
        }
    }

    /**
     * Guess the file extension based on the source and MIME type.
     *
     * @param mixed $source The source to analyze.
     * @param string|null $mime The detected MIME type.
     * 
     * @return string|null Returns the guessed file extension, or null if it cannot be determined.
     */
    private static function guessExtension(mixed $source, ?string $mime): ?string
    {
        if($mime){
            $ext = self::findExtension($mime);

            if($ext){
                return $ext;
            }
        }

        if($source === ''){
            return 'txt';
        }

        if (!is_resource($source)) {
            if (is_file($source)) {
                return strtolower(pathinfo($source, PATHINFO_EXTENSION));
            }

            if(json_validate($source)) {
                return 'json';
            }

            if (self::isFileBasename($source) && !self::isContent($source)) {
                if (preg_match('/\.([a-z0-9]+(?:\.[a-z0-9]+)*)$/i', $source, $matches)) {
                    return strtolower($matches[1]);
                }
            }

            return $mime ? self::findExtension($mime) : null;
        }

        $meta = stream_get_meta_data($source);
        $uri  = $meta['uri'] ?? null;

        if (!$uri) {
            return null;
        }

        $path = str_contains($uri, '://') ? parse_url($uri, PHP_URL_PATH) : $uri;

        if (!$path) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return ($ext !== '') ? $ext : null;
    }

    /**
     * Determine if a string is likely a file name with a valid extension.
     *
     * @param string $value The string to check.
     * 
     * @return bool Returns true if it looks like a filename with an extension.
     */
    private static function isFileBasename(string $value): bool
    {
        if ($value === '' || str_contains($value, '/') || str_contains($value, '\\')) {
            return false;
        }

        return self::isExtension($value);
    }


    /**
     * Load a custom MIME type database from a JSON file.
     *
     * @param string $file The path to the MIME magic or custom database file.
     * 
     * @return void
     * @throws RuntimeException If the file cannot be read.
     * @throws InvalidArgumentException If the JSON content is invalid.
     */
    private static function loadJsonDatabase(string $file): void
    {
        $content = file_get_contents($file);

        if ($content === false) {
            throw new RuntimeException("Failed to read MIME DB: {$file}");
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new InvalidArgumentException("Invalid JSON MIME DB: {$file}");
        }

        self::$db = array_merge(self::$db, $data);
        self::$files[$file] = true;
    }

    /**
     * Load a custom MIME type database from a text file.
     *
     * @param string $file The path to the MIME magic or custom database file.
     * 
     * @return void
     * @throws RuntimeException If the file cannot be read or contains invalid lines.
     * @throws InvalidArgumentException If the file contains invalid extensions or MIME types.
     */
    private static function loadTextDatabase(string $file): void
    {
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $idx => $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = preg_split('/\s+/', $line);

            if (!$parts || count($parts) < 2) {
                throw new RuntimeException("Invalid MIME entry: {$line} on line: {$idx}");
            }

            $mime  = array_shift($parts);

            if (!$mime || !is_string($mime)) {
                throw new RuntimeException("Invalid MIME type in entry: {$line} on line: {$idx}");
            }

            foreach ($parts as $ext) {
                try{
                    self::register($ext, $mime);
                }catch(Throwable $e){
                    throw new RuntimeException(sprintf(
                        '%s in entry "%s" on line %d',
                        $e->getMessage(),
                        $line,
                        $idx
                    ), $e->getCode(), $e);
                }
            }
        }

        self::$files[$file] = true;
    }

    /**
     * Determine if the given value is likely file content.
     *
     * @param string $value The value to check.
     * 
     * @return bool Returns true if the value appears to be file content, false otherwise.
     */
    private static function isContent(string $value): bool
    {
        if (str_contains($value, "\n")) {
            return true;
        }

        if (str_contains($value, "\0")) {
            return true;
        }

        if (strlen($value) > 255) {
            return true;
        }

        return false;
    }
}