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
use \Kreait\Firebase\Factory;
use \Luminova\Interface\LazyObjectInterface;
use \Kreait\Firebase\Contract\Messaging;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Notifications\Models\Message;
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
use function \Luminova\Funcs\root;

class Notification implements LazyObjectInterface
{
    /**
     * Notification factory.
     * 
     * @var Factory|null $factory 
     */
    private static ?Factory $factory = null;

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
     * Initializes the Firebase Cloud Messaging Notification class.
     *
     * @param Factory|string|array $config The service account filename, (e.g, JSON string, Array or Instance of Factory):
     *               - Filename (string): The service account file must be stored in `/writeable/credentials/`.
     *               - Configuration (array): The service account configuration array.
     *               - Configuration (string): The service account configuration JSON string.
     *               - Instance (Factory): The Factory instance initialized with the preferred service account.
     * 
     * @throws RuntimeException If the Factory class is not found or the service account file is missing.
     */
    public function __construct(Factory|string|array $config = 'ServiceAccount.json')
    {
        if ($config instanceof Factory) {
            self::$factory = $config;
            return;
        }

        self::$factory ??= self::createFactory($config);
    }

    /**
     * Initializes the Firebase Cloud Messaging Notification shared instance class.
     *
     * @param Factory|string|array $config The service account filename, (e.g, JSON string, Array or Instance of Factory):
     *               - Filename (string): The service account file must be stored in `/writeable/credentials/`.
     *               - Configuration (array): The service account configuration array.
     *               - Configuration (string): The service account configuration JSON string.
     *               - Instance (Factory): The Factory instance initialized with the preferred service account.
     * 
     * @return self Returns new shared instance of the notification class.
     * @throws RuntimeException If the Factory class is not found or the service account file is missing.
     */
    public static function getInstance(Factory|string|array $config = 'ServiceAccount.json'): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        
        return self::$instance;
    }

    /**
     * Get a shared instance of the Kreait Firebase Factory class, 
     * or create new factory instance from service account.
     *
     * @param string|array $account The service account (e.g, filename, JSON string, an array):
     *               - Filename (string): The service account file must be stored in `/writeable/credentials/`.
     *               - Configuration (array): The service account configuration array.
     *               - Configuration (string): The service account configuration JSON string.
     * 
     * @return Factory|null The shared Factory instance.
     * @throws RuntimeException If the Factory class is not found or the service account file is missing.
     */
    public static function getFactory(string|array $account = 'ServiceAccount.json'): ?Factory
    {
        if (self::$factory === null) {
            self::$factory = self::createFactory($account);
        }

        return self::$factory;
    }

    /**
     * Create a new Factory instance with the specified service account file or configuration array.
     *
     * @param Factory|string|array $config The service account filename or configuration array.
     * 
     * @return Factory The new Factory instance.
     * @throws RuntimeException If the Factory class is not found or the service account file is missing.
     */
    private static function createFactory(Factory|string|array $config): Factory
    {
        if ($config instanceof Factory) {
            return $config;
        }

        if (!class_exists(Factory::class)) {
            throw new RuntimeException(sprintf('Package: %s not found. Please install the required package before using the Firebase module.', Factory::class));
        }

        if (is_string($config) && !str_starts_with($config, '{')) {
            $config = root('/writeable/credentials/', $config);
   
            if (!file_exists($config)) {
                throw new RuntimeException(sprintf('Firebase notification service account not found in %s.', $config));
            }
        }
        
        return (new Factory())->withServiceAccount($config);
    }

    /**
     * Get the Firebase messaging instance.
     *
     * @return Messaging The Firebase messaging instance.
     */
    private function messaging(): Messaging
    {
        return self::$factory->createMessaging();
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
     * @return MessageCaster|null The constructed CloudMessage instance, or null on failure.
     * @throws RuntimeException If an exception occurs during message construction.
     */
    private function message(string $type, Message $config, ?string $to = null): ?MessageCaster
    {
        try {
            $target = match($type){
                MessageTarget::TOKEN     => CloudMessage::new()->toToken($to),
                MessageTarget::TOPIC     => CloudMessage::new()->toTopic($to),
                MessageTarget::CONDITION => CloudMessage::new()->toCondition($to),
                default => CloudMessage::new()
            };

            $message = $target->withNotification(
                self::create($config->getTitle(), $config->getBody(), $config->getImageUrl())
            )->withDefaultSounds();

            $sound = $config->get('sound');
            $priority = $config->getPriority();

            switch ($config->getPlatform()) {
                case Message::ANDROID:
                    $pConfig = AndroidConfig::fromArray($config->toArray());
                    if ($sound !== null) {
                        $pConfig = $pConfig->withSound($sound);
                    }

                    if ($priority !== '') {
                        $pConfig = $pConfig->withMessagePriority($priority);
                    }

                    $message = $message->withAndroidConfig($pConfig);
                    break;

                case Message::APN:
                    $pConfig = ApnsConfig::fromArray($config->toArray());

                    if ($sound !== null) {
                        $pConfig = $pConfig->withSound($sound);
                    }

                    if ($priority !== '') {
                        $pConfig = $pConfig->withPriority($priority);
                    }

                    $message = $message->withApnsConfig($pConfig);
                    break;

                case Message::WEBPUSH:
                    $pConfig = WebPushConfig::fromArray($config->toArray());
                    if ($priority !== '') {
                        $pConfig = $pConfig->withUrgency($priority);
                    }

                    $message = $message->withWebPushConfig($pConfig);
                    break;
                case Message::DEFAULT;
                default:
                    break;
            }

            if (($data = $config->getData()) !== []) {
                $message = $message->withData($data);
            }

            if (($analytics = $config->getAnalytic()) !== '') {
                $message = $message->withFcmOptions(FcmOptions::create()->withAnalyticsLabel($analytics));
            }

            return $message;

        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return null;
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

        if($payload !== []){
            unset($payload['raw'], $payload['platform']);
            return new RawMessageFromArray($payload);
        }

        return null;
    }

    /**
     * Send a notification to a specific device by token.
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
            if (is_array($config)) {
                $config = Message::fromArray($config);
            }

            if ($config instanceof Message) {
                $config->isInternal();

                if($config->isRaw()){
                    $message = $this->rawMessage($config);
                }elseif($config->getToken() !== ''){
                    $message = $this->message(MessageTarget::TOKEN, $config, $config->getToken());
                }

                if($message instanceof MessageCaster){
                    $this->report = $this->messaging()->send($message, $validateOnly);
                    return $this;
                }
            }

        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        throw new RuntimeException("Invalid input: Expected a Message instance and must call method setToken or an array with a 'topic' key token.");
    }

    /**
     * Send a notification to a topic.
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
            if (is_array($config)) {
                $config = Message::fromArray($config);
            }

            if ($config instanceof Message) {
                $config->isInternal();

                if($config->isRaw()){
                    $message = $this->rawMessage($config);
                }elseif($config->getTopic() !== ''){
                    $message = $this->message(MessageTarget::TOPIC, $config, $config->getTopic());
                }

                if($message instanceof MessageCaster){
                    $this->report = $this->messaging()->send($message, $validateOnly);
                    return $this;
                }
            }

        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        throw new RuntimeException("Invalid input: Expected a Message instance and must call method setTopic or an array with a 'topic' key included.");
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
            if (is_array($config)) {
                $config = Message::fromArray($config);
            }

            if ($config instanceof Message) {
                $config->isInternal();

                if($config->isRaw()){
                    $message = $this->rawMessage($config);
                }elseif($config->getConditions() !== ''){
                    $message = $this->message(MessageTarget::CONDITION, $config, $config->getConditions());
                }

                if($message instanceof MessageCaster){
                    $this->report = $this->messaging()->send($message, $validateOnly);
                    return $this;
                }
            }

        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        throw new RuntimeException("Invalid input: Expected a Message instance and must call method setCondition or an array with a 'conditions' key included.");
    }

    /**
     * Send notifications to multiple devices by tokens.
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
            if (is_array($config)) {
                $config = new Message($config);
            }

            if ($config instanceof Message) {
                $config->isInternal();

                if($config->isRaw()){
                    $message = $this->rawMessage($config);

                    if($message instanceof MessageCaster){
                        $this->report = $this->messaging()->send($message, $validateOnly);
                        return $this;
                    }
                }elseif($config->getTokens() !== []){
                    $message = $this->message('tokens', $config);

                    if($message instanceof MessageCaster){
                        $this->report = $this->messaging()->sendMulticast($message, $config->getTokens(), $validateOnly);
                        return $this;
                    }
                }
            }
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        throw new RuntimeException("Invalid input: Expected a Message instance or an array with a 'tokens' key.");
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
            $this->report = $this->messaging()->subscribeToTopic($topic, $token);

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
     * Unsubscribe multiple device tokens from list topics.
     *
     * @param array<int,string> $topics The topic to unsubscribe from.
     * @param array<int,string> $tokens The tokens to unsubscribe from list of topics.
     *
     * @return self Return instance of luminova firebase notification class.
     * @throws RuntimeException
     */
    public function unsubscribers(array $topics, array $tokens): self
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
     * Unsubscribe list of device token from all topics.
     *
     * @param array<int,string> $tokens The device tokens to unsubscribe from all topics.
     *
     * @return self Return instance of luminova firebase notification class.
     * @throws RuntimeException
     */
    public function desubscribe(array $tokens): self
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
            return $this->report->successes()->count() > 0;
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
}