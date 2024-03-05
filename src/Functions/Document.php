<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Functions;

class Document
{
    public const BADGE_LINK = 1;
	public const BADGE_SPAN = 2;
    
	/**
	 * Creates badges from an array of tags.
	 *
	 * @param array $tags List of tags [a, b, c] or [key => a, key => b, key => c].
	 * @param string $class CSS class for styling.
	 * @param int $type Badge type (self::BADGE_SPAN or self::BADGE_LINK).
	 * @param string $urlPrefix URL prefix to append if badge type is self::BADGE_LINK.
	 * 
	 * @deprecated this method is deprecated and will be removed in future
	 * @return string HTML span/link elements.
	 */
	public static function badges(array $tags, string $class = "", int $type = self::BADGE_SPAN, string $urlPrefix = ""): string 
	{
		$badge = "";

		if (!empty($tags)) {
			foreach ($tags as $tg) {
				if (!empty($tg)) {
					$tagContent = "<span class='{$class}' aria-label='Tag {$tg}'>{$tg}</span>";
					
					if ($type === self::BADGE_LINK) {
						$tagContent = "<a class='{$class}' href='{$urlPrefix}?tag={$tg}' aria-label='Tag {$tg}'>{$tg}</a>";
					}

					$badge .= $tagContent . " ";
				}
			}
		}

		return $badge;
	}

	/**
	 * Creates button badges from an array of tags.
	 *
	 * @param array $tags List of tags [a, b, c] or [key => a, key => b, key => c].
	 * @param string $class CSS class for styling.
	 * @param bool $truncate Whether to truncate badges if they exceed the limit.
	 * @param int $limit Maximum number of badges to display before truncating.
	 * @param string|null $selected The active badge value.
	 * 
	 * @deprecated this method is deprecated and will be removed in future
	 * @return string HTML span/button elements.
	 */
	public static function buttonBadges(array $tags, string $class = "", bool $truncate = false, int $limit = 3, ?string $selected = null): string 
	{
		$badge = "";
		$lines = 3;

		if (!empty($tags)) {
			$tagArray = (is_array($tags) ? $tags : explode(',', $tags));
			$line = 0;

			foreach ($tagArray as $tg) {
				if (!empty($tg)) {
					$isActive = ($selected === $tg || ($line === 0 && $selected === null)) ? 'active' : '';
					$badge .= "<button class='{$class} {$isActive}' type='button' data-tag='{$tg}' aria-label='Tag {$tg}'>{$tg}</button>";
					$line++;

					if ($truncate && $line === $limit) {
						$badge .= "<span class='more-badges' style='display:none;'>";
					}
				}
			}

			if ($truncate) {
				$badge .= "</span>";
				$badge .= "<button class='{$class}' type='button' data-state='show'>&#8226;&#8226;&#8226;</button>";
			}
		}

		return $badge;
	}
}