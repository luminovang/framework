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
namespace Luminova\Components\Widget;

use \Luminova\Exceptions\{RuntimeException, InvalidArgumentException};

/**
 * Encode arbitrary binary/text data into an emoji sequence and decode it back.
 *
 * Warning: emoji and Unicode normalization vary across platforms. Messages,
 * chat apps, or copy/paste may strip or change emoji sequences. This is
 * fragile and mainly for experiments/UX demos.
 */
class Emoji
{
    /**
     * Minimal shortcode -> emoji map. Expand as you need.
     * Keys are shortcodes (with or without surrounding colons).
     * 
     * @var array<string,string> $emojis
     */
    private static array $emojis = [
        'grinning' => '😀','grin' => '😁','joy' => '😂','smiley' => '😃','smile' => '😄',
        'sweat_smile' => '😅', 'laughing' => '😆','wink' => '😉','blush' => '😊',
        'yum' => '😋','sunglasses' => '😎','heart_eyes' => '😍', 'kissing_heart' => '😘',
        'thinking' => '🤔','neutral_face' => '😐','expressionless' => '😑','unamused' => '😒',
        'sweat' => '😓','pensive' => '😔','confused' => '😕','slightly_frowning' => '🙂',
        'disappointed' => '😞', 'angry' => '😠','rage' => '😡','cry' => '😢','persevere' => '😣',
        'triumph' => '😤','sleeping' => '😴','relieved' => '😌','stuck_out_tongue' => '😜',
        'stuck_out_tongue_winking_eye' => '😜','money_mouth' => '🤑', 'nerd' => '🤓','clown' => '🤡',
        'cowboy' => '🤠','partying' => '🥳','nerd_face' => '🤓','hugging' => '🤗', 'robot' => '🤖',
        'poop' => '💩','thumbsup' => '👍','thumbsdown' => '👎','ok_hand' => '👌','clap' => '👏',
        'wave' => '👋','pray' => '🙏','muscle' => '💪','fire' => '🔥','skull' => '💀','star' => '⭐',
        'sparkles' => '✨', 'boom' => '💥','zap' => '⚡','rainbow' => '🌈','sun' => '☀️','cloud' => '☁️',
        'umbrella' => '☂️','snowflake' => '❄️','droplet' => '💧','leaf' => '🍃','rose' => '🌹','tulip' => '🌷',
        'cherry_blossom' => '🌸','hibiscus' => '🌺', 'coffee' => '☕','cake' => '🍰','pizza' => '🍕',
        'hamburger' => '🍔','fries' => '🍟','beer' => '🍺','wine' => '🍷', 'soccer' => '⚽','basketball' => '🏀',
        'football' => '🏈','tennis' => '🎾','guitar' => '🎸','microphone' => '🎤','movie' => '🎬','art' => '🎨',
        'camera' => '📷','phone' => '📱','computer' => '💻','lock' => '🔒','key' => '🔑','envelope' => '✉️',
        'bell' => '🔔','bookmark' => '🔖','money' => '💰','package' => '📦','shopping' => '🛍️','car' => '🚗',
        'rocket' => '🚀','anchor' => '⚓','map' => '🗺️','globe' => '🌍','flag' => '🏳️','trophy' => '🏆'
    ];

    public static function get(string $name): ?string
    {
        return self::$emojis[$name] ?? null;
    }

    public static function has(string $name): bool
    {
        return isset(self::$emojis[$name]);
    }

    public static function isEmoji(string $s): bool
    {
        return (strlen($s) !== mb_strlen($s, '8bit')) 
            && (preg_match('/[\p{So}\p{Sk}\x{1F300}-\x{1FAFF}]/u', $s) === 1);
    }

    /**
     * Build a simple emoji alphabet using a contiguous block of codepoints.
     * Default uses U+1F600..U+1F63F (64 smileys) => 6 bits per symbol.
     *
     * @param int $count Number of emoji to generate (power-of-two suggested: 16,32,64,128)
     * @param int $startHex Hex (string) of start codepoint, e.g. '1F600'
     * 
     * @return array Array of UTF-8 emoji characters
     */
    public static function create(int $count = 64, string $startHex = '1F600'): array
    {
        $start = hexdec($startHex);
        $alphabet = [];
        for ($i = 0; $i < $count; $i++) {
            $alphabet[] = mb_chr($start + $i, 'UTF-8');
        }

        return $alphabet;
    }

    /**
     * Build an emoji alphabet from shortcodes or a character-range.
     *
     * $source can be:
     *  - null (use default set of 64 shortcodes)
     *  - array of shortcodes (with or without surrounding colons), or
     *  - array of emoji characters already
     *
     * @param array $source Default source: use keys from emoji map in declared order
     * @param int $count: number of symbols to return (if source has more items). It must be power-of-two.
     *
     * Returns array of emoji characters.
     */
    public static function build(?array $source = null, int $count = 64): array
    {
        if ($count <= 0) {
            throw new InvalidArgumentException("Count must be positive");
        }

        $bits = (int) log($count, 2);
        if ((1 << $bits) !== $count) {
            throw new InvalidArgumentException(sprintf(
                "Alphabet size must be power-of-two. Got: %d",
                $count
            ));
        }

        $source ??= array_keys(self::$emojis);

        $alphabet = [];
        foreach ($source as $s) {
            if (count($alphabet) >= $count) break;

            $name = (is_string($s) && str_starts_with($s, ':') && str_ends_with($s, ':')) 
                ? substr($s, 1, -1)
                : $s;

            if (self::has($name)) {
                $alphabet[] = self::$emojis[$name];
                continue;
            }

            if (self::isEmoji($s)) {
                $alphabet[] = $s;
                continue;
            }
        }

        if (count($alphabet) < $count) {
            throw new InvalidArgumentException(sprintf(
                "Source provided didn't yield %d emoji symbols. Got: %d.",
                $count,
                count($alphabet)
            ));
        }

        return $alphabet;
    }

    /**
     * Encode raw binary data to emoji string.
     *
     * Options:
     *  - alphabet: null (default) or array of shortcodes or emoji characters
     *  - alphabetSize: desired size (32,64,128) - must be power-of-two
     *  - compress: bool - whether to gzip-compress the payload before encoding
     *
     * Format: header (1 byte flags, 4 bytes original length big-endian) followed by payload (maybe gzipped).
     *
     * @param string $data
     * @param array|null $alphabetSource
     * @param int $alphabetSize
     * @param bool $compress
     * 
     * @return string Emoji string
     */
    public static function encode(
        string $data, 
        ?array $alphabetSource = null, 
        int $alphabetSize = 64, 
        bool $compress = false
    ): string
    {
        $alphabet = self::build($alphabetSource, $alphabetSize);
        $alphabetSize = count($alphabet);
        $bitsPerSymbol = (int) log($alphabetSize, 2);

        if ((1 << $bitsPerSymbol) !== $alphabetSize) {
            throw new InvalidArgumentException('Alphabet size must be power of two.');
        }

        $originalLen = strlen($data);
        $flags = $compress ? 1 : 0;

        $payload = $compress ? gzencode($data) : $data;
        if ($compress && $payload === false) {
            throw new RuntimeException('gzencode failed');
        }

        // header: 1 byte flags + 4 bytes original length (big-endian)
        $header = chr($flags) . pack('N', $originalLen);
        $full = $header . $payload;

        // Convert to bits
        $bytes = unpack('C*', $full);
        $bitStr = '';
        foreach ($bytes as $b) {
            $bitStr .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }

        // pad right to full symbols
        $pad = ($bitsPerSymbol - (strlen($bitStr) % $bitsPerSymbol)) % $bitsPerSymbol;
        if ($pad > 0) {
            $bitStr .= str_repeat('0', $pad);
        }

        $result = '';
        for ($i = 0, $L = strlen($bitStr); $i < $L; $i += $bitsPerSymbol) {
            $chunk = substr($bitStr, $i, $bitsPerSymbol);
            $val = bindec($chunk);
            $result .= $alphabet[$val];
        }

        return $result;
    }

    /**
     * Decode emoji string back to original data.
     *
     * @param string $emojiString
     * @param array|null $alphabetSource
     * @param int $alphabetSize
     * @return string|null original data or null on failure
     */
    public static function decode(
        string $emojiString, 
        ?array $alphabetSource = null, 
        int $alphabetSize = 64
    ): ?string
    {
        $alphabet = self::build($alphabetSource, $alphabetSize);
        $alphabetMap = array_flip($alphabet);
        $alphabetSize = count($alphabet);
        $bitsPerSymbol = (int) log($alphabetSize, 2);

        $symbols = preg_split('//u', $emojiString, -1, PREG_SPLIT_NO_EMPTY);
        if ($symbols === false) return null;

        $bitStr = '';
        foreach ($symbols as $sym) {
            if (!isset($alphabetMap[$sym])) {
                return null;
            }

            $val = $alphabetMap[$sym];
            $bitStr .= str_pad(decbin($val), $bitsPerSymbol, '0', STR_PAD_LEFT);
        }

        // Need at least 40 bits for header (1 byte flags + 4 bytes length)
        if (strlen($bitStr) < 40) return null;

        $flagsBits = substr($bitStr, 0, 8);
        $flags = bindec($flagsBits);
        $lenBits = substr($bitStr, 8, 32);
        $originalLen = bindec($lenBits);

        $dataBits = substr($bitStr, 40);
        $byteCount = intdiv(strlen($dataBits), 8);
        $bytes = [];

        for ($i = 0; $i < $byteCount; $i++) {
            $b = substr($dataBits, $i * 8, 8);
            $bytes[] = chr(bindec($b));
        }

        $payload = implode('', $bytes);

        if (($flags & 1) === 1) {
            $decoded = @gzdecode($payload);
            if ($decoded === false) return null;
            $payload = $decoded;
        }

        $payload = substr($payload, 0, $originalLen);
        if (strlen($payload) !== $originalLen) {
            return null;
        }

        return $payload;
    }
}