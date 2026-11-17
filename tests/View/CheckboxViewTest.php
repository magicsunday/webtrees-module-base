<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\View;

use MagicSunday\Webtrees\ModuleBase\Test\Double\ViewFixture;
use MagicSunday\Webtrees\ModuleBase\Testing\MarkupFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Renders the checkbox view that the chart modules share. It adds a hidden field to the checkbox of
 * the webtrees core, so a form that is submitted with the box unchecked still carries the setting.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversNothing]
final class CheckboxViewTest extends TestCase
{
    /**
     * The hidden field carries the value of the unchecked state under the name of the checkbox.
     */
    #[Test]
    public function submitsTheUncheckedValueUnderTheNameOfTheCheckbox(): void
    {
        $xpath = MarkupFixture::xpathOf($this->render());

        $hidden = MarkupFixture::first($xpath, '//input[@type="hidden"]');

        self::assertSame('showDetails', $hidden->getAttribute('name'));
        self::assertSame('0', $hidden->getAttribute('value'));
    }

    /**
     * The checkbox of the core follows the hidden field under the same name, so the checked value
     * overrides the unchecked one in the submitted form.
     */
    #[Test]
    public function followsTheHiddenFieldWithTheCheckboxOfTheSameName(): void
    {
        $xpath = MarkupFixture::xpathOf($this->render());

        $checkbox = MarkupFixture::first($xpath, '//input[@type="checkbox"]');

        self::assertSame('showDetails', $checkbox->getAttribute('name'));
        self::assertSame('1', $checkbox->getAttribute('value'));
        self::assertCount(1, MarkupFixture::elements($xpath, '//input[@type="hidden"]/following::input[@type="checkbox"]'));
    }

    /**
     * A render that leaves out the optional settings does not check or disable the box, so a form
     * that renders a box without them keeps it editable and unchecked.
     */
    #[Test]
    public function leavesTheBoxUncheckedAndEnabledWithoutTheOptionalSettings(): void
    {
        $xpath = MarkupFixture::xpathOf($this->render());

        $checkbox = MarkupFixture::first($xpath, '//input[@type="checkbox"]');

        self::assertFalse($checkbox->hasAttribute('checked'));
        self::assertFalse($checkbox->hasAttribute('disabled'));
    }

    /**
     * The optional settings and the label reach the checkbox of the core unchanged, so the box can
     * start checked or disabled and be reached by its id.
     */
    #[Test]
    public function passesTheOptionalSettingsOnToTheCheckbox(): void
    {
        $xpath = MarkupFixture::xpathOf(ViewFixture::render('checkbox', [
            'checked'   => true,
            'disabled'  => true,
            'id'        => 'show-details',
            'label'     => 'Show details',
            'name'      => 'showDetails',
            'value'     => '7',
            'unchecked' => '0',
        ]));

        $checkbox = MarkupFixture::first($xpath, '//input[@type="checkbox"]');

        self::assertTrue($checkbox->hasAttribute('checked'));
        self::assertTrue($checkbox->hasAttribute('disabled'));
        self::assertSame('show-details', $checkbox->getAttribute('id'));
        self::assertSame('7', $checkbox->getAttribute('value'));
        self::assertStringContainsString('Show details', MarkupFixture::first($xpath, '//label')->textContent);
    }

    /**
     * Renders the view with a label, a name and the unchecked value.
     */
    private function render(): string
    {
        return ViewFixture::render('checkbox', [
            'label'     => 'Show details',
            'name'      => 'showDetails',
            'unchecked' => '0',
        ]);
    }
}
