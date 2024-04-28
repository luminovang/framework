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
use \Luminova\Config\Configuration;
use \Luminova\Exceptions\ErrorException;
use \Luminova\Models\PushMessage;
use \Exception;

/**
 * Firebase Pusher
 *
 * This class handles sending push notifications using Firebase Cloud Messaging.
 */
class FirebasePusher
{
    protected $factory;

    public const TO_ID = "id";
    public const TO_IDS = "ids";
    public const TO_TOPIC = "topic";

    /**
     * Constructor
     *
     * @param string $filename The filename of the service account JSON file.
     * @param string $dir      The directory where the service account file is located.
     */
    public function __construct(string $filename = "ServiceAccount.json", string $dir = __DIR__)
    {
        $serviceAccount = Configuration::getRootDirectory($dir) . "/writeable/credentials/{$filename}";

        if (file_exists($serviceAccount)) {
            $this->factory = (new Factory)->withServiceAccount($serviceAccount);
        } else {
            ErrorException::throwException("Firebase notification service account not found at [{$serviceAccount}]");
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
                //->withApnsConfig([])
            );
        }catch (Exception $e) {
            ErrorException::throwException($e->getMessage());
        }
    }

    public function subscribe(string $token, string $topic): array 
    {
        $result = $this->messaging()->subscribeToTopic($topic, $token);
        //$result = $this->messaging()->subscribeToTopics($topics, $registrationTokenOrTokens);
        return $result;
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