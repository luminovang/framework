<?php
/**
 * Luminova Framework Urchin Tracking Module Client Model.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http\Attribution;

final class UTMClient
{
    /**
     * UTM Client data.
     *
     * @param array $data The UTM client data array.
     */
    public function __construct(private array $data){}

    /**
     * Get the unique client identifier.
     *
     * @return string The client ID.
     */
    public function getClientId(): string
    {
        return $this->data['_meta']['client_id'] ?? '';
    }

    /**
     * Get the IP address of the client.
     *
     * @return string|null The IP address or null if not available.
     */
    public function getIp(): ?string
    {
        return $this->data['_meta']['ip'] ?? null;
    }

    /**
     * Get the URI accessed by the client.
     *
     * @return string|null The URI or null if not available.
     */
    public function getUri(): ?string
    {
        return $this->data['_meta']['uri'] ?? null;
    }

    /**
     * Get the user agent of the client.
     *
     * @return string|null The user agent or null if not available.
     */
    public function getUserAgent(): ?string
    {
        return $this->data['_meta']['user_agent'] ?? null;
    }

    /**
     * Get the campaign ID associated with the client.
     *
     * @return int|null The campaign ID or null if not available.
     */
    public function getCampaignId(): ?int
    {
        return $this->data['_meta']['campaign_id'] ?? null;
    }

    /**
     * Get the creation timestamp of the UTM data.
     *
     * @return int|null The creation timestamp or null if not available.
     */
    public function getCreatedAt(): ?int
    {
        return $this->data['_meta']['created_at'] ?? null;
    }

    /**
     * Get the referer URL of the client.
     *
     * @return string|null The referer URL or null if not available.
     */
    public function getReferer(): ?string
    {
       return $this->data['_meta']['referer'] ?? null;
    }

    /**
     * Get the expiration timestamp of the UTM data.
     *
     * @return int|null The expiration timestamp or null if not available.
     */
    public function getExpiresAt(): ?int
    {
        return $this->data['_meta']['expires_at'] ?? null;
    }

    /**
     * Get the number of hits for the UTM data.
     *
     * @return int The number of hits.
     */
    public function getHits(): int
    {
        return $this->data['_meta']['hits'] ?? 0;
    }

    /**
     * Get UTM campaign parameter data.
     * 
     * @return string|null The UTM campaign parameter value or null if not available.
     */
    public function getCampaign(): ?string
    {
        return $this->data['utm_campaign'] ?? null;
    }

    /**
     * Get UTM medium parameter data.
     * 
     * @return string|null The UTM medium parameter value or null if not available.
     */
    public function getMedium(): ?string
    {
        return $this->data['utm_medium'] ?? null;
    }

    /**
     * Get UTM source parameter data.
     * 
     * @return string|null The UTM source parameter value or null if not available.
     */
    public function getSource(): ?string
    {
        return $this->data['utm_source'] ?? null;
    }

    /**
     * Get UTM term parameter data.
     * 
     * @return string|null The UTM term parameter value or null if not available.
     */
    public function getTerm(): ?string
    {
        return $this->data['utm_term'] ?? null;
    }

    /**
     * Get UTM content parameter data.
     *
     * @return string|null The UTM content parameter value or null if not available.
     */
    public function getContent(): ?string
    {
        return $this->data['utm_content'] ?? null;
    }

    /**
     * Get all raw UTM metadata.
     * 
     * @return array<string,mixed> The raw UTM data array.
     */
    public function getMetadata(): array
    {
        return $this->data;
    }
}