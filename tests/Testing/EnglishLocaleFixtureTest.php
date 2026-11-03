<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Testing;

use Fisharebest\Localization\Locale\LocaleInterface;
use Fisharebest\Localization\Translation;
use Fisharebest\Localization\Translator;
use Fisharebest\Webtrees\I18N;
use MagicSunday\Webtrees\ModuleBase\Testing\EnglishLocaleFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Pins what the English locale fixture installs into the translation layer of webtrees.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(EnglishLocaleFixture::class)]
final class EnglishLocaleFixtureTest extends TestCase
{
    /**
     * The locale that was installed before the test, or null when none was.
     */
    private ?LocaleInterface $previousLocale = null;

    /**
     * The translator that was installed before the test, or null when none was.
     */
    private ?Translator $previousTranslator = null;

    /**
     * Remembers the installed locale and translator, so the test leaves them as it found them.
     */
    protected function setUp(): void
    {
        $locale     = new ReflectionProperty(I18N::class, 'locale');
        $translator = new ReflectionProperty(I18N::class, 'translator');

        $installedLocale     = $locale->isInitialized() ? $locale->getValue() : null;
        $installedTranslator = $translator->isInitialized() ? $translator->getValue() : null;

        $this->previousLocale     = $installedLocale instanceof LocaleInterface ? $installedLocale : null;
        $this->previousTranslator = $installedTranslator instanceof Translator ? $installedTranslator : null;
    }

    /**
     * Puts the remembered locale and translator back.
     */
    protected function tearDown(): void
    {
        if ($this->previousLocale instanceof LocaleInterface) {
            (new ReflectionProperty(I18N::class, 'locale'))->setValue(null, $this->previousLocale);
        }

        if ($this->previousTranslator instanceof Translator) {
            (new ReflectionProperty(I18N::class, 'translator'))->setValue(null, $this->previousTranslator);
        }
    }

    /**
     * The installed locale is English, so the plural rule and the number format are the English ones.
     *
     * @return void
     */
    #[Test]
    public function installsTheEnglishLocale(): void
    {
        EnglishLocaleFixture::install();

        self::assertSame('en', I18N::languageTag());
        self::assertSame('1 item', I18N::plural('%s item', '%s items', 1, '1'));
        self::assertSame('2 items', I18N::plural('%s item', '%s items', 2, '2'));
    }

    /**
     * A text without a translation stays as it is, and a given translation replaces its source text.
     *
     * @return void
     */
    #[Test]
    public function translatesTheGivenMessagesAndKeepsAnyOtherText(): void
    {
        EnglishLocaleFixture::install(['Hello' => 'Hallo']);

        self::assertSame('Hallo', I18N::translate('Hello'));
        self::assertSame('Other', I18N::translate('Other'));
    }

    /**
     * A given locale code replaces the English locale, so a test can ask for a right-to-left language.
     *
     * @return void
     */
    #[Test]
    public function installsTheGivenLocaleInPlaceOfEnglish(): void
    {
        EnglishLocaleFixture::install([], 'ar');

        self::assertSame('ar', I18N::languageTag());
        self::assertSame('rtl', I18N::direction());
    }

    /**
     * Without a locale code the direction is left to right, and a later call with the default
     * installs English again after another locale.
     *
     * @return void
     */
    #[Test]
    public function installsEnglishAgainAfterAnotherLocale(): void
    {
        EnglishLocaleFixture::install([], 'ar');
        EnglishLocaleFixture::install();

        self::assertSame('en', I18N::languageTag());
        self::assertSame('ltr', I18N::direction());
    }

    /**
     * The translator follows the plural rule of the given locale, so the plural form of a count comes
     * from that locale and not from the English rule.
     *
     * @return void
     */
    #[Test]
    public function translatesPluralsWithTheRuleOfTheGivenLocale(): void
    {
        $forms = ['zero', 'one', 'two', 'few', 'many', 'other'];

        EnglishLocaleFixture::install(
            ['%s item' . Translation::PLURAL_SEPARATOR . '%s items' => implode(Translation::PLURAL_SEPARATOR, $forms)],
            'ar',
        );

        self::assertSame('two', I18N::plural('%s item', '%s items', 2, '2'));
        self::assertSame('few', I18N::plural('%s item', '%s items', 5, '5'));
    }

    /**
     * A later installation without messages drops the messages of an earlier one.
     *
     * @return void
     */
    #[Test]
    public function aLaterInstallationReplacesTheMessagesOfTheEarlierOne(): void
    {
        EnglishLocaleFixture::install(['Hello' => 'Hallo']);
        EnglishLocaleFixture::install();

        self::assertSame('Hello', I18N::translate('Hello'));
    }
}
