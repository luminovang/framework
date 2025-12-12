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
namespace Luminova\Notifications\Firebase;

use \Exception;
use Luminova\Luminova;
use \Kreait\Firebase\Factory;
use Luminova\Interface\LazyObjectInterface;
use \Kreait\Firebase\Contract\Messaging;
use Luminova\Exceptions\RuntimeException;
use Luminova\Notifications\Models\Message;
use \Kreait\Firebase\Messaging\Notification as Notifier;
use \Kreait\Firebase\Messaging\Message as MessageCaster;
use \Kreait\Firebase\Messaging\{
    AndroidConfig,
    CloudMessage,
    WebPushConfig,
    ApnsConfig,
    FcmOptions,
    MessageTarget,
    RawMessageFromArray,
    MulticastSendReport
};

class Notification implements LazyObjectInterface
{
    /**
     * Notification factory.
     * 
     * @var Factory|null $factory 
     */
    private ?Factory $factory = null;

    /**
     * Notification instance.
     * 
     * @var self|null $instance 
     */
    private static ?self $instance = null;

    /**
     * Notification response report.
     * 
     * @var MulticastSendReport|array|null $report 
     */
    private MulticastSendReport|array|null $report = null;

    /**
     * Initialize the Firebase Cloud Messaging notification service.
     *
     * The account may be:
     * - A service account filename relative to `/writeable/credentials/`.
     * - A service account configuration array.
     * - A service account configuration JSON string.
     * - An initialized {@see Factory} instance.
     *
     * @param Factory|string|array $account Service account configuration.
     *
     * @throws RuntimeException If the Firebase Factory cannot be created or
     *                          the service account file is unavailable.
     */
    public function __construct(Factory|string|array $account = 'ServiceAccount.json')
    {
        $this->factory = ($account instanceof Factory)
            ? $account
            : self::factory($account);
    }

    /**
     * Get the shared Firebase Cloud Messaging notification instance.
     *
     * @param Factory|string|array $account Service account configuration.
     *
     * @return self The shared notification instance.
     *
     * @throws RuntimeException If the Firebase Factory cannot be created or
     *                          the service account file is unavailable.
     */
    public static function getInstance(
        Factory|string|array $account = 'ServiceAccount.json'
    ): self 
    {
        return self::$instance ??= new self($account);
    }

    /**
     * Create a Firebase Factory using the specified service account.
     *
     * The account may be an initialized {@see Factory} instance, a service
     * account configuration array, a JSON configuration string, or a filename.
     * Relative filenames are resolved from `/writeable/credentials/`.
     *
     * @param string|array $account Service account filename, JSON configuration,
     *                              or configuration array.
     *
     * @return Factory The configured Factory instance.
     *
     * @throws RuntimeException If the service account file cannot be found or
     *                          the account value is invalid.
     */
    public static function factory(
        string|array $account = 'ServiceAccount.json'
    ): Factory
    {
        if (is_array($account)) {
            return (new Factory())->withServiceAccount($account);
        }

        if (trim($account) === '') {
            throw new RuntimeException(
                'Invalid service account. Expected a filename, JSON configuration, or array configuration.'
            );
        }

        if (!str_starts_with(ltrim($account), '{')) {
            $credentials = Luminova::root('/writeable/credentials/', $account);

            $account = is_file($account)
                ? $account
                : $credentials;

            if (!is_file($account)) {
                throw new RuntimeException(sprintf(
                    'Firebase service account file not found: %s.',
                    $account
                ));
            }
        }

        return (new Factory())->withServiceAccount($account);
    }

    /**
     * Get Firebase Factory instance from the specified service account.
     *
     * @return Factory The configured Firebase Factory instance.
     */
    public function getFactory(): Factory 
    {
        return $this->factory;
    }

    /**
     * Send a notification to a specific device by token.
     * 
     * The method accepts either a Message object or an array containing the notification payload.
     *
     * @param Message|array<string,mixed> $config The notification payload.
     * @param bool $validateOnly Optional. If set to true, the message 
     *      will only be validated without sending.
     *
     * @return self Return instance of luminova firebase notification class.
     * @throws RuntimeException If token is not valid or an error occurred while sending notification.
     */
    public function send(Message|array $config, bool $validateOnly = false): self
    {
        $this->report = null;
        $message = null;
        
        try {
            $config = ($config instanceof Message) 
                ? $config 
                : Message::fromArray($config);

            if ($config instanceof Message) {
                if($config->isRaw()){
                    $message = $this->rawMessage($config);
                }elseif($config->getToken() !== ''){
                    $message = $this->message(
                        MessageTarget::TOKEN, 
                        $config, 
                        $config->getToken()
                    );
                }

                if($message instanceof MessageCaster){
                    $this->report = $this->messaging()
                        ->send($message, $validateOnly);

                    return $this;
                }
            }

        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        throw new RuntimeException(sprintf(
            "Invalid input: method %s expected a %s object or an array with a 'topic' key token.",
            __METHOD__,
            Message::class
        ));
    }

    /**
     * Send a notification to a topic.
     * 
     * The method accepts either a Message object or an array containing the notification payload.
     *
     * @param Message|array<string,mixed> $config The notification payload.
     * @param bool $validateOnly Optional. If set to true, the message will only be validated without sending.
     *
     * @return self Return instance of luminova firebase notification class.
     * @throws RuntimeException If the topic is not provided correctly.
     */
    public function channel(Message|array $config, bool $validateOnly = false): self
    {
        $this->report = null;
        $message = null;
        
        try {
            $config = ($config instanceof Message) 
                ? $config 
                : Message::fromArray($config);

            if ($config instanceof Message) {
                if($config->isRaw()){
                    $message = $this->rawMessage($config);
                }elseif($config->getTopic() !== ''){
                    $message = $this->message(
                        MessageTarget::TOPIC, 
                        $config, 
                        $config->getTopic()
                    );
                }

                if($message instanceof MessageCaster){
                    $this->report = $this->messaging()
                        ->send($message, $validateOnly);
                        
                    return $this;
                }
            }

        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        throw new RuntimeException(sprintf(
            "Invalid input: method %s expected a %s object or an array with a 'topic' key token.",
            __METHOD__,
            Message::class
        ));
    }

    /**
     * Send conditional messages, by specifying an expression the target topics.
     * 
     * @param Message|array<string,mixed> $config The notification payload.
     * @param bool $validateOnly Optional. If set to true, the message will only be validated without sending.
     *
     * @return self Return instance of luminova firebase notification class.
     * @throws RuntimeException If the topic is not provided correctly.
     * 
     * @example "'TopicA' in topics && ('TopicB' in topics || 'TopicC' in topics)".
     */
    public function condition(Message|array $config, bool $validateOnly = false): self
    {
        $this->report = null;
        $message = null;

        try {
            $config = ($config instanceof Message) 
                ? $config 
                : Message::fromArray($config);

            if ($config instanceof Message) {
                if($config->isRaw()){
                    $message = $this->rawMessage($config);
                }elseif($config->getConditions() !== ''){
                    $message = $this->message(
                        MessageTarget::CONDITION, 
                        $config, 
                        $config->getConditions()
                    );
                }

                if($message instanceof MessageCaster){
                    $this->report = $this->messaging()->send($message, $validateOnly);
                    return $this;
                }
            }

        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        throw new RuntimeException(
            sprintf(
                "Invalid input: method %s expected a %s object or an array with a 'conditions' key token.",
                __METHOD__,
                Message::class
            )
        );
    }

    /**
     * Send notifications to multiple devices by tokens.
     * 
     * The method accepts either a Message object or an array containing the notification payload.
     *
     * @param Message|array<string,mixed> $config The notification data.
     * @param bool $validateOnly Optional. If set to true, the message will only be validated without sending.
     *
     * @return self Return instance of luminova firebase notification class.
     * @throws RuntimeException If tokens are not provided or if an error occurs during message construction.
     */
    public function broadcast(Message|array $config, bool $validateOnly = false): self
    {
        $this->report = null;
        $message = null;

        try {
            $config = ($config instanceof Message) 
                ? $config 
                : Message::fromArray($config);

            if ($config instanceof Message) {
                if($config->isRaw()){
                    $message = $this->rawMessage($config);

                    if($message instanceof MessageCaster){
                        $this->report = $this->messaging()->send($message, $validateOnly);
                        return $this;
                    }
                }elseif($config->getTokens() !== []){
                    $messaging = $this->messaging();
                    $sent = 0;

                    foreach($config->getTokens() as $token){
                        try{
                            $this->report[] = $messaging
                                ->send($this->message(
                                    MessageTarget::TOKEN, 
                                    $config, 
                                    $token
                                ), $validateOnly);

                            $sent++;
                        } catch(\Throwable $e){
                            $this->report[] = $e;
                            continue;
                        }
                    }

                    if($sent > 0){
                        return $this;
                    }
                }
            }
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        throw new RuntimeException(
            sprintf(
                "Invalid input: method %s expected a %s object or an array with a 'tokens' key token.",
                __METHOD__,
                Message::class
            )
        );
    }

    /**
     * Subscribe a device token to a topic.
     *
     * @param string $token The device token.
     * @param string $topic The topic to subscribe to.
     *
     * @return self Return instance of luminova firebase notification class.
     * @throws RuntimeException
     */
    public function subscribe(string $token, string $topic): self
    {
        $this->report = null;

        try {
            $this->report = $this->messaging()
                ->subscribeToTopic($topic, $token);

            return $this;
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Subscribe multiple device tokens to list of topics.
     *
     * @param array<int,string> $topics The device tokens.
     * @param array<int,string> $tokens The topics to subscribe.
     *
     * @return self Return instance of luminova firebase notification class.
     * @throws RuntimeException
     */
    public function subscribers(array $topics, array $tokens): self
    {
        $this->report = null;

        try{
            $this->report = $this->messaging()->subscribeToTopics($topics, $tokens);

            return $this;
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Unsubscribe device token from a topic.
     *
     * @param string $token The device token.
     * @param string $topic The topic to unsubscribe from.
     *
     * @return self Return instance of luminova firebase notification class.
     * @throws RuntimeException
     */
    public function unsubscribe(string $token, string $topic): self
    {
        $this->report = null;

        try{
            $this->report = $this->messaging()->unsubscribeFromTopic($topic, $token);

            return $this;
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Unsubscribe list of device token from all topics.
     *
     * @param array<int,string> $tokens The device tokens to unsubscribe from all topics.
     *
     * @return self Return instance of luminova firebase notification class.
     * @throws RuntimeException
     */
    public function unsubscribeFromAllTopics(array $tokens): self
    {
        $this->report = null;

        try{
            $this->report = $this->messaging()->unsubscribeFromAllTopics($tokens);

            return $this;
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Unsubscribe multiple device tokens from list topics.
     *
     * @param array<int,string> $topics The topic to unsubscribe from.
     * @param array<int,string> $tokens The tokens to unsubscribe from list of topics.
     *
     * @return self Return instance of luminova firebase notification class.
     * @throws RuntimeException
     */
    public function unsubscribeFromTopics(array $topics, array $tokens): self
    {
        $this->report = null;

        try{
            $this->report = $this->messaging()->unsubscribeFromTopics($topics, $tokens);

            return $this;
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Unsubscribe multiple device tokens from list topics.
     *
     * @param array<int,string> $topics The topic to unsubscribe from.
     * @param array<int,string> $tokens The tokens to unsubscribe from list of topics.
     *
     * @return self Return instance of luminova firebase notification class.
     * @throws RuntimeException
     * @deprecated Use unsubscribeFromTopics
     */
    public function unsubscribers(array $topics, array $tokens): self
    {
        return $this->unsubscribeFromTopics($topics, $tokens);
    }

    /**
     * Unsubscribe list of device token from all topics.
     *
     * @param array<int,string> $tokens The device tokens to unsubscribe from all topics.
     *
     * @return self Return instance of luminova firebase notification class.
     * @throws RuntimeException
     * @deprecated Use unsubscribeFromAllTopics
     */
    public function desubscribe(array $tokens): self
    {
        return $this->unsubscribeFromAllTopics($tokens);
    }

    /**
     * Determine if notification or subscription was completed successfully.
     * 
     * @return bool Return true if successful, false otherwise.
     */
    public function isDone(): bool 
    {
        if($this->report === null){
            return false;
        }

        if($this->report instanceof MulticastSendReport){
            return $this->report->successes()
                ->count() > 0;
        }
        
        return (is_array($this->report) && ($this->report === [] || count($this->report) > 0));
    }

    /**
     * Retrieve response report from firebase sent notification or topic management.
     * 
     * @return MulticastSendReport|array The response from Firebase Cloud Messaging.
     */
    public function getReport(): MulticastSendReport|array|null
    {
        return $this->report;
    }

    /**
     * Get the Firebase messaging instance.
     *
     * @return Messaging The Firebase messaging instance.
     */
    private function messaging(): Messaging
    {
        static $messaging;

        return $messaging ??= $this->factory->createMessaging();
    }

    /**
     * Create a Firebase notification.
     *
     * @param string $title The title of the notification.
     * @param string|null $body  The body of the notification.
     * @param string|null $imageUrl The image URL of the notification.
     *
     * @return Notifier The Firebase notification.
     */
    private static function create(string $title, ?string $body = null, ?string $imageUrl = null): Notifier
    {
        return Notifier::create($title, $body, $imageUrl);
    }

    /**
     * Create a CloudMessage based on the given type, target, and configuration.
     *
     * @param string $type The type of target (e.g., 'token', 'topic', etc.).
     * @param Message $config The configuration for the push message.
     * @param string|null $to The target value (e.g., token, tokens or topic name).
     *
     * @return MessageCaster The constructed CloudMessage instance, or null on failure.
     * @throws RuntimeException If an exception occurs during message construction.
     */
    private function message(string $type, Message $config, ?string $to = null): MessageCaster
    {
        try {
            $target = match($type){
                MessageTarget::TOKEN     => MessageTarget::TOKEN,
                MessageTarget::TOPIC     => MessageTarget::TOPIC,
                MessageTarget::CONDITION => MessageTarget::CONDITION,
                default                  => MessageTarget::UNKNOWN
            };

            $message = ($to === null) 
                ? CloudMessage::new()
                : CloudMessage::new()->withChangedTarget($target, $to);

            $message->withNotification(self::create(
                $config->getTitle(),
                $config->getBody(),
                $config->getImageUrl()
            ))->withDefaultSounds();

            foreach ($config->getPlatforms() as $platform) {
                switch ($platform){
                    case Message::ANDROID:
                        $message = $this->withAndroid($config, $message);
                        break;

                    case Message::APN:
                        $message = $this->withApn($config, $message);
                        break;

                    case Message::WEBPUSH:
                        $message = $this->withWebpush($config, $message);
                        break;
                    case Message::DEFAULT:
                        $message = $this->withAndroid($config, $message);
                        $message = $this->withApn($config, $message);
                        $message = $this->withWebpush($config, $message);
                    default:
                        break;
                }
            }

            if($config->hasData()){
               $message = $message->withData($config->getData());
            }

            if ($config->has('analytics_label')) {
                $message = $message->withFcmOptions(
                    FcmOptions::create()
                        ->withAnalyticsLabel($config->getAnalytic())
                );
            }

            return $message;
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Undocumented function
     *
     * @param Message $config
     * @param CloudMessage $message
     * @return CloudMessage
     */
    private function withWebpush(Message $config, CloudMessage $message): CloudMessage
    {
        $config->forPlatform(Message::WEBPUSH);

        $priority = $config->getPriority();
      
        $pConfig = WebPushConfig::fromArray($config->toArray());

        if ($priority !== '') {
            $pConfig = $pConfig->withUrgency($priority);
        }

        return $message->withWebPushConfig($pConfig);
    }

    /**
     * Undocumented function
     *
     * @param Message $config
     * @param CloudMessage $message
     * @return CloudMessage
     */
    private function withApn(Message $config, CloudMessage $message): CloudMessage
    {
        $config->forPlatform(Message::APN);

        $priority = $config->getPriority();
        $sound = $config->get('sound');
        $pConfig = ApnsConfig::fromArray($config->toArray());

        if ($sound !== null) {
            $pConfig = $pConfig->withSound($sound);
        }

        if ($priority !== '') {
            $pConfig = $pConfig->withPriority($priority);
        }

        return $message->withApnsConfig($pConfig);
    }

    /**
     * Undocumented function
     *
     * @param Message $config
     * @param CloudMessage $message
     * @return CloudMessage
     */
    private function withAndroid(Message $config, CloudMessage $message): CloudMessage
    {
        $config->forPlatform(Message::ANDROID);

        $priority = $config->getPriority();
        $sound = $config->get('sound');
        $pConfig = AndroidConfig::fromArray($config->toArray());

        if ($sound !== null) {
            $pConfig = $pConfig->withSound($sound);
        }

        if ($priority !== '') {
            $pConfig = $pConfig->withMessagePriority($priority);
        }

       return $message->withAndroidConfig($pConfig);
    }

    /**
     * Create a Cloud Message based on the given type, target, and configuration.
     * 
     * @param Message $config The configuration for the push message.
     * 
     * @return MessageCaster|null The constructed CloudMessage instance, or null on failure.
     */
    private function rawMessage(Message $config): ?MessageCaster
    {
        $payload = $config->toArray();

        if($payload === []){
            return null;
        }

        unset($payload['raw'], $payload['platforms']);

        return new RawMessageFromArray($payload);
    }
}