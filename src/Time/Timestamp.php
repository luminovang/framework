<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Time;

use \Luminova\Time\Time;
use \DateTimeInterface;
use \DateInterval;
use \Luminova\Exceptions\DateTimeException;;

class Timestamp
{
    /**
     * Converts expires time to Unix timestamp format.
     *
     * @param DateTimeInterface|int|string $expires Expiry time to convert to Unix timestamp.
     * 
     * @return int $timestamp Returns Unix timestamp.
     * @throws DateTimeException
     */
    public static function ttlTimestamp(DateTimeInterface|int|string $expires = 0): int
    {
        if ($expires instanceof DateTimeInterface) {
            $expires = $expires->format('U');
        }

        if (!is_string($expires) && !is_int($expires)) {
            $message = sprintf('Invalid time format: %s', gettype($expires));

            throw new DateTimeException($message);
        }

        if (!is_numeric($expires)) {
            $expires = strtotime($expires);

            if ($expires === false) {
                throw new DateTimeException('Invalid time value');
            }
        }

        return ($expires > 0) ? (int) $expires : 0;
    }

    /**
     * Convert DateInterval to seconds.
     * 
     * @param DateInterval|DateTimeInterface $ttl Time 
     * 
     * @return int Return converted date interval in seconds.
     */
    public static function ttlToSeconds(DateInterval|DateTimeInterface|int|null $ttl): int
    {
        if($ttl === null){
            return 0;
        }

        if(is_int($ttl)){
            return $ttl;
        }

        $now = Time::now();
        
        if($ttl instanceof DateInterval){
            return $now->add($ttl)->getTimestamp() - $now->getTimestamp();
        }

        if($ttl instanceof DateTimeInterface){
            $diff = $now->diff($ttl);
            
            return $diff->s + ($diff->i * 60) + ($diff->h * 3600) + ($diff->d * 86400) + ($diff->m * 2592000) + ($diff->y * 31536000);
        }

        return 0;
    }
}