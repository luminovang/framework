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
namespace Luminova\Notifications\Models;

use Luminova\Exceptions\InvalidArgumentException;

final class Message
{
    /**
     * Default no specific platform.
     * 
     * @var string DEFAULT
     */
    public const DEFAULT = 'default';

    /**
     * Android specific platform.
     * 
     * @var string ANDROID
     */
    public const ANDROID = 'android';

    /**
     * IOS, APNs specific platform.
     * 
     * @var string APN
     */
    public const APN = 'apns';

    /**
     * Website, WebPush specific platform.
     * 
     * @var string WEBPUSH
     */
    public const WEBPUSH = 'webpush';

    /**
     * Indicate that payload is handled by notification class
     * 
     * @var string $platform
     */
    private string $platform = self::DEFAULT;

    /**
     * @var array<string,mixed> $payload
     */
    private array $payload = [
        'android'       => [],
        'apns'          => [],
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
     * @var array<string,mixed> $metadata
     */
    private array $metadata = [
        'platforms'     => [self::DEFAULT],
        'raw'           => false,
        'topic'         => '',
        'token'         => '',
        'conditions'    => '',
        'tokens'        => []
    ];

    /**
     *  Map additional fields directly if they exist in $setter.
     * 
     * @var array<string,string> FIELD_TYPES
     */ 
    private const FIELD_TYPES = [
        'priority'        => 'mixed', 
        'ttl'             => 'mixed',  
        'link'            => 'mixed',
        'analytics_label' => 'mixed', 
        'headers'         => 'array', 
        'webpush'         => 'array', 
        'android'         => 'array', 
        'apns'            => 'array',  
        'data'            => 'array',  
        'ntification'     => 'array',  
        'fcm_options'     => 'array',
    ];

    /**
     * Create a new message payload.
     *
     * @param array|null $options An optional array of notification configurations to initialize model from.
     *      - platform (string) Notification specific platform (default: `default`).
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
    public function __construct(?array $options = null)
    {
        $isRaw = (bool) ($options['raw'] ?? false);

        $this->metadata['raw'] = $isRaw;

        if($isRaw){
            $this->payload = $options;
            return;
        }

        $this->metadata['platforms']  = array_values(
            $options['platforms'] 
            ?? [$options['platform'] ?? self::DEFAULT]
        );
        $this->metadata['topic']      = $options['topic'] ?? '';
        $this->metadata['token']      = $options['token'] ?? '';
        $this->metadata['conditions'] = $options['conditions'] ?? '';
        $this->metadata['tokens']     = $options['tokens'] ?? [];
        $this->metadata['headers']    = $options['headers'] ?? [];

        $this->createFromArray($options);
    }

    /**
     * Create a new message payload from array.
     *
     * @param array<string,mixed> $configs An array to notification configurations.
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
     * @return self Returns instance of notification payload model.
     */
    public static function fromArray(array $configs): self 
    {
        return new self($configs);
    }

    /**
     * Determine whether the configuration name is present.
     *
     * @param string|'apns'|'android'|'webpush'|'data'|'notification'|'headers'|'fcm_options'|'analytics_label' $name
     * @return bool True if the config option configuration exists.
     */
    public function has(string $name): bool
    {
        $value = $this->payload[$name] 
            ?? $this->metadata[$name] 
            ?? null;

        return !empty($value);
    }

    /**
     * Determine whether the Android configuration is present.
     *
     * @return bool True if the Android configuration exists.
     */
    public function hasAndroid(): bool
    {
        return $this->has('android');
    }

    /**
     * Determine whether the Web Push configuration is present.
     *
     * @return bool True if the Web Push configuration exists.
     */
    public function hasWebpush(): bool
    {
        return $this->has('webpush');
    }

    /**
     * Determine whether the APNs configuration is present.
     *
     * @return bool True if the APNs configuration exists.
     */
    public function hasApn(): bool
    {
        return $this->has('apns');
    }

    /**
     * Determine whether custom data is present.
     *
     * @return bool True if the data payload exists.
     */
    public function hasData(): bool
    {
        return $this->has('data');
    }

    /**
     * Determine whether a notification payload is present.
     *
     * @return bool True if the notification payload exists.
     */
    public function hasNotification(): bool
    {
        return $this->has('notification');
    }

    /**
     * Determine whether FCM options are present.
     *
     * @return bool True if the FCM options exist.
     */
    public function hasFcmOption(): bool
    {
        return $this->has('fcm_options');
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

        if((self::FIELD_TYPES[$root] ?? 'mixed') === 'array' && !is_array($value)){
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
     * Add APNs specific configuration key-value pair to notification payload.
     *
     * @param string $key The key to add.
     * @param mixed $value The value to associate with the key.
     * 
     * @return self Return notification message model instance.
     */
    public function addApns(string $key, mixed $value): self
    {
        return $this->add($key, $value, self::APN);
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
        return $this->add($key, $value, self::WEBPUSH);
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
        return $this->add($key, $value, self::ANDROID);
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
        if($value === ''){
            return $this;
        }
        
        $this->payload['notification'][$key] = $value;
        
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
        $this->metadata['headers'] = array_merge(
            $this->metadata['headers'] ?? [],
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
        return $this->addNotification('vibrate', $vibrate);
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
        $this->metadata['analytics_label'] = $analytic;
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
        $this->metadata['priority'] = $priority;
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
        $this->metadata['ttl'] = $ttl;
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
        $fcmOptions = $this->payload[self::WEBPUSH]['fcm_options'] ?? [];

        if($fcmOptions === []){
            $this->payload[self::WEBPUSH]['fcm_options'] = [
                'link' =>  $url
            ];

            return $this;
        }
 
        $this->payload[self::WEBPUSH]['fcm_options']['link'] = $url;
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
        return $this->addNotification('notification_count', $count);
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
        $this->payload[self::ANDROID]['restricted_package_name'] = $package;
        return $this;
    }

    /**
     * Determine if notification should be sent raw from payload array.
     *
     * @return bool Return true if should send notification as raw, otherwise false.
     */
    public function isRaw(): bool
    {
        return $this->metadata['raw'] ?? false;
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
        $this->metadata['raw'] = $raw;
        
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
        $this->metadata['topic'] = $topic;
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
        $this->metadata['conditions'] = $conditions;
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
        $this->metadata['tokens'] = $tokens;
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
        $this->metadata['token'] = $token;
        return $this;
    }

    /**
     * Set the notification platform type.
     * 
     * @param string $platform The notification platform.
     *      - Message::DEFAULT) - Default notification without platform specific. 
     *      - Message::ANDROID  - Android platform. 
     *      - Message::APN      - APNs platform. 
     *      - Message::WEBPUSH  - WebPush platform.  
     *      
     * 
     * @return self Return notification message model instance.
     */
    public function setPlatform(string $platform): self
    {
        $platforms = $this->metadata['platforms'] ?? [];
        $platforms[] = $platform;

        $this->metadata['platforms'] = array_unique($platforms);
        return $this;
    }

    /**
     * Get the array of notification device tokens.
     *
     * @return array Return the array of notification device tokens.
     */
    public function getTokens(): array
    {
        return $this->metadata['tokens'] ?? [];
    }

    /**
     * Get notification topic conditional expression.
     *
     * @return string Return notification topic expression.
     */
    public function getConditions(): string
    {
        return $this->metadata['conditions'] ?? '';
    }

    /**
     * Get notification token.
     *
     * @return string Return notification token.
     */
    public function getToken(): string
    {
        return $this->metadata['token'] ?? '';
    }

    /**
     * Get notification platform id.
     *
     * @return array Returns the notification platform id.
     */
    public function getPlatforms(): array
    {
        return $this->metadata['platforms'] 
            ?? [self::DEFAULT];
    }

    /**
     * Get notification priority.
     *
     * @return string Return notification priority.
     */
    public function getPriority(): string
    {
        return $this->metadata['priority'] ?? '';
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
        return $this->metadata['topic'] ?? 'test';
    }

    /**
     * Get notification analytic label.
     *
     * @return string Return notification analytics label.
     */
    public function getAnalytic(): string
    {
        return $this->metadata['analytics_label'] ?? '';
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
     * Set the payload from an array of configuration settings.
     *
     * @param array|null $options The array of configuration settings.
     * 
     * @return void
     * @throws InvalidArgumentException Throws if $setter field value has an invalid value.
     */
    private function createFromArray(?array $options = null): void 
    {
        if ($options === null || $options === []) {
            return;
        }

        static $attributes = [
            'platforms'     => true,
            'raw'           => true,
            'topic'         => true,
            'token'         => true,
            'conditions'    => true,
            'tokens'        => true,
            'headers'       => true
        ];

        static $payload = [
            self::WEBPUSH   => true,
            self::ANDROID   => true,
            self::APN       => true,
            'data'          => true,
            'notification'  => true
        ];

        foreach($options as $name => $option){
            if(isset($attributes[$name])){
                continue;
            }

            if(!isset($payload[$name])){
                $this->metadata['attributes'][$name] = $option;
                continue;
            }

            if(!isset(self::FIELD_TYPES[$name])){
                $this->payload[$name] = $option;
                continue;
            }

            $isArrayRequired = self::FIELD_TYPES[$name] === 'array';

            if($isArrayRequired && !is_array($option)){
                throw new InvalidArgumentException(sprintf(
                    'Invalid field "%s" value, array value is required.', $name
                ));
            }

            $this->payload[$name] = $option;
        }
    }

    /**
     * Determine if building payload for internal notification class.
     * 
     * @param string $platform Whether notification payload is handled internally by notification class.
     * 
     * @return self Return instance of notification class.
     * @internal Handled internally for notification class.
     */
    public function forPlatform(string $platform): self 
    {
        $this->platform = $platform;
        return $this;
    }

    /**
     * Convert message payload to array.
     * 
     * This method process notification payload and return 
     * an array representing full notification configurations.
     * 
     * @return array<string,mixed> Return notification payload.
     */
    public function toArray(): array
    {
        if($this->isRaw()){
            $data = $this->payload;

            $data['headers'] = array_merge(
                $data['headers'] ?? [], 
                $this->metadata['headers'] ?? []
            );

            return self::cleanArray(array_merge(
                $data,
                $this->metadata['attributes'] ?? []
            ));
        }

        static $data = [];

        if(isset($data[$this->platform])){
            return $data[$this->platform];
        }

        foreach($this->getPlatforms() as $platform){
            $data[$platform] = match ($platform) {
                self::WEBPUSH => $this->fromWebpush(),
                self::ANDROID => $this->fromAndroid(),
                self::APN     => $this->fromApns(),
                self::DEFAULT => self::cleanArray(array_merge([
                    'notification'  => $this->fromNotification(),
                    'data'          => $this->getData(),
                    self::WEBPUSH   => $this->fromWebpush(),
                    self::ANDROID   => $this->fromAndroid(),
                    self::APN       => $this->fromApns(),
                    //'fcm_options'   => $this->payload['fcm_options'],
                ], $this->metadata['attributes'] ?? [])),
                default      => []
            };
        }

        return $data[$this->platform] 
            ?? $data[self::DEFAULT][$this->platform] 
            ?? [];
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    private function fromNotification(): array 
    {
        $data = $this->payload['notification'] ?? [];

        $data['title'] ??= $this->payload['notification']['title'] ?? '';
        $data['body']  ??= $this->payload['notification']['body'] ?? '';
        $data['image'] ??= $this->payload['notification']['image'] ?? '';
        $data['icon']  ??= $this->payload['notification']['icon'] ?? '';

        return self::cleanArray($data);
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    private function fromAndroid(): array 
    {
        $data = $this->payload[self::ANDROID] ?? [
            'notification' => [],
            'fcm_options'  => []
        ];

        $data['ttl'] ??= $this->metadata['ttl'] ?? null;
        $data['priority'] ??= $this->metadata['priority'] ?? null;

        $data['notification'] = array_merge(
            $data['notification'] ?? [],
            $this->fromNotification()
        );

        $data['fcm_options']['analytics_label'] ??= ($this->metadata['analytics_label'] ?? '');

        return self::cleanArray($data, true);
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    private function fromApns(): array 
    {
        $data = $this->payload[self::APN] ?? [
            'payload' => [
                'aps' => [
                    'alert' => []
                ]
            ],
            'headers'     => [],
            'fcm_options' => [],
        ];

        $data['headers'] = array_merge(
            $data['headers'] ?? [], 
            $this->metadata['headers'] ?? []
        );

        $data['headers']['apns-priority'] ??= $this->metadata['priority'] ?? null;

        $data['fcm_options'] ??= $this->payload['fcm_options'] ?? [];

        $data['payload']['aps'] ??= [];
        $data['payload']['aps']['alert'] ??= [];

        $data['fcm_options']['image'] ??= $this->getImageUrl();
        $data['fcm_options']['analytics_label'] ??= ($this->metadata['analytics_label'] ?? '');


        $data['payload']['aps']['alert']['title'] 
            ??= $this->payload['notification']['title'] ?? '';

        $data['payload']['aps']['alert']['body']  
            ??= $this->payload['notification']['body'] ?? '';

        return self::cleanArray($data, true);
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    private function fromWebpush(): array
    {
        $data = $this->payload[self::WEBPUSH] ?? [
            'notification'  => [
                'requireInteraction' => true,
                'silent'             => false,
                'renotify'           => true,
                'dir'                => 'auto',
            ],
            'headers'       => [],
            'options'       => [],
            'actions'       => [],
            'fcm_options'   => []
        ];

        $data['headers'] = array_merge(
            $data['headers'] ?? [], 
            $this->metadata['headers'] ?? []
        );

        $data['notification'] = array_merge(
            $data['notification'] ?? [],
            $this->fromNotification()
        );

        $data['headers']['ttl'] ??= (string) ($this->metadata['ttl'] ?? '');
        $data['fcm_options']['analytics_label'] ??= ($this->metadata['analytics_label'] ?? '');

        unset($data['headers']['apns-priority']);

        return self::cleanArray($data, true);
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @param boolean $deep
     * @return array
     */
    private static function cleanArray(array $data, bool $deep = false): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                if ($value === []) {
                    unset($data[$key]);
                    continue;
                }

                if(!$deep){
                    continue;
                }

                $value = self::cleanArray($value, $deep);

                if ($value === []) {
                    unset($data[$key]);
                }
            } elseif ($value === null || $value === '') {
                unset($data[$key]);
            }
        }

        return $data;
    }
}