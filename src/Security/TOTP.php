<?php 
/**
 * Luminova Framework Time-Based One-Time Password (TOTP) authentication.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Security;

use \Luminova\Security\Authenticator\Google;
use \Luminova\Interface\AuthenticatorInterface;
use \Luminova\Exceptions\EncryptionException;
use \Luminova\Exceptions\InvalidArgumentException;
use \Endroid\QrCode\{
    QrCode,
    Writer\PngWriter,
    Writer\SvgWriter
};

final class TOTP
{
    /**
     * Indicate Whether QrCode is installed.
     * 
     * @var bool|null $isQrCodeInstalled
     */
    private static ?bool $isQrCodeInstalled = null;

    /**
     * Initialize class.
     * 
     * @param AuthenticatorInterface The client instance used for TOTP operations.
     */
    private function __construct(private AuthenticatorInterface $client) {}

    /**
     * Factory method to create a TOTP instance.
     * 
     * @param AuthenticatorInterface|null $client Optional custom authenticator implementation. 
     *                                            Defaults to a Google Authenticator client.
     * @return static Return a new TOTP instance.
     */
    public static function create(?AuthenticatorInterface $client = null): self
    {
        return new self(
            $client ?? new Google(
                uniqid('default.') . '@' . APP_HOSTNAME,
                APP_NAME
            )
        );
    }

    /**
     * Get or set the secret key used for TOTP (Time-Based One-Time Password).
     *
     * This method allows you to either retrieve the current secret key or set a new one.
     * If a secret is provided, it will be set as the new secret key.
     * If no secret is provided, the current secret key will be returned.
     *
     * @param string|null $secret Optional. The secret key to set. 
     *                            If null, the method will retrieve the current secret.
     *
     * @return string|null Returns the newly set secret key if a new secret was provided,
     *                     or the current secret key if no new secret was given.
     *                     Returns null if no secret is set and none was provided.
     */
    public function secret(?string $secret = null): ?string
    {
        if ($secret) {
            $this->client->setSecret($secret);
            return $secret;
        }

        return $this->client->getSecret();
    }

    /**
     * Generate the QR code URL for the authenticator.
     * 
     * @return string Return the URL for the QR code.
     * @throws EncryptionException If called without a valid authentication secret.
     * 
     * @example - Generate the QR code URL:
     * 
     * ```php
     * $totp->secret('USER_SECRETE');
     * $url = $totp->generate();
     * ```
     */
    public function url(): string
    {
        return $this->client->getQRCodeUrl();
    }

    /**
     * Generate the QR code image for the TOTP setup.
     *
     * This method generates a QR code image for Time-Based One-Time Password (TOTP) setup.
     * It can create the image in different formats and sizes, either using an installed
     * QR code library or a fallback method using an external service.
     *
     * @param int $size The size of the QR code image in pixels. Default is 200.
     * @param string $format The desired format of the QR code image. 
     *                       Supported formats are 'png', 'svg', and 'base64'. Default is 'svg'.
     *
     * @return string Return the QR code image as HTML element, depending on the chosen format and generation method.
     *
     * @throws EncryptionException If the 'base64' format is requested but the QR code
     *                             library is not installed, or if called without a valid
     *                             authentication secret.
     * @example - Generate the QR code Image:
     * 
     * ```php
     * $totp->secret('USER_SECRETE');
     * echo $totp->image();
     * ```
     */
    public function image(int $size = 200, string $format = 'svg'): string
    {
        self::$isQrCodeInstalled ??= class_exists(QrCode::class);

        if ($format === 'base64' && self::$isQrCodeInstalled === false) {
            throw new EncryptionException(sprintf(
                'QR code dependency: "%s" is missing. To generate base64, run "composer require endroid/qr-code" to install dependency.',
                QrCode::class
            ));
        }

        return self::$isQrCodeInstalled 
            ? $this->generateQrCodeImage($size, $format) 
            : $this->generateQrCodeImageLink($size, $format);
    }

    /**
     * Retrieve the label for the TOTP account.
     * 
     * @return string Return the label for the account.
     */
    public function label(): string
    {
        return $this->client->getLabel();
    }

    /**
     * Retrieve the account identifier used for the TOTP.
     * 
     * @return string Return the account identifier.
     */
    public function account(): string
    {
        return $this->client->getAccount();
    }

    /**
     * Validate a TOTP code.
     * 
     * @param string $code The TOTP code to validate.
     * @param int $discrepancy The allowed time window (in steps) for validation. Default: 1.
     * @param int $timeStep The time step in seconds for TOTP generation. Default: 30.
     * 
     * @return bool Return true if the code is valid; otherwise, false.
     * @throws EncryptionException If called without a valid authentication secret or an invalid base32 character is found in secret.
     * 
     * @example - Verify Code:
     * 
     * ```php
     * $totp->secret('USER_SECRETE');
     * if($totp->validate('123456')){
     *      echo 'Valid';
     * }
     * ```
     */
    public function validate(string $code, int $discrepancy = 1, int $timeStep = 30): bool
    {
        return $this->client->verify($code, $discrepancy, $timeStep);
    }

    /**
     * Generate the QR code image URL or HTML for Google Authenticator.
     * This method uses `https://quickchart.io/qr?text=`
     *
     * @param int $size Image size  (default: 200).
     * @param string $format QR code image format (default: 'png').
     *          Support format `png`, `svg`.
     * 
     * @return string Return the URL for the QR code image.
     * @throws EncryptionException If called without a valid authentication secret.
     */
    private function generateQrCodeImageLink(int $size, string $format): string
    {
        return sprintf(
            "%s%s%s%s",
            '<img src="',
            "https://quickchart.io/qr?size={$size}&format={$format}&text=",
            rawurlencode($this->url()),
            '" class="auth-qr-code-image" alt="' . $this->label() . '" />',
        );
    }

    /**
     * Generate a QR code and output it as PNG or SVG.
     * 
     * @param int $pixelSize Size of each QR code "block" in pixels.
     * @param string $output Output format: 'png', 'svg', 'base64', etc.
     * 
     * @return string|null Return the generated QR code in the desired format.
     */
    private function generateQrCodeImage(int $size, string $output): string
    {
        $image = match ($output) {
            'svg' => (new SvgWriter())->write(new QrCode(data: $this->url(), size: $size))->getString(),
            'png', 'base64' => 'data:image/png;base64,' . base64_encode((new PngWriter())->write(
                new QrCode(data: $this->url(), size: $size)
            )->getString()),
            default => throw new InvalidArgumentException("Unsupported QRCode image format: {$output}. Use (png, svg or base64)"),
        };

        return ($output === 'svg') ? $image : sprintf(
            "%s%s%s",
            '<img src="',
            $image,
            '" class="auth-qr-code-image" alt="' . $this->label() . '" />',
        );
    }
}