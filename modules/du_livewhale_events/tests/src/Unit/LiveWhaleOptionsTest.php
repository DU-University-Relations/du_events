<?php

namespace Drupal\Tests\du_livewhale_events\Unit;

use Drupal\du_livewhale_events\LiveWhaleOptions;
use Drupal\du_livewhale_events\LiveWhaleScriptUrl;
use PHPUnit\Framework\TestCase;

/**
 * Tests LiveWhale widget option construction.
 *
 * @group du_livewhale_events
 */
class LiveWhaleOptionsTest extends TestCase {

  /**
   * Tests multiple groups and reserved-character encoding.
   */
  public function testBuildWithGroups() {
    $options = LiveWhaleOptions::build(11, [
      'Lamont School of Music & Theatre',
      'Newman Center',
    ]);

    $this->assertSame(
      'id=11&format=html&group=Lamont School of Music %26 Theatre|Newman Center',
      $options
    );
  }

  /**
   * Tests that empty groups are omitted.
   */
  public function testBuildWithoutGroups() {
    $this->assertSame(
      'id=11&format=html',
      LiveWhaleOptions::build('11', ['', '  '])
    );
  }

  /**
   * Tests that invalid IDs do not produce an embed.
   */
  public function testBuildWithInvalidId() {
    $this->assertSame('', LiveWhaleOptions::build('not-an-id'));
    $this->assertSame('', LiveWhaleOptions::build(0));
  }

  /**
   * Tests that a pipe in a group name is not treated as a separator.
   */
  public function testBuildEncodesLiteralPipe() {
    $this->assertSame(
      'id=11&format=html&group=Group%7CName',
      LiveWhaleOptions::build(11, ['Group|Name'])
    );
  }

  /**
   * Tests supported LiveWhale widget script URLs.
   */
  public function testValidScriptUrls() {
    $this->assertTrue(LiveWhaleScriptUrl::isValid(LiveWhaleScriptUrl::DEFAULT_URL));
    $this->assertTrue(LiveWhaleScriptUrl::isValid(
      'https://events.example.edu/livewhale/theme/core/scripts/lwcw.js'
    ));
  }

  /**
   * Tests unsupported LiveWhale widget script URLs.
   */
  public function testInvalidScriptUrls() {
    $this->assertFalse(LiveWhaleScriptUrl::isValid(
      'http://events.example.edu/livewhale/theme/core/scripts/lwcw.js'
    ));
    $this->assertFalse(LiveWhaleScriptUrl::isValid('https://events.example.edu/other.js'));
    $this->assertFalse(LiveWhaleScriptUrl::isValid('not-a-url'));
  }

}
