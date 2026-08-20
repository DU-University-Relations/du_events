<?php

namespace Drupal\Tests\du_livewhale_events\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests LiveWhale data-options construction and replacement.
 *
 * @group du_livewhale_events
 */
class LiveWhaleOptionsTest extends TestCase {

  /**
   * Loads the procedural helpers under test.
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once dirname(__DIR__, 3) . '/du_livewhale_events.module';
  }

  /**
   * Confirms a blank override preserves generated ID and group options.
   */
  public function testBlankOverrideUsesGeneratedOptions(): void {
    $expected = 'id=11&format=html&group=Music %26 Theatre|Newman Center';
    $groups = ['Music & Theatre', 'Newman Center'];

    $this->assertSame($expected, du_livewhale_events_build_options(11, $groups));
    $this->assertSame($expected, du_livewhale_events_build_options(11, $groups, NULL));
    $this->assertSame($expected, du_livewhale_events_build_options(11, $groups, '   '));
  }

  /**
   * Confirms an advanced value completely replaces generated options.
   */
  public function testOverrideReplacesGeneratedOptions(): void {
    $override = 'id=20&format=html&tag=Featured|max=6';

    $this->assertSame(
      $override,
      du_livewhale_events_build_options(0, ['Ignored group'], "  {$override}  ")
    );
  }

  /**
   * Confirms control characters prevent an override from rendering.
   */
  public function testControlCharactersRejectOverride(): void {
    $this->assertSame(
      '',
      du_livewhale_events_build_options(11, ['Newman Center'], "id=11&format=html\ntag=Featured")
    );
  }

}
