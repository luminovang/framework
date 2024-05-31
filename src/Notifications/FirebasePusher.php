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
     * @var Factory $factory 
    */
    protected ?Factory $factory = null;

    /**
     * Flag for notification to id.
     * 
     * @var string TO_ID
    */
    public const TO_ID = "id";

    /**
     * Flag for notification to ids.
     * 
     * @var string TO_IDS
    */
    public const TO_IDS = "ids";

    /**
     * Flag for notification to topic.
     * 
     * @var string TO_TOPIC
    */
    public const TO_TOPIC = "topic";

    /**
     * Constructor
     *
     * @param string $filename The filename of the service account JSON file.
     */
    public function __construct(string $filename = 'ServiceAccount.json')
    {
        if(!class_exists(Factory::class)){
            throw new RuntimeException('Package: ' .  Factory::class . ', not found, you need to install first before using firebase module.');
        }
       
        if($this->factory === null){
            $serviceAccount = root('/writeable/credentials/') . $filename;

            if (file_exists($serviceAccount)) {
                $this->factory = (new Factory)->withServiceAccount($serviceAccount);
            } else {
                throw new RuntimeException("Firebase notification service account could not be found in '{$serviceAccount}'");
            }
        }
    }

    /**
     * Get the Firebase messaging instance.
     *
     * @return object The Firebase messaging instance.
     */
    public function messaging(): object
    {
        return $this->factory->createMessaging();
    }

    /**
     * Create a Firebase notification.
     *
     * @param string $title The title of the notification.
     * @param string $body  The body of the notification.
     *
     * @return object The Firebase notification.
     */
    private static function create(string $title, string $body): object
    {
        return Notification::create($title, $body);
    }

    /**
     * Send a notification to a specific device by token.
     *
     * @param array $data The notification data.
     *
     * @return mixed The response from Firebase Cloud Messaging.
     */
    public function sendToId(array $data): mixed
    {
        try {
            return $this->messaging()->send(
                CloudMessage::withTarget("token", $data["token"])
                    ->withNotification(Notification::create($data["title"], $data["body"]))
                    ->withData($data["data"]??[])
            );
        } catch (Exception $e) {
            ErrorException::throwException($e->getMessage());
        }
        return [];
    }

    /**
     * Send a notification to a topic.
     *
     * @param array $data The notification data.
     *
     * @return mixed The response from Firebase Cloud Messaging.
    */

    public function channel(array $data): mixed
    {
        try {
            return $this->messaging()->send(
                CloudMessage::withTarget("topic", $data["topic"])
                    ->withNotification(
                        Notification::create($data["title"], $data["body"], $data["image"] ?? '')
                    )
                    ->withData($data["data"] ?? [])
            );
        } catch (Exception $e) {
            ErrorException::throwException($e->getMessage());
        }
        return [];
    }

    /**
     * Send notifications to multiple devices.
     *
     * @param array $data The notification data.
     *
     * @return mixed The response from Firebase Cloud Messaging.
     */
    public function cast(array $data): mixed
    {
        if (is_array($data["tokens"])) {
            return $this->messaging()->sendMulticast(
                CloudMessage::new()
                    ->withNotification(
                        Notification::create($data["title"], $data["body"], $data["image"] ?? '')
                    )
                    ->withData($data["data"]??[]),
                $data["tokens"]
            );
        } else {
            ErrorException::throwException("Method requires an array of notification ids");
        }
        return [];
    }

    /**
     * Send notifications using a PushMessage object.
     *
     * @param PushMessage $message The PushMessage instance.
     *
     * @return mixed The response from Firebase Cloud Messaging.
     */
    public function push(PushMessage $message): mixed
    {
        try {
            return $this->messaging()->sendMulticast($message->toArray(), $message->getTokens());
        } catch (Exception $e) {
            ErrorException::throwException($e->getMessage());
        }
    }

    public function device(PushMessage $message): mixed{
        try{
            return $this->messaging()->sendMulticast(
                CloudMessage::new()
                ->withNotification(self::create($message->getTitle(), $message->getBody()))
                ->withData($message->getData())
                ->withAndroidConfig([
                    'priority' => 'high',
                    'notification' => [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ])
            );
        }catch (Exception $e) {
            ErrorException::throwException($e->getMessage());
        }
    }

    public function subscribe(string $token, string $topic): array 
    {
        return $this->messaging()->subscribeToTopic($topic, $token);
    }

    /**
     * Send notifications based on the type (to ID, to IDs, to topic).
     *
     * @param array  $data The notification data.
     * @param string $type The type of notification (TO_ID, TO_IDS, TO_TOPIC).
     *
     * @return mixed The response from Firebase Cloud Messaging.
     */
    public function send(array $data, string $type = self::TO_ID): mixed
    {
        return match ($type) {
            "topic" => $this->channel($data),
            "id" => $this->sendToId($data),
            "ids" => $this->cast($data),
            default => []
        };
    }
}