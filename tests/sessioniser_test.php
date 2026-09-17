<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace report_timeoncourse;

/**
 * Tests for the session rule.
 *
 * @package    report_timeoncourse
 * @copyright  2026 Marcus Jackman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sessioniser::class)]
final class sessioniser_test extends \basic_testcase {
    /** @var int A fixed Monday 09:00 to anchor every case. */
    private const BASE = 1757667600;

    public function test_no_activity_is_no_time(): void {
        $s = new sessioniser();
        $this->assertSame(0, $s->total([]));
        $this->assertSame(0, $s->summarise([])['sessions']);
        $this->assertNull($s->summarise([])['first']);
    }

    public function test_continuous_clicks_are_one_session(): void {
        $s = new sessioniser(3600, 60);
        $clicks = [self::BASE, self::BASE + 600, self::BASE + 1200, self::BASE + 1800];
        $sessions = $s->sessions($clicks);
        $this->assertCount(1, $sessions);
        $this->assertSame(1800, $sessions[0]['duration']);
        $this->assertSame(4, $sessions[0]['clicks']);
    }

    public function test_a_gap_longer_than_the_threshold_splits(): void {
        $s = new sessioniser(3600, 60);
        $clicks = [
            self::BASE, self::BASE + 900, // 15 minutes.
            self::BASE + 900 + 3601, self::BASE + 900 + 3601 + 600, // After a 60m01s silence.
        ];
        $sessions = $s->sessions($clicks);
        $this->assertCount(2, $sessions);
        $this->assertSame(900, $sessions[0]['duration']);
        $this->assertSame(600, $sessions[1]['duration']);
        $this->assertSame(1500, $s->total($clicks));
    }

    public function test_a_gap_exactly_on_the_threshold_does_not_split(): void {
        $s = new sessioniser(3600, 0);
        $clicks = [self::BASE, self::BASE + 3600, self::BASE + 3600 + 60];
        $this->assertCount(1, $s->sessions($clicks));
        $this->assertSame(3660, $s->total($clicks));
    }

    public function test_short_sessions_are_discarded(): void {
        $s = new sessioniser(3600, 300);
        $clicks = [self::BASE, self::BASE + 120];
        $this->assertSame([], $s->sessions($clicks));
        $this->assertSame(0, $s->total($clicks));
    }

    public function test_a_lone_click_is_worth_nothing_by_default(): void {
        $s = new sessioniser(3600, 0);
        $this->assertSame(0, $s->total([self::BASE]));
    }

    public function test_a_lone_click_can_be_credited_when_configured(): void {
        $s = new sessioniser(3600, 0, 300);
        $this->assertSame(300, $s->total([self::BASE]));
        // And the credit must not apply to multi-click sessions.
        $this->assertSame(600, $s->total([self::BASE, self::BASE + 600]));
    }

    public function test_unordered_input_is_handled(): void {
        $s = new sessioniser(3600, 60);
        $ordered = [self::BASE, self::BASE + 300, self::BASE + 900];
        $shuffled = [self::BASE + 900, self::BASE, self::BASE + 300];
        $this->assertSame($s->total($ordered), $s->total($shuffled));
        $this->assertSame(900, $s->total($shuffled));
    }

    public function test_summary_reports_boundaries_and_longest(): void {
        $s = new sessioniser(3600, 60);
        $clicks = [
            self::BASE, self::BASE + 600, // 10 minutes.
            self::BASE + 600 + 7200, self::BASE + 600 + 7200 + 3000, // 50 minutes, later.
        ];
        $summary = $s->summarise($clicks);
        $this->assertSame(3600, $summary['seconds']);
        $this->assertSame(2, $summary['sessions']);
        $this->assertSame(4, $summary['clicks']);
        $this->assertSame(self::BASE, $summary['first']);
        $this->assertSame(self::BASE + 600 + 7200 + 3000, $summary['last']);
        $this->assertSame(3000, $summary['longest']);
    }

    public function test_duplicate_timestamps_do_not_inflate_time(): void {
        $s = new sessioniser(3600, 0);
        $clicks = [self::BASE, self::BASE, self::BASE + 60, self::BASE + 60];
        $this->assertSame(60, $s->total($clicks));
    }

    public function test_thresholds_are_reported_for_the_audit_note(): void {
        $s = new sessioniser(1800, 120, 30);
        $this->assertSame(['gap' => 1800, 'minsession' => 120, 'singleclick' => 30], $s->parameters());
    }

    public function test_an_impossible_gap_is_rejected(): void {
        $this->expectException(\coding_exception::class);
        new sessioniser(0);
    }

    public function test_negative_thresholds_are_rejected(): void {
        $this->expectException(\coding_exception::class);
        new sessioniser(3600, -1);
    }
}
