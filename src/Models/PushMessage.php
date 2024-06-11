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

use Luminova\Exceptions\InvalidArgumentException;

final class PushMessage
{
    /**
     * @var int
     */
    public const DEFAULT = 1;

    /**
     * @var int
     */
    public const ANDROID = 2;

    /**
     * @var int
     */
    public const APN = 3;

    /**
     * @var int
     */
    public const WEBPUSH = 4;

    /**
     * @var array<string,mixed> $basic
     */
    private array $basic = [
        'data'          => [],
        'notification'  => [
            'title'     => '',
            'body'      => '',
            'image'     => ''
        ]
    ];

     /**
     * @var array<string,mixed> $payload
     */
    private array $payload = [
        'android'       => [],
        'apn'           => [],
        'data'          => [],
        'webpush'       => [],
        'notification'  => [
            'title'     => '',
            'body'      => '',
            'image'     => ''
        ]
    ];

    /**
     * @var array<string,mixed> $default
     */
    private array $default = [
        'platform'      => self::DEFAULT,
        'topic'         => '',
        'token'         => '',
        'conditions'    => '',
        'topics'        => [],
        'tokens'        => []
    ];

    /**
     *  Map additional fields directly if they exist in $setter.
     * 
     * @var array<string,bool> $fields
     */ 
    private static array $fields = [
        'priority'        => false, 
        'ttl'             => false, 
        'analytics_label' => false, 
        'headers'         => true, 
        'link'            => false, 
        'webpush'         => true, 
        'android'         => true, 
        'apns'            => true,  
        'fcm_options'     => true,
    ];

    /**
     * PushMessage constructor.
     *
     * @param array|null $setter An optional array to initialize from array.
     *      - token (string) Optional single notification token.
     *      - topic (string) Optional single notification topic.
     *      - tokens (array<int,string>) Optional multiple notification tokens.
     *      - data (array<string,mixed>) Optional data to send with the notification.
     *      - notification (array<string,mixed>) Notification payload information:
     *          - title (string) Notification title.
     *          - body (string) Notification message body.
     *          - image (string) Notification image URL.
     *          - icon (string) Notification app icon.
     *          - sound (string) Notification sound.
     *          - vibrate (array<int>) Notification vibration pattern.
     *          - click_action (string) Notification click action.
     *          - tag (string) Notification tag.
     *          - color (string) Notification color.
     * 
     * @see https://firebase.google.com/docs/cloud-messaging/admin/send-messages#apns_specific_fields
     * @see https://firebase.google.com/docs/cloud-messaging/admin/send-messages#android_specific_fields
     * @see https://firebase.google.com/docs/cloud-messaging/admin/send-messages#webpush_specific_fields
     */
    public function __construct(?array $setter = null)
    {
        $this->payload = $this->basic;
        $this->default['platform'] = $setter['platform'] ?? self::DEFAULT;
        $this->default['topic'] = $setter['topic'] ?? '';
        $this->default['topics'] = $setter['topics'] ?? [];
        $this->default['token'] = $setter['token'] ?? '';
        $this->default['conditions'] = $setter['conditions'] ?? '';
        $this->default['tokens'] = $setter['tokens'] ?? [];
        $this->setFromArray($setter);
    }

    /**
     * Add a configuration key-value pair to the payload.
     *
     * @param string $key The key to add.
     * @param mixed $value The value to associate with the key.
     * @param string|null $root Optional root key for nested payloads
     *      if NUll, the key will be store in payload root instead. and replace any existing key value.
     * 
     * @return self
     */
    public function add(string $key, mixed $value, ?string $root = null): self
    {
        if($root === null || $root === ''){
            $this->payload[$key] = $value;
            return $this;
        }

        if (!isset($this->payload[$root]) || !is_array($this->payload[$root])) {
            $this->payload[$root] = [];
        }

        if (isset($this->payload[$root][$key]) && is_array($this->payload[$root][$key]) && is_array($value)) {
            $this->payload[$root][$key] = array_merge_recursive(
                $this->payload[$root][$key], 
                $value
            );
            return $this;
        }

        if((self::$fields[$root] ?? false) === true && !is_array($value)){
            $this->payload[$root] = [];
        }

        $this->payload[$root][$key] = $value;
        return $this;
    }

    /**
     * Add a nested configuration key-value pair to the payload using dot (.) notation as a delimiter to represent the nested structure of keys.
     *
     * @param string $keys The dot-separated keys representing the nested structure.
     * @param mixed $value The value to associate with the nested keys.
     * 
     * @return self Returns the updated instance of the class, allowing method chaining.
     */
    public function addNested(string $keys, mixed $value): self
    {
        if($keys === ''){
            return $this;
        }

        $keys = explode('.', $keys);
        $cloneArray = &$this->payload; 

        foreach ($keys as $key) {
            if (!isset($cloneArray[$key])) {
                $tempArray[$key] = [];
            }
            $cloneArray = &$cloneArray[$key]; 
        }

        $cloneArray = $value;

        return $this;
    }

    /**
     * Add APN configuration key-value pair to the APN payload.
     *
     * @param string $key The key to add.
     * @param mixed $value The value to associate with the key.
     * @return self
     */
    public function addApn(string $key, mixed $value): self
    {
        return $this->add($key, $value, 'apns');
    }

    /**
     * Add Webpush configuration key-value pair to the Webpush payload.
     *
     * @param string $key The key to add.
     * @param mixed $value The value to associate with the key.
     * @return self
     */
    public function addWebpush(string $key, mixed $value): self
    {
        return $this->add($key, $value, 'webpush');
    }

    /**
     * Add Android configuration key-value pair to the Android payload.
     *
     * @param string $key The key to add.
     * @param mixed $value The value to associate with the key.
     * @return self
     */
    public function addAndroid(string $key, mixed $value): self
    {
        return $this->add($key, $value, 'android');
    }

    /**
     * Add a custom key-value pair to notification data object.
     *
     * @param string $key
     * @param string $value
     * 
     * @return self
     */
    public function addData(string $key, string $value): self
    {
        $this->payload['data'][$key] = $value;
        return $this;
    }

    /**
     * Add a key-value pair to the notification object.
     *
     * @param string $key
     * @param string $value
     * 
     * @return self
     */
    public function addNotification(string $key, mixed $value): self
    {
        $this->payload['notification'][$key] = $value;
        return $this;
    }

    /**
     * Set the title of the notification.
     *
     * @param string $title
     * @return self
     */
    public function setTitle(string $title): self
    {
        $this->payload['notification']['title'] = $title;
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
        $this->payload['notification']['body'] = $body;
        return $this;
    }

    /**
     * Set the image URL for the notification.
     *
     * @param string $url The image url to set.
     * @return self
     */
    public function setImageUrl(string $url): self
    {
        $this->payload['notification']['image'] = $url;
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
        $this->payload['notification']['icon'] = $icon;
        return $this;
    }

    /**
     * Set the sound for the notification.
     *
     * @param string $sound Notification sound e.g default.
     * @return self
     */
    public function setSound(string $sound): self
    {
        $this->payload['notification']['sound'] = $sound;
        return $this;
    }

    /**
     * Set the vibrate pattern for the notification.
     *
     * @param array $vibrate The vibrate pattern e.g. [200, 100, 200].
     * @return self
     */
    public function setVibration(array $vibrate): self
    {
        $this->payload['notification']['vibrate'] = $vibrate;
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
        $this->payload['notification']['tag'] = $tag;
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
        $this->payload['notification']['color'] = $color;
        return $this;
    }

    /**
     * Set the topic for the notification.
     *
     * @param string $analytic Set analytic label 
     * @return self
     */
    public function setAnalytic(string $analytic): self
    {
        $this->payload['analytics_label'] = $analytic;
        return $this;
    }

    /**
     * Set the priority for the notification.
     *
     * @param string $priority e.g normal.
     * @return self
     */
    public function setPriority(string $priority): self
    {
        $this->payload['priority'] = $priority;
        return $this;
    }

    /**
     * Set the ttl for the notification.
     *
     * @param string $ttl e.g. 3600s.
     * @return self
     */
    public function setTtl(string $ttl): self
    {
        $this->payload['ttl'] = $ttl;
        return $this;
    }

    /**
     * Add custom data to the notification.
     *
     * @param string $url
     * @return self
     */
    public function setLink(string $url): self
    {
        $this->payload['link'] = $url;
        return $this;
    }

    /**
     * Set click action, an activity with a matching intent filter is launched when a user clicks on the notification.
     *
     * @param string $action
     * @return self
     */
    public function setClickAction(string $action): self
    {
        $this->payload['notification']['click_action'] = $action;
        return $this;
    }

    /**
     * Sets the number of badge count this notification will add. 
     * This may be displayed as a badge count for launchers that support badging.
     *
     * @param int $count
     * @return self
     */
    public function setBadgeCount(int $count): self
    {
        $this->payload['notification']['notification_count'] = $count;
        return $this;
    }

    /**
     * Set the topic for the notification.
     *
     * @param string $topic
     * @return self
     */
    public function setTopic(string $topic): self
    {
        $this->default['topic'] = $topic;
        return $this;
    }

    /**
     * Set message topic conditions.
     *
     * @param string $conditions
     * @return self
     */
    public function setConditions(string $conditions): self
    {
        $this->default['conditions'] = $conditions;
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
        $this->default['tokens'] = $tokens;
        return $this;
    }

    /**
     * Set the token to send the push message to.
     *
     * @param string $token
     * @return self
     */
    public function setToken(string $token): self
    {
        $this->default['token'] = $token;
        return $this;
    }

    /**
     * Get the data of the notification.
     * 
     * @param int $platform The notification platform.
     * (PushMessage::ANDROID, PushMessage::APN, PushMessage::WEBPUSH or PushMessage::DEFAULT).
     * 
     * @return self
     */
    public function setPlatform(int $platform): self
    {
        $this->default['platform'] = $platform;
        return $this;
    }

    /**
     * Get the array of tokens to send the push message to.
     *
     * @return array
     */
    public function getTokens(): array
    {
        return $this->default['tokens'] ?? [];
    }

    /**
     * Get message topic conditions.
     *
     * @return string
     */
    public function getConditions(): string
    {
        return $this->default['conditions'] ?? '';
    }

    /**
     * Get the token to send the push message to.
     *
     * @return string
     */
    public function getToken(): string
    {
        return $this->default['token'] ?? '';
    }

    /**
     * Get the data of the notification.
     *
     * @return int
     */
    public function getPlatform(): int
    {
        return $this->default['platform'] ?? self::DEFAULT;
    }

    /**
     * Get the token to send the push message to.
     *
     * @return string
     */
    public function getPriority(): string
    {
        return $this->payload['priority'] ?? '';
    }

    /**
     * Get the title of the notification.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->payload['notification']['title'] ?? '';
    }

    /**
     * Get the body of the notification.
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->payload['notification']['body'] ?? '';
    }

    /**
     * Get the data of the notification.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->payload['data'] ?? [];
    }

    /**
     * Get the image URL of the notification.
     *
     * @return string
     */
    public function getImageUrl(): string
    {
        return $this->payload['notification']['image'] ?? '';
    }

    /**
     * Get the topic of the notification.
     *
     * @return string
     */
    public function getTopic(): string
    {
        return $this->payload['topic'] ?? 'test';
    }

    /**
     * Get the topic of the notification.
     *
     * @return string
     */
    public function getAnalytic(): string
    {
        return $this->payload['analytics_label'] ?? '';
    }

    /**
     * Get the data from notification.
     *
     * @return string
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload['notification'][$key] ?? $default;
    }

    /**
     * Get the data from notification.
     *
     * @return string
     */
    public function getPayload(?string $key = null): array
    {
        if($key === null){
            return $this->payload[$key];
        }

        return $this->payload;
    }

    /**
     * Set the payload from an array of configuration settings.
     *
     * @param array|null $setter The array of configuration settings.
     * @return void
     * @throws InvalidArgumentException
     */
    private function setFromArray(?array $setter = null): void 
    {
        if ($setter === null || $setter === []) {
            return;
        }
        
        $this->payload = array_merge(
            $this->basic,
            $setter['data'] ?? [],
            $setter['notification'] ?? [
                'notification' => [
                    'title' => $setter['title'] ?? '',
                    'body' => $setter['body'] ?? '',
                    'image' => $setter['image'] ?? ''
                ]
            ],
            $setter['apns'] ?? [],
            $setter['android'] ?? [],
            $setter['webpush'] ?? [],
        );
        
        foreach (self::$fields as $field => $requireArray) {
            if (isset($setter[$field])) {
                if($requireArray && !is_array($setter[$field])){
                    throw new InvalidArgumentException(sprintf('Invalid field "%s" value, array value is required.', $field));
                }
                $this->payload[$field] = $setter[$field];
            }
        }
    }

    /**
     * Convert the PushMessage instance to an array.
     * 242063
     * @return array<string,mixed> Return notification payload.
     */
    public function fromArray(string $type, string|array $to): array
    {
        $data = [];
        if($type !== 'instance'){
            $data[$type] = $to;
        }

        $platform = $this->getPlatform();

        switch ($platform) {
            case self::WEBPUSH:
                $data['webpush'] = [
                    'notification' => $this->payload['notification'] ?? [],
                    'fcm_options' => [
                        'link' => $this->payload['link'] ?? ''
                    ],
                    'headers' => $this->payload['headers'] ?? [],
                ];

                if (isset($this->payload['ttl'])) {
                    $data['webpush']['headers']['ttl'] = $this->payload['ttl'];
                }
                break;

            case self::ANDROID:
                $data['android'] = [
                    'notification' => $this->payload['notification'] ?? [],
                ];

                foreach (['ttl', 'priority', 'restricted_package_name'] as $key) {
                    if (isset($this->payload[$key])) {
                        $data['android'][$key] = $this->payload[$key];
                    }
                }
                break;

            case self::APN:
                $data['apns'] = [
                    'headers' => $this->payload['headers'] ?? [],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $this->payload['notification']['title'] ?? '',
                                'body' => $this->payload['notification']['body'] ?? '',
                            ]
                        ]
                    ],
                    'fcm_options' => [
                        'image' => $this->getImageUrl()
                    ]
                ];
             
                break;
        }

        if (isset($this->payload['analytics_label'])) {
            $data['fcm_options']['analytics_label'] = $this->payload['analytics_label'];
        }

        return array_merge_recursive($this->payload, $data);
    }
}