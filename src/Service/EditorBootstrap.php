<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Service;

use const ENT_QUOTES;
use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Turns the prebuilt editor's dist/static/index.html into the page loaded in
 * the editor iframe: a <base> pointing at the static bundle and the
 * __EXE_EMBEDDING_CONFIG__ the editor's EmbeddingBridge reads.
 *
 * Same pattern as the siblings (mod_exelearning editor/index.php,
 * omeka-s-exelearning editor-bootstrap.phtml).
 */
final class EditorBootstrap
{
    /**
     * Whether a usable editor bundle exists in $staticDir.
     */
    public static function isAvailable(string $staticDir): bool
    {
        return is_readable($staticDir.'/index.html')
            && (is_dir($staticDir.'/app') || is_dir($staticDir.'/libs'));
    }

    /**
     * @param string $indexHtml  contents of dist/static/index.html
     * @param string $staticUrl  absolute URL of dist/static (no trailing slash)
     * @param string $origin     the site origin, the only trusted parent
     * @param string $projectUrl URL of the .elpx to open, or '' for a new project
     * @param string $locale     editor UI language (e.g. "es")
     * @param string $bridgeUrl  URL of resources/js/editor-bridge.js, or '' to omit it
     */
    public static function render(string $indexHtml, string $staticUrl, string $origin, string $projectUrl, string $locale, string $bridgeUrl = ''): string
    {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        $base = htmlspecialchars($staticUrl, ENT_QUOTES, 'UTF-8');

        $config = json_encode([
            'basePath' => $staticUrl,
            'parentOrigin' => $origin,
            'trustedOrigins' => [$origin],
            'initialProjectUrl' => $projectUrl,
            'hideUI' => ['fileMenu' => true, 'saveButton' => true, 'userMenu' => true],
            'platform' => 'chamilo',
        ], $flags);
        $localeJson = json_encode($locale, $flags);

        $script = <<<HTML
<script>
window.__EXE_EMBEDDING_CONFIG__ = {$config};
(function () {
    var locale = {$localeJson};
    if (!locale) { return; }
    try {
        var prefs = JSON.parse(localStorage.getItem("exe_user_preferences") || "{}");
        if (!prefs || typeof prefs !== "object") { prefs = {}; }
        prefs.userPreferences = prefs.userPreferences || {};
        prefs.userPreferences.locale = { value: locale };
        localStorage.setItem("exe_user_preferences", JSON.stringify(prefs));
    } catch (e) {}
})();
</script>
HTML;

        $html = preg_replace('/(<head[^>]*>)/i', '$1<base href="'.$base.'/">', $indexHtml, 1) ?? $indexHtml;
        $html = preg_replace('/(?<=["\'])\.\//', $base.'/', $html) ?? $html;

        $html = str_ireplace('</head>', $script."\n</head>", $html);
        if ('' === $bridgeUrl) {
            return $html;
        }

        return str_ireplace('</body>', '<script src="'.htmlspecialchars($bridgeUrl, ENT_QUOTES, 'UTF-8').'"></script>'."\n</body>", $html);
    }
}
