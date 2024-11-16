<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Notifications\Models;

use \Luminova\Exceptions\InvalidArgumentException;

final class Message
{
    /**
     * Default no specific platform.
     * 
     * @var int DEFAULT
     */
    public const DEFAULT = 1;

    /**
     * Android specific platform.
     * 
     * @var int ANDROID
     */
    public const ANDROID = 2;

    /**
     * IOS, APNs specific platform.
     * 
     * @var int APN
     */
    public const APN = 3;

    /**
     * Website, WebPush specific platform.
     * 
     * @var int WEBPUSH
     */
    public const WEBPUSH = 4;

    /**
     * Indicate that payload is handled by notification class
     * 
     * @var bool $isInternal
     */
    private bool $isInternal = false;

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
        'headers'       => [],
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
        'raw'           => false,
        'topic'         => '',
        'token'         => '',
        'conditions'    => '',
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
     * Initialize new message model.
     *
     * @param array|null $setter An optional array to initialize model from.
     *      - platform (int) Notification specific platform (default: 1).
     *      - raw (bool) Send custom notification payload.
     *      - token (string) Optional single notification token.
     *      - topic (string) Optional single notification topic.
     *      - tokens (array<int,string>) Optional multiple notification tokens.
     *      - data (array<string,mixed>) Optional data to send with the notification.
     *      - android (array<string,mixed>) Android specific configuration.
     *      - apns (array<string,mixed>) APNs specific configuration.
     *      - webpush (array<string,mixed>) WebPush specific configuration.
     *      - headers (array<string,mixed>) Payload headers configuration.
     *      - fcm_options (array<string,mixed>) Optional firebase configurations.
     *      - notification (array<string,mixed>) Notification payload information:
     *         -  - title (string) Notification title.
     *         - - body (string) Notification message body.
     *         - - image (string) Notification image URL.
     * 
     * @see https://firebase.google.com/docs/cloud-messaging/admin/send-messages#apns_specific_fields
     * @see https://firebase.google.com/docs/cloud-messaging/admin/send-messages#android_specific_fields
     * @see https://firebase.google.com/docs/cloud-messaging/admin/send-messages#webpush_specific_fields
     */
    public function __construct(?array $setter = null)
    {
        $this->default['raw'] = $setter['raw'] ?? false;

        if($this->default['raw']){
            $this->payload = $setter;
            return;
        }

        $this->payload = $this->basic;
        $this->default['platform'] = $setter['platform'] ?? self::DEFAULT;
        $this->default['topic'] = $setter['topic'] ?? '';
        $this->default['token'] = $setter['token'] ?? '';
        $this->default['conditions'] = $setter['conditions'] ?? '';
        $this->default['tokens'] = $setter['tokens'] ?? [];
        $this->setFromArray($setter);
    }

    /**
     * Add a configuration key-value pair to the payload, it supports nested payload structures and can merge values recursively if needed.
     *
     * @param string $key The key to add.
     * @param mixed $value The value to associate with the key.
     * @param string|null $root Optional root key for nested payloads, if `NUll`, the key will be store in payload root instead. and replace any existing key value.
     * 
     * @return self Return notification message model instance.
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
     * @return self Return notification message model instance. Returns the updated instance of the class, allowing method chaining.
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
     * Add APNs specific configuration key-value pair to notification payload.
     *
     * @param string $key The key to add.
     * @param mixed $value The value to associate with the key.
     * 
     * @return self Return notification message model instance.
     */
    public function addApns(string $key, mixed $value): self
    {
        return $this->add($key, $value, 'apns');
    }

    /**
     * Add WebPush specific configuration key-value pair to the notification payload.
     *
     * @param string $key The key to add.
     * @param mixed $value The value to associate with the key.
     * 
     * @return self Return notification message model instance.
     */
    public function addWebpush(string $key, mixed $value): self
    {
        return $this->add($key, $value, 'webpush');
    }

    /**
     * Add Android specific configuration key-value pair to the notification payload.
     *
     * @param string $key The key to add.
     * @param mixed $value The value to associate with the key.
     * 
     * @return self Return notification message model instance.
     */
    public function addAndroid(string $key, mixed $value): self
    {
        return $this->add($key, $value, 'android');
    }

    /**
     * Add a custom key-value pair to notification data object.
     *
     * @param string $key The key to add.
     * @param string $value The value to associate with the key.
     * 
     * @return self Return notification message model instance.
     */
    public function addData(string $key, string $value): self
    {
        $this->payload['data'][$key] = $value;
        return $this;
    }

    /**
     * Add a key-value pair to the notification object.
     *
     * @param string $key The key to add.
     * @param string $value The value to associate with the key.
     * 
     * @return self Return notification message model instance.
     */
    public function addNotification(string $key, mixed $value): self
    {
        if($value !== ''){
            $this->payload['notification'][$key] = $value;
        }
        
        return $this;
    }

    /**
     * Set array of key-value pair to the notification object.
     * 
     * @param array<string,mixed> $notification The notification payload object.
     * 
     * @return self Return notification message model instance.
     */
    public function setNotification(array $notification): self
    {
        $this->payload['notification'] = array_merge(
            $this->payload['notification'] ?? [],
            $notification
        );
        return $this;
    }

    /**
     * Set FCM options. array of key-value pair to the `fcm_options` object.
     * 
     * @param array<string,mixed> $options The FCM options.
     * 
     * @return self Return notification message model instance.
     */
    public function setFcmOptions(array $options): self
    {
        $this->payload['fcm_options'] = array_merge(
            $this->payload['fcm_options'] ?? [],
            $options
        );
        return $this;
    }

    /**
     * Set payload headers. array of key-value pair to the `header` object.
     * 
     * @param array<string,mixed> $headers The payload array headers key-pair value.
     * 
     * @return self Return notification message model instance.
     */
    public function setHeaders(array $headers): self
    {
        $this->payload['headers'] = array_merge(
            $this->payload['headers'] ?? [],
            $headers
        );
        return $this;
    }

    /**
     * Set the display title for notification.
     *
     * @param string $title The notification title.
     * 
     * @return self Return notification message model instance.
     */
    public function setTitle(string $title): self
    {
        $this->payload['notification']['title'] = $title;
        return $this;
    }

    /**
     * Set the display body for notification.
     *
     * @param string $body Notification message body.
     * 
     * @return self Return notification message model instance.
     */
    public function setBody(string $body): self
    {
        $this->payload['notification']['body'] = $body;
        return $this;
    }

    /**
     * Set the image URL for notification.
     *
     * @param string $url The image url to set.
     * 
     * @return self Return notification message model instance.
     */
    public function setImageUrl(string $url): self
    {
        return $this->addNotification('image', $url);
    }

    /**
     * Set the icon for the notification.
     *
     * @param string $icon The notification icon.
     * 
     * @return self Return notification message model instance.
     */
    public function setIcon(string $icon): self
    {
        return $this->addNotification('icon', $icon);
    }

    /**
     * Set the sound for the notification.
     *
     * @param string $sound Notification sound.
     * 
     * @return self Return notification message model instance.
     */
    public function setSound(string $sound): self
    {
        return $this->addNotification('sound', $sound);
    }

    /**
     * Set the vibration pattern for the notification.
     *
     * @param array $vibrate The vibrate pattern e.g. [200, 100, 200].
     * 
     * @return self Return notification message model instance.
     */
    public function setVibration(array $vibrate): self
    {
        $this->payload['notification']['vibrate'] = $vibrate;
        return $this;
    }

    /**
     * Set a tag for the notification.
     *
     * @param string $tag The notification tag.
     * 
     * @return self Return notification message model instance.
     */
    public function setTag(string $tag): self
    {
        return $this->addNotification('tag', $tag);
    }

    /**
     * Set a color for the notification.
     *
     * @param string $color The notification color.
     * @return self Return notification message model instance.
     */
    public function setColor(string $color): self
    {
        return $this->addNotification('color', $color);
    }

    /**
     * Set the analytic label for the notification.
     *
     * @param string $analytic Set analytic label.
     * 
     * @return self Return notification message model instance.
     */
    public function setAnalytic(string $analytic): self
    {
        $this->payload['analytics_label'] = $analytic;
        return $this;
    }

    /**
     * Set the notification priority.
     *
     * @param string $priority The notification priority (e.g normal).
     * 
     * @return self Return notification message model instance.
     */
    public function setPriority(string $priority): self
    {
        $this->payload['priority'] = $priority;
        return $this;
    }

    /**
     * Set TTL for the notification.
     *
     * @param string $ttl The ttl (e.g. 3600s).
     * 
     * @return self Return notification message model instance.
     */
    public function setTtl(string $ttl): self
    {
        $this->payload['ttl'] = $ttl;
        return $this;
    }

    /**
     * Set a link to open when notification is clicked.
     *
     * @param string $url The notification action url.
     * 
     * @return self Return notification message model instance.
     */
    public function setLink(string $url): self
    {
        $this->payload['link'] = $url;
        return $this;
    }

    /**
     * Set click action, an activity with a matching intent filter is launched when a user clicks on the notification.
     *
     * @param string $action The notification intent action.
     * 
     * @return self Return notification message model instance.
     */
    public function setClickAction(string $action): self
    {
        return $this->addNotification('click_action', $action);
    }

    /**
     * Sets the number of badge count this notification will add. 
     * This may be displayed as a badge count for launchers that support badging.
     *
     * @param int $count The number of badge to add for this notification.
     * 
     * @return self Return notification message model instance.
     */
    public function setBadgeCount(int $count): self
    {
        $this->payload['notification']['notification_count'] = $count;
        return $this;
    }

    /**
     * Sets package restriction, the package name of your application where the registration token must match in order to receive the message.
     *
     * @param string $package The notification package restriction (e.g: com.app.name.foo).
     * 
     * @return self Return notification message model instance.
     */
    public function setPackage(string $package): self
    {
        $this->payload['restricted_package_name'] = $package;
        return $this;
    }

    /**
     * Determine if notification should be sent raw from payload array.
     *
     * @return bool Return true if should send notification as raw, otherwise false.
     */
    public function isRaw(): bool
    {
        return $this->default['raw'] ?? false;
    }

    /**
     * Set raw flag, to send notification raw from payload array, instead of building notification.
     * 
     * @param bool $raw Should send notification as raw, otherwise false.
     * 
     * @return self Return notification message model instance.
     */
    public function setRaw(bool $raw = true): self
    {
        $this->default['raw'] = $raw;
        
        return $this;
    }

    /**
     * Set the notification topic to use when called `channel` method.
     *
     * @param string $topic The notification topic name.
     * 
     * @return self Return notification message model instance.
     */
    public function setTopic(string $topic): self
    {
        $this->default['topic'] = $topic;
        return $this;
    }

    /**
     * Set the notification topic conditional expression to use when called `condition` method.
     *
     * @param string $conditions The conditional expression.
     * 
     * @return self Return notification message model instance.
     */
    public function setConditions(string $conditions): self
    {
        $this->default['conditions'] = $conditions;
        return $this;
    }

    /**
     * Set the notification device tokens to use when called `broadcast` method.
     *
     * @param array<int,string> $tokens The device notification tokens.
     * 
     * @return self Return notification message model instance.
     */
    public function setTokens(array $tokens): self
    {
        $this->default['tokens'] = $tokens;
        return $this;
    }

    /**
     * Set the notification device token to use when called `send` method.
     *
     * @param string $token The notification device token.
     * 
     * @return self Return notification message model instance.
     */
    public function setToken(string $token): self
    {
        $this->default['token'] = $token;
        return $this;
    }

    /**
     * Set the notification platform type.
     * 
     * @param int $platform The notification platform.
     *      - Message::DEFAULT) (1) - Default notification without platform specific. 
     *      - Message::ANDROID (2) - Android platform. 
     *      - Message::APN (3) - APNs platform. 
     *      - Message::WEBPUSH (4) - WebPush platform.  
     *      
     * 
     * @return self Return notification message model instance.
     */
    public function setPlatform(int $platform): self
    {
        $this->default['platform'] = $platform;
        return $this;
    }

    /**
     * Get the array of notification device tokens.
     *
     * @return array Return the array of notification device tokens.
     */
    public function getTokens(): array
    {
        return $this->default['tokens'] ?? [];
    }

    /**
     * Get notification topic conditional expression.
     *
     * @return string Return notification topic expression.
     */
    public function getConditions(): string
    {
        return $this->default['conditions'] ?? '';
    }

    /**
     * Get notification token.
     *
     * @return string Return notification token.
     */
    public function getToken(): string
    {
        return $this->default['token'] ?? '';
    }

    /**
     * Get notification platform id.
     *
     * @return int Returns the notification platform id.
     */
    public function getPlatform(): int
    {
        return $this->default['platform'] ?? self::DEFAULT;
    }

    /**
     * Get notification priority.
     *
     * @return string Return notification priority.
     */
    public function getPriority(): string
    {
        return $this->payload['priority'] ?? '';
    }

    /**
     * Get notification title.
     *
     * @return string Return notification title.
     */
    public function getTitle(): string
    {
        return $this->payload['notification']['title'] ?? '';
    }

    /**
     * Get notification body.
     *
     * @return string Return notification body.
     */
    public function getBody(): string
    {
        return $this->payload['notification']['body'] ?? '';
    }

    /**
     * Get notification custom data.
     *
     * @return array<string,mixed> Returns the notification data.
     */
    public function getData(): array
    {
        return $this->payload['data'] ?? [];
    }

    /**
     * Get notification image url.
     *
     * @return string Return notification image url.
     */
    public function getImageUrl(): string
    {
        return $this->payload['notification']['image'] ?? '';
    }

    /**
     * Get notification channel topic.
     *
     * @return string Returns notification channel topic.
     */
    public function getTopic(): string
    {
        return $this->default['topic'] ?? 'test';
    }

    /**
     * Get notification analytic label.
     *
     * @return string Return notification analytics label.
     */
    public function getAnalytic(): string
    {
        return $this->payload['analytics_label'] ?? '';
    }

    /**
     * Get key from notification object.
     *
     * @param $key Key to retrieve.
     * @param $default Default value.
     * 
     * @return mixed Return notification key value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload['notification'][$key] ?? $default;
    }

    /**
     * Get notification payload or a specific key from payload.
     *
     * @param string $key Optional key to retrieve from payload.
     * 
     * @return mixed Return array of notification payload or value from passed key.
     */
    public function getPayload(?string $key = null): mixed
    {
        if($key === null){
            return $this->payload[$key];
        }

        return $this->payload;
    }

    /**
     * Retrieve notification platform name.
     * 
     * @return string Return platform name.
     */
    public function getPlatformName(): string 
    {
        return match($this->getPlatform()){
            self::WEBPUSH => 'webpush',
            self::ANDROID => 'android',
            self::APN => 'apns',
            default => 'default'
        };
    }

    /**
     * Set the payload from an array of configuration settings.
     *
     * @param array|null $setter The array of configuration settings.
     * 
     * @return void
     * @throws InvalidArgumentException Throws if $setter field value has an invalid value.
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
            $setter['headers'] ?? [],
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
     * Determine if building payload for internal notification class.
     * 
     * @param bool $builder Weather notification payload is handled internally by notification class.
     * 
     * @return self Return instance of notification class.
     * @internal Handled internally for notification class.
     */
    public function isInternal(bool $internal = true): self 
    {
        $this->isInternal = $internal;
        return $this;
    }

    /**
     * Process notification payload and return an array representing full notification configurations.
     * 
     * @return array<string,mixed> Return notification payload.
     */
    public function fromArray(): array
    {
        if($this->isRaw()){
            return $this->payload;
        }

        $data = [];
        $platform = $this->getPlatform();

        switch ($platform) {
            case self::WEBPUSH:
                $data['webpush'] = [
                    'notification' => $this->payload['notification'] ?? [],
                    'headers' => $this->payload['headers'] ?? [],
                    'fcm_options' => []
                ];

                if (isset($this->payload['link'])) {
                    $data['webpush']['fcm_options']['link'] = $this->payload['link'];
                }
                
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
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $this->payload['notification']['title'] ?? '',
                                'body' => $this->payload['notification']['body'] ?? '',
                            ]
                        ]
                    ],
                    'headers' => [],
                    'fcm_options' => [],
                ];

                $image = $this->getImageUrl();
                if($image){
                    $data['apns']['fcm_options']['image'] = ($data['apns']['fcm_options']['image'] ?? $image);
                }
             
                break;
        }

        $platformName = $this->getPlatformName();

        if($this->isInternal && $platformName !== 'default'){

            if (isset($this->payload['analytics_label'])) {
                $data[$platformName]['fcm_options']['analytics_label'] = $this->payload['analytics_label'];
            }

            $clone = $this->payload;
            $specific = $clone[$platformName] ?? [];
            unset($clone[$platformName]);

            return array_merge_recursive(
                $specific, 
                $data[$platformName],
                $clone
            );
        }

        if (isset($this->payload['analytics_label'])) {
            $data['fcm_options']['analytics_label'] = $this->payload['analytics_label'];
        }

        return array_merge_recursive(
            $this->payload, 
            $data
        );
    }
}