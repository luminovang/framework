<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Components\Email\Clients;

use \Luminova\Utility\MIME;
use \Luminova\Logger\Logger;
use \Luminova\Utility\Helpers;
use \Luminova\Storage\Filesystem;
use \Luminova\Interface\MailerInterface;
use \Luminova\Exceptions\MailerException;
use function \Luminova\Funcs\get_content;

class NovaMailer implements MailerInterface
{
    public const CHARSET_UTF8 = 'UTF-8';
    public const CONTENT_TYPE_PLAINTEXT = 'text/plain';
    public const CONTENT_TYPE_TEXT_CALENDAR = 'text/calendar';
    public const CONTENT_TYPE_TEXT_HTML = 'text/html';
    public const CONTENT_TYPE_MULTIPART_ALTERNATIVE = 'multipart/alternative';
    public const CONTENT_TYPE_MULTIPART_MIXED = 'multipart/mixed';
    public const CONTENT_TYPE_MULTIPART_RELATED = 'multipart/related';

    public const ENCODING_7BIT = '7bit';
    public const ENCODING_8BIT = '8bit';
    public const ENCODING_BASE64 = 'base64';
    public const ENCODING_BINARY = 'binary';
    public const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';

    /**
     * To email address.
     * 
     * @var array<int,string> $to 
     */
    private array $to = [];

    /**
     * To Bcc email addresses. 
     * 
     * @var array $bcc 
     */
    private array $bcc = [];

    /**
     * To Cc email addresses. 
     * 
     * @var array $cc 
     */
    private array $cc = [];

    /**
     * Message subject. 
     * 
     * @var string $Subject 
     */
    public string $Subject = '';

    /**
     * Message main body. 
     * 
     * @var string $Body 
     */
    public string $Body = '';

    /**
     * Message plain text. 
     * 
     * @var string $AltBody 
     */
    public string $AltBody = '';

    /**
     * From email address. 
     * 
     * @var string $from 
     */
    private string $from = '';

    /**
     * Mail priority address. 
     * 
     * 1 = High, 3 = Normal (default), 5 = Low
     * 
     * @var int $Priority 
     */
    public int $Priority = 3;

    /**
     * Return Path
     * 
     * @var string $Sender 
     */
    public string $Sender = '';

    /**
     * Organization name. 
     * 
     * @var string $Organization 
     */
    public string $Organization = '';

    /**
     * Reply to email address.
     * 
     * @var string $replyTo 
     */
    private string $replyTo = '';

    /**
     * Notification to email address.
     * 
     * @var string $notificationTo 
     */
    private string $notificationTo = '';

    /**
     * Send handler method. 
     * 
     * @var string $sendHandler 
     */
    private string $sendHandler = 'mail';

    /**
     * Main message content type. 
     * 
     * @var string $contentType 
     */
    private string $contentType = 'text/html';

    /**
     * Message attachments. 
     * 
     * @var array $attachments 
     */
    private array $attachments = [];

    /**
     * Sender signature. 
     * 
     * @var string $XMailer 
     */
    public string $XMailer = '';

    /**
     * Main message content type charset. 
     * 
     * @var string $CharSet 
     */
    public string $CharSet = self::CHARSET_UTF8;

    /**
     * SMTP debug level (unused). 
     * 
     * @var int $SMTPDebug 
     */
    public int $SMTPDebug = 0;

    /**
     * The SMTP server timeout in seconds.
     *
     * @var int $Timeout
     */
    public $Timeout = 300;

    /**
     * SMTP connection options. 
     * 
     * @var array<array,mixed> $SMTPOptions
     */
    public array $SMTPOptions = [];

    /**
     * SMTP connection status. 
     * 
     * @var bool $isEstablished 
     */
    public bool $isEstablished = false;

    /**
     * SMTP last response line. 
     * 
     * @var string $line 
     */
    public string $line = '';

    /**
     * SMTP connection host. 
     * 
     * @var string $Host 
     */
    public string $Host = '';

    /**
     * Server hostname. 
     * 
     * @var string $Hostname 
     */
    private string $Hostname = APP_HOSTNAME;

    /**
     * SMTP connection port. 
     * 
     * @var int $Port 
     */
    public int $Port = -1;

    /**
     * SMTP Authentication mode. 
     * 
     * @var bool $SMTPAuth 
     */
    public bool $SMTPAuth = false;

    /**
     * SMTP authentication username. 
     * 
     * @var string $Username 
     */
    public string $Username = '';

    /**
     * SMTP authentication password. 
     * 
     * @var string $Password 
     */
    public string $Password = '';

    /**
     * SMTP connection type. 
     * 
     * @var string $SMTPSecure 
     */
    public string $SMTPSecure = 'tls';

    /**
     * SMTP socket connection. 
     * 
     * @var resource|bool $connection
     */
    private mixed $connection = false;

    /**
     * Email headers. 
     * 
     * @var array<string,mixed> $headers
     */
    private array $headers = [];

    /**
     * {@inheritdoc}
     */

    /**
     * Constructor.
     *
     * @param bool $exceptions Whether to throw exceptions if error (default: false).
     */
    public function __construct(public bool $exceptions = false) {}

    /**
     * {@inheritdoc}
     */
    public function reset(): static
    {
        $this->to = [];
        $this->cc = [];
        $this->bcc = [];
        $this->attachments = [];
        $this->headers = [];

        $this->from = '';
        $this->Sender = '';
        $this->notificationTo = '';
        $this->replyTo = '';

        $this->Subject = '';
        $this->Body = '';
        $this->AltBody = '';

        $this->Port = -1;
        $this->SMTPAuth = false;
        $this->Username = '';
        $this->Password = '';
        $this->Host = '';

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function clearAddresses(): self 
    {
        $this->to = [];
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function clearCc(): self 
    {
        $this->cc = [];
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function clearBcc(): self 
    {
        $this->bcc = [];
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function clearAttachments(): self 
    {
        $this->attachments = [];
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function clearHeaders(): static
    {
        $this->headers = [];
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMailer(): MailerInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(): void { }

    /**
     * {@inheritdoc}
     */
    public function setFrom(string $address, string $name = '', bool $auto = true): bool 
    {
        $this->from = $this->parse($address, $name, $auto);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setNotificationTo(string $address): bool
    {
        $this->notificationTo = $this->toAddress($address);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function addAddress(string $address, string $name = ''): bool
    {
        $this->to[] = $this->parse($address, $name);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function addAddresses(string|array $address): bool
    {
        $count = 0;
        $address = is_string($address) ? explode(',', $address) : $address;

        foreach ($address as $email => $name) {

            if (is_int($email)) {
                $this->addAddress($name, $this->toName($name));
                $count++;
                continue;
            }

            $this->addAddress($email, $name);
            $count++;
        }

        return $count > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function addCC(string $address, string $name = ''): bool
    {
        $this->cc[] = $this->parse($address, $name);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function addBCC(string $address, string $name = ''): bool
    {
        $this->bcc[] = $this->parse($address, $name);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function addReplyTo(string $address, string $name = ''): bool 
    {
        $this->replyTo = $this->parse($address, $name);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function addAttachment(
        string $path, 
        string $name = '', 
        string $encoding = self::ENCODING_BASE64, 
        string $type = '', 
        string $disposition = 'attachment'
    ): bool {
        try {
            if (!Filesystem::isAccessible($path)) {
                throw MailerException::rethrow('file_access', $path);
            }

            if ('' === $name) {
                $name = basename($path);
            }

            $this->attachments[] = [
                'path' => $path,
                'name' => $name,
                'encoding' => $encoding,
                'type' => $type,
                'disposition' => $disposition
            ];
            return true;
        } catch (MailerException $e) {
            if ($this->exceptions) {
                throw MailerException::rethrow($e->getMessage());
            }

            Logger::dispatch('exception', $e->getMessage());
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function send(): bool 
    {
        error_clear_last();

        $sent = 0;
        $body = ($this->attachments === []) 
            ? $this->sendAttachment() 
            : $this->sendMessage();

        if($this->sendHandler === 'mail'){
            set_max_execution_time(0);
            $start = microtime(true);
        }

        foreach ($this->to as $recipient) {

            $email = $recipient[0] ?? null;

            if (!$email) {
                continue;
            }

            if($this->sendHandler === 'smtp'){
                if($this->smtpMail($body, $email)){
                    $sent++;
                }

                continue;
            }

            [$headers, $rawBody] = $this->toMail($body, $email);

            $success = mail(
                $this->toAddress($email), 
                $this->Subject, 
                $rawBody, 
                $headers
            );

            if($success){
                $sent++;
            }

            if($this->Timeout > 0){
                $elapsed = microtime(true) - $start;

                if ($elapsed > $this->Timeout) {
                    $error = "Mail to {$email} exceeded maximum allowed time of {$this->Timeout}s ({$elapsed}s).";

                    if($this->exceptions){
                        throw new MailerException($error);
                    }

                    Logger::dispatch('error', $error);
                    return false;
                }
            }
        }

        if ($sent > 0) {
            return true;
        }

        $error = error_get_last();

        if($error !== null){
            if($this->exceptions){
                throw new MailerException($error['message']);
            }

            Logger::dispatch('error', $error['message']);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isSMTP(): void 
    {
        $this->sendHandler = 'smtp';
    }

    /**
     * {@inheritdoc}
     */
    public function isMail(): void 
    {
        $this->sendHandler = 'mail';
    }

    /**
     * {@inheritdoc}
     */
    public function isHTML(bool $isHtml = true): void 
    {
        $this->contentType = $isHtml 
            ? self::CONTENT_TYPE_TEXT_HTML 
            : self::CONTENT_TYPE_PLAINTEXT;
    }

    /**
     * {@inheritdoc}
     */
    public function addHeader(string $name, ?string $value = null): bool 
    {
        $this->headers[$name] = $value;
        return true;
    }

    /**
     * Send email using smtp details.
     * 
     * @param array $body Email content information.
     * 
     * @return bool Return true if successful, otherwise false.
     * @throws MailerException
     */
    private function smtpMail(array $body, string $email): bool
    {
        $this->connection = $this->connection($this->SMTPOptions);
        $this->onEstablished();

        if ($this->SMTPAuth && !$this->login()) {
           throw new MailerException('Failed to initiate authentication, server response: ' . $this->line);
        }

        $from = $this->toAddress($this->from ?? $this->Username);

        $this->command("MAIL FROM:<{$from}>", 'MAIL FROM');
        $this->command("RCPT TO:<{$this->toAddress($email)}>", 'RCPT TO');

        if($this->bcc !== []){
            foreach ($this->bcc as $bcc) {
                $this->command("RCPT TO: <{$this->toAddress($bcc)}>", "RCPT BCC TO: {$bcc}");
            }
        }

        $this->command('DATA', 'DATA');
        $this->commands($this->getHeaders($email), true);
        $this->commands($body);
        $this->command('.', 'END');
        $this->command('QUIT', 'QUIT');
        $this->close();

        return true;
    }

    /**
     * Open SMTP socket connection.
     * 
     * @param array $options Connection options.
     * 
     * @return resource Return smtp connection resource.
     */
    public function connection(array $options = []): mixed
    {
        if ($this->connected()) {
            return $this->connection;
        }

        static $isSocks;
        $transport = ($this->SMTPSecure === 'tls') ? '' : 'ssl://';
        $transport .= $this->Host;
        
        if ($isSocks === null) {
            $isSocks = function_exists('stream_socket_client');
        }

        if($isSocks){
            $transport .= ':' . $this->Port;
            $context = stream_context_create($options);
            $conn = stream_socket_client(
                $transport,
                $errno,
                $errstr,
                $this->Timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );
        }else{
            $conn = fsockopen($transport, $this->Port, $errno, $errstr, $this->Timeout);
        }

        if (!is_resource($conn)) {
            throw new MailerException(
                sprintf('Failed to connect to SMTP server: %s (%d)', $errstr, $errno), 
                $errno
            );
        }

        set_max_execution_time($this->Timeout);
        stream_set_timeout($conn, $this->Timeout, 0);
        return $conn;
    }

    /**
     * Check socket already connected.
     *
     * @return bool Return true if connected.
     */
    public function connected(): bool
    {
        if ($this->isEstablished && is_resource($this->connection)) {
            $status = stream_get_meta_data($this->connection);

            if (!$status['eof']) {
                return true;
            }

            Logger::debug('SMTP NOTICE: EOF caught while checking if connected');
            $this->close();
        }

        return false;
    }

    /**
     * Close the socket connection.
     * 
     * @return bool Return true if connection was closed, otherwise false.
     */
    public function close(): bool
    {
        if (is_resource($this->connection)) {
            fclose($this->connection);

            $this->connection = null;
            $this->isEstablished = false;

            if(!PRODUCTION){
                Logger::debug('Connection: closed');
            }
        }

        return !is_resource($this->connection);
    }

    /**
     * Initiates SMTP authentication with the provided username and password.
     *
     * @return bool Returns true if the authentication was successful, false otherwise.
     *
     * @throws MailerException If the SMTP server does not respond as expected during the authentication process.
     */
    protected function login(): bool 
    {
        if ($this->command('AUTH LOGIN', 'AUTH') !== 334) {
            return false;
        }

        if ($this->command(base64_encode($this->Username), 'Username') !== 334) {
            return false;
        }

        if ($this->command(base64_encode($this->Password), 'Password') !== 235) {
            return false;
        }

        return true;
    }

    /**
     * Sends a series of SMTP commands to the server.
     *
     * @param array<string|int,string> $lines The SMTP header or body commands to send.
     * @param bool $isHeader Whether the lines are SMTP headers.
     *
     * @return void
     */
    protected function commands(array $lines, bool $isHeader = false): void 
    {
        $max = 998;
        if($isHeader === false){
            $field = strtok($lines[0], ':');
            $isHeader = (empty($field) || !str_contains($field, ' '));
        }

        foreach ($lines as $key => $line) {
            $normalized = [];
            $line = (!$line || is_int($key)) ? $line : "{$key}: {$line}";

            if ($isHeader && $line === '') {
                $isHeader = false;
            }

            while (strlen($line) > $max) {
                $pos = strrpos(substr($line, 0, $max), ' ');

                if ($pos === false) {
                    $pos = $max - 1;
                }

                $normalized[] = substr($line, 0, $pos);
                $line = substr($line, $pos + 1);

                if ($isHeader) {
                    $line = "\t" . $line;
                }
            }

            $normalized[] = $line;

            foreach ($normalized as $normalize) {
                if (!empty($normalize) && $normalize[0] === '.') {
                    $normalize = '.' . $normalize;
                }

                $this->command($normalize, 'DATA');
            }
        }
    }

    /**
     * Sends a series of SMTP commands to the server.
     *
     * @param string $command The SMTP command to send.
     * @param string $name The name of the SMTP command for logging purposes.
     *
     * @return int Return the status code of the SMTP command response.
     */
    protected function command(string $command, string $name = ''): int 
    {
        fwrite($this->connection, "{$command}\r\n");

        return $this->getStatus($name);
    }

    /**
     * Handles the SMTP connection after it has been established.
     *
     * @throws MailerException If the connection cannot be established or if TLS upgrade fails.
     */
    protected function onEstablished(): void 
    {
        if (!is_resource($this->connection)) {
            throw new MailerException('Failed to establish connection');
        }

        if(!$this->hello('EHLO Established')){
            throw new MailerException(sprintf('Connection error: %s', $this->line));
        }

        if($this->SMTPSecure !== 'tls'){
            $this->isEstablished = true;
            return;
        }

        if($this->command('STARTTLS', 'TLS Upgrade') === 250){
            $this->isEstablished = $this->hello('EHLO TLS Upgrade');
            return;
        }

        $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;

        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            $method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }

        if (!stream_socket_enable_crypto($this->connection, true, $method)) {
            throw new MailerException(
                sprintf('Failed to start TLS encryption. Server response: %s. Last Line: %s', 
                    fgets($this->connection), 
                    $this->line
                )
            );
        }

        $this->isEstablished = $this->hello('EHLO afterUpgrade TLS');
    }

    /**
     * Send the EHLO greeting to the SMTP server.
     *
     * @param string $name The name of the SMTP command for logging purposes.
     *
     * @return bool Returns true if the EHLO command was successful and the server responded with a 220 status code.
     *              Returns false if the EHLO command failed or the server responded with a 554 status code.
     *              Also sends a QUIT command if the server responded with a 554 status code.
     *
     * @throws MailerException If the connection cannot be established or if TLS upgrade fails.
     */
    protected function hello(string $name): bool 
    {
        $status = $this->command('EHLO ' . $this->Hostname, $name);

        if($status === 220){
            return true;
        }

        if ($status === 554) {
            $this->command('QUIT');
        }

        return false;
    }

    /**
     * Extract the email address from a full input string.
     *
     * Parses and returns the actual email address from input that may contain a name and angle brackets,
     * e.g., "Name <email@example.com>".
     *
     * @param string $input The full email string to extract from.
     *
     * @return string Return the extracted or trimmed email address.
     */
    protected function toAddress(string $input): string
    {
        $input = trim($input);

        if (preg_match('/<(.+?)>/', $input, $match)) {
            return $match[1];
        }

        return $input;
    }

    /**
     * Convert a array containing email headers and body strings mail function format.
     *
     * Formats an array with keys `headers` and `body` into the final string representations required by 
     * the `mail()` function.
     *
     * @param array<string,array> $result The parsed result containing 'headers' (key-value) and 'body' (lines).
     *
     * @return array Return an array with two elements: the headers string and the message body string.
     */
    protected function toMail(array $body, string $email): array
    {
        $headers = '';

        foreach($this->getHeaders($email) as $key => $value){
            $headers .= "{$key}: {$value}\r\n";
        }

        return [$headers, implode("\r\n", $body) . "\r\n"];
    }

    /**
     * Extracts the name part from a full email string.
     *
     * Supports:
     * - "John Doe <john@example.com>" → "John Doe"
     * - "john@example.com" → ""
     * - "John Doe" → "John Doe"
     *
     * @param string $input The input string to extract the name from.
     * @return string The extracted name or an empty string if not available.
     */
    protected function toName(string $input): string
    {
        $input = trim(preg_replace('/[\r\n]+/', '', $input));

        if ($input === '') {
            return '';
        }

        if (str_contains($input, '<') && preg_match('/^(.+?)\s*<[^>]+>$/', $input, $match)) {
            return trim($match[1]);
        }

        return filter_var($input, FILTER_VALIDATE_EMAIL) ? '' : $input;
    }

    /**
     * Converts the email body into a format suitable for the mail function.
     *
     * Handles encoding for SMTP or plain send.
     * If it's 'smtp', it explodes the email body, replaces carriage returns and line breaks with 
     * a single newline character, and merges the result with the existing body array.
     *
     * @param array &$body The reference to the array containing the email body.
     *
     * @return void
     */
    private function toBody(array &$body, bool $isNoneAscii): void
    {
        $content = str_replace(["\r\n", "\r"], "\n", $this->Body);

        if ($this->sendHandler === 'smtp') {
            $lines = explode("\n", $content);

            foreach ($lines as $line) {
                $body[] = str_replace("\n", "\r\n", quoted_printable_encode($line));
            }

            return;

        }

        $body[] = str_replace(
            "\n", "\r\n",
            $isNoneAscii ? quoted_printable_encode($content) : $content
        );
    }

    /**
     * Generate a unique MIME boundary ID.
     *
     * Used to separate parts of a multipart email. This boundary is a URL-safe base64-encoded
     * SHA-256 hash of a randomly generated 10-character alphanumeric string.
     *
     * @return string Return a unique MIME boundary string.
     */
    protected function getBoundaryId(): string
    {
        return Helpers::base64UrlEncode(hash('sha256', Helpers::random(10, 'alphanumeric'), true));
    }

    /**
     * Get the SMTP status code from the server response.
     *
     * Sends a command or reads a line from the server, and extracts the 3-digit status code from the start.
     * An optional identifier name can be passed to track or label the operation.
     *
     * @param string $name An optional identifier for logging or context.
     *
     * @return int Return the 3-digit SMTP status code.
    */
    protected function getStatus(string $name = ''): int 
    {
        $this->line = $this->getLines($name);
        return (int) substr($this->line, 0, 3);
    }

    /**
     * Get initial headers.
     *
     * @return array Return message default and custom headers.
     */
    protected function getHeaders(string $recipient): array 
    {
        $sender = $this->from ?: $this->Sender;

        $this->headers['To'] = $recipient;
        $this->headers['From'] = $sender ?: "<{$this->Username}>";

        if($this->replyTo) {
            $this->headers['Reply-To'] = $this->replyTo;
        }

        if ($this->cc !== []) {
            $this->headers['Cc'] = implode(',', $this->cc);
        }

        if ($this->sendHandler === 'mail' && $this->bcc !== []) {
            $this->headers['Bcc'] = implode(',', $this->bcc);
        }

        if($this->notificationTo){
            $this->headers['Return-Receipt-To'] = $this->notificationTo;
            $this->headers['Disposition-Notification-To'] = $this->notificationTo;
        }

        $this->headers['Subject'] = $this->Subject;
        $this->headers['Date'] = date("r (T)");
        $this->headers['Message-ID'] = "<" . md5(uniqid((string) time())) . "@" . $this->Hostname . ">";
        $this->headers['X-Mailer'] = trim($this->XMailer);
        $this->headers['MIME-Version'] = '1.0';
        $this->headers['X-Priority'] = $this->Priority;
        $this->headers['Return-Path'] = $sender;
        $this->headers['Organization'] = $this->Organization ?: APP_NAME;

        return $this->headers;
    }

    /**
     * Send the email without attachments.
     *
     * @return array<string,array> Return an array of message headers and body.
     */
    private function sendMessage(): array 
    {
        $boundary = $this->getBoundaryId();

        $this->headers['Content-Type'] = "multipart/alternative; boundary=\"{$boundary}\"";
        
        $body = [];

        if($this->AltBody){
            $body[] = "--{$boundary}";
            $body[] = "Content-Type: text/plain; charset={$this->CharSet}";
            $body[] = "Content-Transfer-Encoding: quoted-printable";
            $body[] = "\r\n";
            $body[] = quoted_printable_encode($this->AltBody);
            $body[] = "\r\n";
        }

        $isNoneAscii = preg_match('/[^\x00-\x7F]/', $this->Body) === 1;
        $encoding = $isNoneAscii ? 'quoted-printable' : '7bit';

        $body[] = "--{$boundary}";
        $body[] = "Content-Type: {$this->contentType}; charset={$this->CharSet}";
        $body[] = "Content-Transfer-Encoding: {$encoding}";
        $body[] = "\r\n";
        $this->toBody($body, $isNoneAscii);
        $body[] = "\r\n";

        $body[] = "--{$boundary}--";
        $body[] = "\r\n";

        return $body;
    }

    /**
     * Send the email with attachments.
     *
     * @return array<string,array> Return an array of message headers and body.
     */
    private function sendAttachment(): array
    {
        $outerBoundary = $this->getBoundaryId();
        $innerBoundary = $this->getBoundaryId();

        $this->headers['Content-Type'] = "multipart/mixed; boundary=\"{$outerBoundary}\"";

        $body = [];
        $body[] = "--{$outerBoundary}";
        $body[] = "Content-Type: multipart/alternative; boundary=\"{$innerBoundary}\"";
        $body[] = "\r\n";

        if($this->AltBody){
            $body[] = "--{$innerBoundary}";
            $body[] = "Content-Type: text/plain; charset={$this->CharSet}";
            $body[] = "Content-Transfer-Encoding: quoted-printable";
            $body[] = "\r\n";
            $body[] = quoted_printable_encode($this->AltBody);
            $body[] = "\r\n";
        }

        $isNoneAscii = preg_match('/[^\x00-\x7F]/', $this->Body) === 1;
        $encoding = $isNoneAscii ? 'quoted-printable' : '7bit';

        $body[] = "--{$innerBoundary}";
        $body[] = "Content-Type: {$this->contentType}; charset={$this->CharSet}";
        $body[] = "Content-Transfer-Encoding: {$encoding}";
        $body[] = "\r\n";
        $this->toBody($body, $isNoneAscii);

        //$body[] = "\r\n";
        $body[] = "\r\n";
        $body[] = "--{$innerBoundary}--";
        $body[] = "\r\n";

        foreach ($this->attachments as $attachment) {
            $fileContent = chunk_split(base64_encode(get_content($attachment['path'])));

            $encoding = $attachment['encoding'] ?? 'base64';
            $disposition = $attachment['disposition'] ?? 'attachment';
            $name = $attachment['name'];
            $type = $attachment['type'] ?? null;

            if(!$type){
                $type = MIME::findType($attachment['path']) ?: 'application/octet-stream';
            }

            $body[] = "--{$outerBoundary}";
            $body[] = "Content-Type: {$type}; name=\"{$name}\"";
            $body[] = "Content-Transfer-Encoding: {$encoding}";
            $body[] = "Content-Disposition: {$disposition}; filename=\"{$name}\"";
            $body[] = "\r\n";
            $body[] = $fileContent;
        }

        $body[] = "--{$outerBoundary}--";
        $body[] = "\r\n";

        return $body;
    }

    /**
     * Parse and normalize address.
     * 
     * @param string $address 
     * @param string $name 
     * @param bool $auto
     * 
     * @return string
     */
    private function parse(string $address, string $name, bool $auto = false): string
    {
        if(!$auto && $name === ''){
            return $this->toAddress($address);
        }

        $name = $this->toName($name ?: $address);
        $address = $this->toAddress($address);

        return ($name !== '') ? "{$name} <{$address}>" : $address;
    }

    /**
     * Read SMTP connection response message.
     * 
     * @param string $name An optional identifier for logging or context.
     * @param int $seconds The maximin time to wait in seconds (default: `300`).
     * 
     * @return string Return the the SMTP response message.
     * @throws MailerException Throws if an error in development or exception is enabled.
     */
    protected function getLines(string $name = '', int $seconds = 300): string
    {
        if (!is_resource($this->connection)) {
            return '';
        }

        $response = '';
        $endtime = 0;
        stream_set_timeout($this->connection, $this->Timeout);

        if ($seconds > 0) {
            $endtime = time() + $seconds;
        }

        $read = [$this->connection];
        $write = null;

        while (is_resource($this->connection) && !feof($this->connection)) {

            $n = stream_select($read, $write, $write, $seconds);

            if ($n === false) {
                $error = "Failed to read from SMTP connection: {$name}";

                if (!PRODUCTION || $this->exceptions) {
                    throw new MailerException($error);
                }
    
                Logger::debug($error);
                break;
            }

            if (!$n) {
                break;
            }

            $str = fgets($this->connection, 515);
            $response .= $str;

            if (!isset($str[3]) || $str[3] === ' ' || $str[3] === "\r" || $str[3] === "\n") {
                break;
            }

            $info = stream_get_meta_data($this->connection);
            if ($info['timed_out']) {
                break;
            }

            if ($endtime && time() > $endtime) {
                break;
            }
        }

        return $response;
    }
}