<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Cleans rich text from the editor (CKEditor) before it is stored.
 * Only simple formatting survives: scripts, styles, event handlers, iframes and javascript: links are removed.
 */
class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = trim(self::purifier()->purify($html));

        // Editor "empty" content like <p></p> or <p>&nbsp;</p> counts as no description.
        return trim(strip_tags(html_entity_decode($clean)), " \t\n\r\0\x0B\u{00A0}") === '' ? null : $clean;
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', storage_path('app/purifier'));
        $config->set('HTML.Allowed', implode(',', [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
            'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'hr',
            'a[href|title]',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
        ]));
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.Nofollow', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        return self::$purifier = new HTMLPurifier($config);
    }
}
