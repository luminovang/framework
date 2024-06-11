<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Notifications;

use \Kreait\Firebase\Factory;
use \Kreait\Firebase\Messaging\CloudMessage;
use \Kreait\Firebase\Messaging\Notification;
use \Kreait\Firebase\Contract\Messaging;
use \Kreait\Firebase\Messaging\MulticastSendReport;
use \Kreait\Firebase\Messaging\AndroidConfig;
use \Kreait\Firebase\Messaging\WebPushConfig;
use \Kreait\Firebase\Messaging\ApnsConfig;
use \Kreait\Firebase\Messaging\FcmOptions;
use \Kreait\Firebase\Messaging\MessageTarget;
use \Luminova\Exceptions\ErrorException;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Models\PushMessage;
use \Exception;

/**
 * Firebase Pusher
 *
 * This class handles sending push notifications using Firebase Cloud Messaging.
 */
class FirebasePusher
{
    /**
     * Notification factory.
     * 
     * @var Factory|null $factory 
     */
    private static ?Factory $factory = null;

    /**
     * Notification response report.
     * 
     * @var MulticastSendReport|array|null $report 
     */
    private MulticastSendReport|array|null $report = null;

    /**
     * Initializing the FirebasePusher class.
     *
     * @param string $filename The filename of the service account JSON file.
     *                         - Note: The service account file must be stored in `/writeable/credentials/`.
     * 
     * @throws RuntimeException If the Factory class is not found or the service account file is missing.
     */
    public function __construct(string $filename = 'ServiceAccount.json')
    {
        self::$factory ??= self::createFactory($filename);
    }

    /**
     * Get a shared instance of the Factory class.
     *
     * @param string $filename The filename of the service account JSON file.
     *                         - Note: The service account file must be stored in `/writeable/credentials/`.
     * 
     * @return Factory|null The shared Factory instance.
     * @throws RuntimeException If the Factory class is not found or the service account file is missing.
     */
    public static function getFactory(string $filename = 'ServiceAccount.json'): ?Factory
    {
        if (self::$factory === null) {
            self::$factory = self::createFactory($filename);
        }

        return self::$factory;
    }

    /**
     * Create a new Factory instance with the specified service account file.
     *
     * @param string $filename The filename of the service account JSON file.
     * 
     * @return Factory The new Factory instance.
     * @throws RuntimeException If the Factory class is not found or the service account file is missing.
     */
    private static function createFactory(string $filename): Factory
    {
        if (!class_exists(Factory::class)) {
            throw new RuntimeException('Package: ' . Factory::class . ' not found. Please install the required package before using the Firebase module.');
        }

        $serviceAccount = root('/writeable/credentials/') . $filename;

        if (file_exists($serviceAccount)) {
            return (new Factory())->withServiceAccount($serviceAccount);
        }

        throw new RuntimeException("Firebase notification service account not found in '{$serviceAccount}'.");
    }

    /**
     * Get the Firebase messaging instance.
     *
     * @return Messaging The Firebase messaging instance.
     */
    public function messaging(): Messaging
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
     * @return Notification The Firebase notification.
     */
    private static function create(string $title, ?string $body = null, ?string $imageUrl = null): Notification
    {
        return Notification::create($title, $body, $imageUrl);
    }

    /**
     * Create a CloudMessage based on the given type, target, and configuration.
     *
     * @param string $type The type of target (e.g., 'token', 'topic', etc.).
     * @param string|array $to The target value (e.g., token, topic name).
     * @param PushMessage $config The configuration for the push message.
     *
     * @return CloudMessage|null The constructed CloudMessage instance, or null on failure.
     * @throws ErrorException If an exception occurs during message construction.
     */
    private function message(string $type, string|array $to, PushMessage $config): ?CloudMessage
    {
        try {
            $message = ($type === 'instance') ? CloudMessage::new() : CloudMessage::withTarget($type, $to);
            $message = $message->withNotification(
                self::create($config->getTitle(), $config->getBody(), $config->getImageUrl())
            )->withDefaultSounds();

            $platform = $config->getPlatform();
            $sound = $config->get('sound');
            $priority = $config->getPriority();

            switch ($platform) {
                case PushMessage::ANDROID:
                    $pConfig = AndroidConfig::fromArray($config->fromArray($type, $to));
                    if ($sound !== null) {
                        $pConfig = $pConfig->withSound($sound);
                    }
                    if ($priority !== '') {
                        $pConfig = $pConfig->withMessagePriority($priority);
                    }
                    $message = $message->withAndroidConfig($pConfig);
                    break;

                case PushMessage::APN:
                    $pConfig = ApnsConfig::fromArray($config->fromArray($type, $to));
                    if ($sound !== null) {
                        $pConfig = $pConfig->withSound($sound);
                    }
                    if ($priority !== '') {
                        $pConfig = $pConfig->withPriority($priority);
                    }
                    $message = $message->withApnsConfig($pConfig);
                    break;

                case PushMessage::WEBPUSH:
                    $pConfig = WebPushConfig::fromArray($config->fromArray($type, $to));
                    if ($priority !== '') {
                        $pConfig = $pConfig->withUrgency($priority);
                    }
                    $message = $message->withWebPushConfig($pConfig);
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
            ErrorException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return null;
    }

    /**
     * Send a notification to a specific device by token.
     *
     * @param PushMessage|array<string,mixed> $config The notification payload.
     * @param bool $validateOnly Optional. If set to true, the message will only be validated without sending.
     *
     * @return self Return instance of luminova firebase class.
     * @throws ErrorException If tokens are not provided correctly.
     */
    public function send(PushMessage|array $config, bool $validateOnly = false): self
    {
        $this->report = null;
        try {
            if (is_array($config)) {
                $config = new PushMessage($config);
            }

            if ($config instanceof PushMessage && $config->getToken() !== '') {
                $message = self::message(MessageTarget::TOKEN, $config->getToken(), $config);

                $this->report = $this->messaging()->send($message, $validateOnly);

                return $this;
            }

        } catch (Exception $e) {
            ErrorException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        throw new ErrorException("Invalid input: Expected a PushMessage instance and must call methed setToken or an array with a 'topic' key token.");
    }

    /**
     * Send a notification to a topic.
     *
     * @param PushMessage|array<string,mixed> $config The notification payload.
     * @param bool $validateOnly Optional. If set to true, the message will only be validated without sending.
     *
     * @return self Return instance of luminova firebase class.
     * @throws ErrorException If the topic is not provided correctly.
     */
    public function channel(PushMessage|array $config, bool $validateOnly = false): self
    {
        $this->report = null;
        try {
            if (is_array($config)) {
                $config = new PushMessage($config);
            }

            if ($config instanceof PushMessage && $config->getTopic() !== '') {
                $message = self::message(MessageTarget::TOPIC, $config->getTopic(), $config);

                $this->report = $this->messaging()->send($message, $validateOnly);
                return $this;
            }

        } catch (Exception $e) {
            ErrorException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        throw new ErrorException("Invalid input: Expected a PushMessage instance and must call methed setTopic or an array with a 'topic' key included.");
    }

    /**
     * Send conditional messages, by specifying an expression the target topics.
     * @example "'TopicA' in topics && ('TopicB' in topics || 'TopicC' in topics)".
     * 
     * @param PushMessage|array<string,mixed> $config The notification payload.
     * @param bool $validateOnly Optional. If set to true, the message will only be validated without sending.
     *
     * @return self Return instance of luminova firebase class.
     * @throws ErrorException If the topic is not provided correctly.
     */
    public function condition(PushMessage|array $config, bool $validateOnly = false): self
    {
        $this->report = null;
        try {
            if (is_array($config)) {
                $config = new PushMessage($config);
            }

            if ($config instanceof PushMessage && $config->getConditions() !== '') {
                $message = self::message(MessageTarget::CONDITION, $config->getConditions(), $config);

                $this->report = $this->messaging()->send($message, $validateOnly);
                return $this;
            }

        } catch (Exception $e) {
            ErrorException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        throw new ErrorException("Invalid input: Expected a PushMessage instance and must call methed setCondition or an array with a 'conditions' key included.");
    }

    /**
     * Send notifications to multiple devices by tokens.
     *
     * @param PushMessage|array<string,mixed> $config The notification data.
     * @param bool $validateOnly Optional. If set to true, the message will only be validated without sending.
     *
     * @return self Return instance of luminova firebase class.
     * @throws ErrorException If tokens are not provided or if an error occurs during message construction.
     */
    public function broadcast(PushMessage|array $config, bool $validateOnly = false): self
    {
        $this->report = null;
        try {
            if (is_array($config)) {
                $config = new PushMessage($config);
            }

            if ($config instanceof PushMessage && !empty($config->getTokens())) {
                $message = $this->message('instance', [], $config);

                $this->report = $this->messaging()->sendMulticast($message, $config->getTokens(), $validateOnly);
                return $this;
            }
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage(), $e->getCode(), $e);
        }

        throw new ErrorException("Invalid input: Expected a PushMessage instance or an array with a 'tokens' key.");
    }

    /**
     * Subscribe a device token to a topic.
     *
     * @param string $token The device token.
     * @param string $topic The topic to subscribe to.
     *
     * @return self Return instance of luminova firebase class.
     */
    public function subscribe(string $token, string $topic): self
    {
        $this->report = null;
        try {
            $this->report = $this->messaging()->subscribeToTopic($topic, $token);

            return $this;
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Subscribe multiple device tokens to list of topics.
     *
     * @param array<int,string> $topics The device tokens.
     * @param array<int,string> $tokens The topics to subscribe.
     *
     * @return self Return instance of luminova firebase class.
     */
    public function subscribers(array $topics, array $tokens): self
    {
        $this->report = null;
        try{
            $this->report = $this->messaging()->subscribeToTopics($topics, $tokens);

            return $this;
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Unsubscribe device token from a topic.
     *
     * @param string $token The device token.
     * @param string $topic The topic to unsubscribe from.
     *
     * @return self Return instance of luminova firebase class.
     */
    public function unsubscribe(string $token, string $topic): self
    {
        $this->report = null;
        try{
            $this->report = $this->messaging()->unsubscribeFromTopic($topic, $token);

            return $this;
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Unsubscribe multiple device tokens from list topics.
     *
     * @param array<int,string> $topics The topic to unsubscribe from.
     * @param array<int,string> $tokens The tokens to unsubscribe from list of topics.
     *
     * @return self Return instance of luminova firebase class.
     */
    public function unsubscribers(array $topics, array $tokens): self
    {
        $this->report = null;
        try{
            $this->report = $this->messaging()->unsubscribeFromTopics($topics, $tokens);

            return $this;
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Unsubscribe list of device token from all topics.
     *
     * @param array<int,string> $tokens The device tokens to unsubscribe from all topics.
     *
     * @return self Return instance of luminova firebase class.
     */
    public function desubscribe(array $tokens): self
    {
        $this->report = null;
        try{
            $this->report = $this->messaging()->unsubscribeFromAllTopics($tokens);

            return $this;
        } catch (Exception $e) {
            throw new ErrorException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Dtermind if notification or subscription was completed successfully.
     * 
     * @return bool Return true if sucessful, false otherwise.
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
     * Retrive response report from firebase sent notification or topic managment.
     * 
     * @return MulticastSendReport|array The response from Firebase Cloud Messaging.
    */
    public function getReport(): MulticastSendReport|array|null
    {
        return $this->report;
    }
}