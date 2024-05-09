<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Models;

class PushMessage
{
    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $body;

    /**
     * @var array
    */
    private $tokens = [];

    /**
     * @var array
     */
    private $data = [];

    /**
     * @var array
     */
    private $notification = [];

    /**
     * PushMessage constructor.
     *
     * @param string $type (Optional) The type of push message.
     */
    public function __construct(string $type = 'ids')
    {
    }

    /**
     * Set the title of the notification.
     *
     * @param string $title
     * @return self
     */
    public function setTitle(string $title): self
    {
        $this->notification["title"] = $title;
        return $this;
    }

    /**
     * Set the body of the notification.
     *
     * @param string $body
     * @return self
     */
    public function setBody(string $body): self
    {
        $this->notification["body"] = $body;
        return $this;
    }

    /**
     * Set the icon for the notification.
     *
     * @param string $icon
     * @return self
     */
    public function setIcon(string $icon): self
    {
        $this->notification["icon"] = $icon;
        return $this;
    }

    /**
     * Set the sound for the notification.
     *
     * @param string $sound
     * @return self
     */
    public function setSound(string $sound = 'default'): self
    {
        $this->notification["sound"] = $sound;
        return $this;
    }

    /**
     * Set the vibrate pattern for the notification.
     *
     * @param array $vibrate
     * @return self
     */
    public function setVibrate(array $vibrate = [200, 100, 200]): self
    {
        $this->notification["vibrate"] = $vibrate;
        return $this;
    }

    /**
     * Set the click action for the notification.
     *
     * @param string $click_action
     * @return self
     */
    public function setClickAction(string $click_action): self
    {
        $this->notification["click_action"] = $click_action;
        return $this;
    }

    /**
     * Set a tag for the notification.
     *
     * @param string $tag
     * @return self
     */
    public function setTag(string $tag): self
    {
        $this->notification["tag"] = $tag;
        return $this;
    }

    /**
     * Set the color for the notification.
     *
     * @param string $color
     * @return self
     */
    public function setColor(string $color): self
    {
        $this->notification["color"] = $color;
        return $this;
    }

    /**
     * Add custom data to the notification.
     *
     * @param string $key
     * @param string $value
     * @return self
     */
    public function addData(string $key, string $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Set an array of tokens to send the push message to.
     *
     * @param array $tokens
     * @return self
     */
    public function setTokens(array $tokens): self
    {
        $this->tokens = $tokens;
        return $this;
    }

    /**
     * Get the array of tokens to send the push message to.
     *
     * @return array
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * Get the title of the notification.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->notification["title"];
    }

    /**
     * Get the body of the notification.
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->notification["body"];
    }

    /**
     * Get the data of the notification.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Convert the PushMessage instance to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'notification' => $this->notification,
            'data' => $this->data,
        ];
    }
}