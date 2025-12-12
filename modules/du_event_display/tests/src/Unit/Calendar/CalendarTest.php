<?php

declare(strict_types=1);

namespace Drupal\Tests\du_event_display\Unit\Calendar;

use Drupal\du_event_display\Calendar\Calendar;
use Drupal\du_event_display\Calendar\CalendarEvent;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Calendar class.
 *
 * @group du_event_display
 * @coversDefaultClass \Drupal\du_event_display\Calendar\Calendar
 */
final class CalendarTest extends UnitTestCase {

  /**
   * Tests constructor with default parameters.
   *
   * @covers ::__construct
   */
  public function testConstructorDefaults(): void {
    $calendar = new Calendar([]);
    $output = $calendar->generateString();

    self::assertStringContainsString('X-WR-CALNAME:Calendar', $output);
    self::assertStringContainsString('PRODID:-//Calender Generator//NONSGML//EN', $output);
  }

  /**
   * Tests constructor with custom title.
   *
   * @covers ::__construct
   */
  public function testConstructorWithCustomTitle(): void {
    $calendar = new Calendar(['title' => 'My Custom Calendar']);
    $output = $calendar->generateString();

    self::assertStringContainsString('X-WR-CALNAME:My Custom Calendar', $output);
  }

  /**
   * Tests constructor with custom author.
   *
   * @covers ::__construct
   */
  public function testConstructorWithCustomAuthor(): void {
    $calendar = new Calendar(['author' => 'Test Author']);
    $output = $calendar->generateString();

    self::assertStringContainsString('PRODID:-//Test Author//NONSGML//EN', $output);
  }

  /**
   * Tests generateString returns valid ICS format.
   *
   * @covers ::generateString
   */
  public function testGenerateStringReturnsValidIcsFormat(): void {
    $calendar = new Calendar([]);
    $output = $calendar->generateString();

    self::assertStringContainsString('BEGIN:VCALENDAR', $output);
    self::assertStringContainsString('VERSION:2.0', $output);
    self::assertStringContainsString('CALSCALE:GREGORIAN', $output);
    self::assertStringContainsString('END:VCALENDAR', $output);
  }

  /**
   * Tests generateString includes events.
   *
   * @covers ::generateString
   */
  public function testGenerateStringWithEvents(): void {
    // Create a mock event that returns a known string.
    $mockEvent = $this->createMock(CalendarEvent::class);
    $mockEvent->method('generateString')
      ->willReturn("BEGIN:VEVENT\r\nSUMMARY:Test Event\r\nEND:VEVENT\r\n");

    $calendar = new Calendar(['events' => [$mockEvent]]);
    $output = $calendar->generateString();

    self::assertStringContainsString('BEGIN:VEVENT', $output);
    self::assertStringContainsString('SUMMARY:Test Event', $output);
    self::assertStringContainsString('END:VEVENT', $output);
  }

  /**
   * Tests generateString with multiple events.
   *
   * @covers ::generateString
   */
  public function testGenerateStringWithMultipleEvents(): void {
    $mockEvent1 = $this->createMock(CalendarEvent::class);
    $mockEvent1->method('generateString')->willReturn("BEGIN:VEVENT\r\nUID:1\r\nEND:VEVENT\r\n");

    $mockEvent2 = $this->createMock(CalendarEvent::class);
    $mockEvent2->method('generateString')->willReturn("BEGIN:VEVENT\r\nUID:2\r\nEND:VEVENT\r\n");

    $calendar = new Calendar(['events' => [$mockEvent1, $mockEvent2]]);
    $output = $calendar->generateString();

    self::assertStringContainsString('UID:1', $output);
    self::assertStringContainsString('UID:2', $output);
  }

  /**
   * Tests that ICS format has no extra whitespace.
   *
   * @covers ::generateString
   */
  public function testIcsFormatNoExtraWhitespace(): void {
    $calendar = new Calendar([]);
    $output = $calendar->generateString();

    // Check that lines don't have leading whitespace (except after \r\n).
    $lines = explode("\r\n", $output);
    foreach ($lines as $line) {
      if (!empty($line)) {
        // Lines should not start with whitespace.
        self::assertDoesNotMatchRegularExpression('/^\s/', $line, "Line should not start with whitespace: '$line'");
      }
    }
  }

  /**
   * Tests that output ends with END:VCALENDAR and proper line ending.
   *
   * @covers ::generateString
   */
  public function testOutputEndsCorrectly(): void {
    $calendar = new Calendar([]);
    $output = $calendar->generateString();

    self::assertStringEndsWith("END:VCALENDAR\r\n", $output);
  }

}
