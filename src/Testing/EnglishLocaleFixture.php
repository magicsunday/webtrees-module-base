<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Testing;

use Fisharebest\Localization\Locale;
use Fisharebest\Localization\Translator;
use Fisharebest\Webtrees\I18N;
use ReflectionProperty;

/**
 * Installs an English locale and translator, so translated texts are deterministic. A module
 * uses it in the tests of its own suite.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class EnglishLocaleFixture
{
    /**
     * Prevents instances of the fixture, because it only installs a locale and holds no state.
     */
    private function __construct()
    {
    }

    /**
     * Installs the English locale, or the given one, and a translator that knows the given messages.
     * The translator follows the plural rule of the installed locale.
     *
     * @param array<string, string> $messages   The translations by source text
     * @param string                $localeCode The code of the locale to install, English by default
     */
    public static function install(array $messages = [], string $localeCode = 'en'): void
    {
        $locale     = Locale::create($localeCode);
        $translator = new Translator($messages, $locale->pluralRule());

        $localeProperty = new ReflectionProperty(I18N::class, 'locale');
        $localeProperty->setValue(null, $locale);

        $translatorProperty = new ReflectionProperty(I18N::class, 'translator');
        $translatorProperty->setValue(null, $translator);
    }
}
