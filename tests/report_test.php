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
 * Tests the report against a real log table.
 *
 * @package    report_timeoncourse
 * @copyright  2026 Marcus Jackman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(report::class)]
final class report_test extends \advanced_testcase {
    /** @var int Fixed anchor so every assertion is arithmetic, not wall clock. */
    private const BASE = 1757667600;

    /**
     * Put a row in the standard log directly: the report reads the table, so
     * the table is what the test has to be honest about.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $time
     * @param string|null $origin
     */
    private function log(int $userid, int $courseid, int $time, ?string $origin = 'web'): void {
        global $DB;
        $DB->insert_record('logstore_standard_log', (object)[
            'eventname' => '\core\event\course_viewed',
            'component' => 'core',
            'action' => 'viewed',
            'target' => 'course',
            'crud' => 'r',
            'edulevel' => 2,
            'contextid' => \context_course::instance($courseid)->id,
            'contextlevel' => CONTEXT_COURSE,
            'contextinstanceid' => $courseid,
            'userid' => $userid,
            'courseid' => $courseid,
            'anonymous' => 0,
            'timecreated' => $time,
            'origin' => $origin,
            'ip' => '10.0.0.1',
        ]);
    }

    public function test_course_report_credits_each_learner_separately(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();

        // Alice: 30 minutes in one sitting.
        $this->log($alice->id, $course->id, self::BASE);
        $this->log($alice->id, $course->id, self::BASE + 900);
        $this->log($alice->id, $course->id, self::BASE + 1800);
        // Bob: 10 minutes, then 20 minutes after a two hour break.
        $this->log($bob->id, $course->id, self::BASE);
        $this->log($bob->id, $course->id, self::BASE + 600);
        $this->log($bob->id, $course->id, self::BASE + 7800);
        $this->log($bob->id, $course->id, self::BASE + 9000);

        $rows = (new report(new sessioniser(3600, 60)))->course($course->id);

        $this->assertSame(1800, $rows[$alice->id]['seconds']);
        $this->assertSame(1, $rows[$alice->id]['sessions']);
        $this->assertSame(1800, $rows[$bob->id]['seconds']);
        $this->assertSame(2, $rows[$bob->id]['sessions']);
        $this->assertSame(1200, $rows[$bob->id]['longest']);
    }

    public function test_other_courses_do_not_leak_in(): void {
        $this->resetAfterTest();
        $one = $this->getDataGenerator()->create_course();
        $two = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->log($user->id, $one->id, self::BASE);
        $this->log($user->id, $one->id, self::BASE + 600);
        $this->log($user->id, $two->id, self::BASE + 1200);
        $this->log($user->id, $two->id, self::BASE + 3000);

        $report = new report(new sessioniser(3600, 60));
        $this->assertSame(600, $report->course($one->id)[$user->id]['seconds']);
        $this->assertSame(1800, $report->course($two->id)[$user->id]['seconds']);
    }

    public function test_date_range_clips_the_log(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Last month: 20 minutes. This month: 10 minutes.
        $this->log($user->id, $course->id, self::BASE - 30 * DAYSECS);
        $this->log($user->id, $course->id, self::BASE - 30 * DAYSECS + 1200);
        $this->log($user->id, $course->id, self::BASE);
        $this->log($user->id, $course->id, self::BASE + 600);

        $report = new report(new sessioniser(3600, 60));
        $this->assertSame(1800, $report->course($course->id)[$user->id]['seconds']);
        $this->assertSame(600, $report->course($course->id, self::BASE - DAYSECS)[$user->id]['seconds']);
        $this->assertSame(
            1200,
            $report->course($course->id, 0, self::BASE - DAYSECS)[$user->id]['seconds']
        );
    }

    public function test_cron_and_cli_activity_is_not_credited(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->log($user->id, $course->id, self::BASE, 'cron');
        $this->log($user->id, $course->id, self::BASE + 1800, 'cli');
        $this->log($user->id, $course->id, self::BASE + 3600, 'restore');

        $this->assertSame([], (new report(new sessioniser(3600, 60)))->course($course->id));
    }

    public function test_mobile_app_activity_is_credited(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->log($user->id, $course->id, self::BASE, 'ws');
        $this->log($user->id, $course->id, self::BASE + 900, 'ws');

        $this->assertSame(900, (new report(new sessioniser(3600, 60)))->course($course->id)[$user->id]['seconds']);
    }

    public function test_site_report_sums_across_courses(): void {
        $this->resetAfterTest();
        $one = $this->getDataGenerator()->create_course();
        $two = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->log($user->id, $one->id, self::BASE);
        $this->log($user->id, $one->id, self::BASE + 600);
        $this->log($user->id, $two->id, self::BASE + 7200);
        $this->log($user->id, $two->id, self::BASE + 9000);

        $rows = (new report(new sessioniser(3600, 60)))->site();
        $this->assertSame(2400, $rows[$user->id]['seconds']);
        $this->assertSame(2, $rows[$user->id]['sessions']);
    }

    public function test_per_user_breakdown_by_course(): void {
        $this->resetAfterTest();
        $one = $this->getDataGenerator()->create_course();
        $two = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->log($user->id, $one->id, self::BASE);
        $this->log($user->id, $one->id, self::BASE + 600);
        $this->log($user->id, $two->id, self::BASE + 7200);
        $this->log($user->id, $two->id, self::BASE + 8400);

        $rows = (new report(new sessioniser(3600, 60)))->user_by_course($user->id);
        $this->assertSame(600, $rows[$one->id]['seconds']);
        $this->assertSame(1200, $rows[$two->id]['seconds']);
    }

    public function test_guest_and_system_activity_is_excluded(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        // User id 0 is cron/system activity, never a learner.
        $this->log(0, $course->id, self::BASE);
        $this->log(0, $course->id, self::BASE + 600);

        $this->assertSame([], (new report(new sessioniser(3600, 60)))->course($course->id));
    }

    public function test_settings_drive_the_default_sessioniser(): void {
        $this->resetAfterTest();
        set_config('sessiongap', 1800, 'report_timeoncourse');
        set_config('minsession', 120, 'report_timeoncourse');
        set_config('singleclick', 45, 'report_timeoncourse');

        $this->assertSame(
            ['gap' => 1800, 'minsession' => 120, 'singleclick' => 45],
            report::configured_sessioniser()->parameters()
        );
    }

    public function test_methodology_states_the_thresholds_used(): void {
        $this->resetAfterTest();
        $text = (new report(new sessioniser(1800, 120)))->methodology();
        $this->assertStringContainsString('30 mins', $text);
        $this->assertStringContainsString('2 mins', $text);
        $this->assertStringContainsString('cron', $text);
    }
}
