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
 * Reads the standard log and reports credited learner time.
 *
 * @package    report_timeoncourse
 * @copyright  2026 Marcus Jackman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report {
    /** @var string[] Log origins that are not a human sitting at a screen. */
    public const IGNORED_ORIGINS = ['cli', 'cron', 'restore'];

    /** @var sessioniser */
    protected sessioniser $sessioniser;

    /**
     * Build a report reader.
     *
     * @param sessioniser|null $sessioniser defaults to the site's configured thresholds.
     */
    public function __construct(?sessioniser $sessioniser = null) {
        $this->sessioniser = $sessioniser ?? self::configured_sessioniser();
    }

    /**
     * Build a sessioniser from the site admin settings.
     *
     * @return sessioniser
     */
    public static function configured_sessioniser(): sessioniser {
        return new sessioniser(
            (int)(get_config('report_timeoncourse', 'sessiongap') ?: 3600),
            (int)(get_config('report_timeoncourse', 'minsession') ?: 60),
            (int)(get_config('report_timeoncourse', 'singleclick') ?: 0),
        );
    }

    /**
     * Credited time per learner in one course.
     *
     * @param int $courseid
     * @param int $from unix seconds, 0 for no lower bound.
     * @param int $to unix seconds, 0 for no upper bound.
     * @param int[] $userids optional restriction to these users.
     * @return array[] rows keyed by userid, each the sessioniser summary plus 'userid'.
     */
    public function course(int $courseid, int $from = 0, int $to = 0, array $userids = []): array {
        return $this->aggregate(['courseid' => $courseid], $from, $to, $userids);
    }

    /**
     * Credited time per learner across every course on the site.
     *
     * @param int $from unix seconds, 0 for no lower bound.
     * @param int $to unix seconds, 0 for no upper bound.
     * @param int[] $userids optional restriction to these users.
     * @return array[] rows keyed by userid.
     */
    public function site(int $from = 0, int $to = 0, array $userids = []): array {
        return $this->aggregate([], $from, $to, $userids);
    }

    /**
     * Credited time for one learner broken down course by course.
     *
     * @param int $userid
     * @param int $from unix seconds, 0 for no lower bound.
     * @param int $to unix seconds, 0 for no upper bound.
     * @return array[] rows keyed by courseid.
     */
    public function user_by_course(int $userid, int $from = 0, int $to = 0): array {
        global $DB;
        [$where, $params] = $this->conditions(['userid' => $userid], $from, $to, []);
        $sql = "SELECT id, courseid, timecreated
                  FROM {logstore_standard_log}
                 WHERE $where
              ORDER BY courseid, timecreated";
        $rows = [];
        $current = null;
        $times = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $record) {
            $courseid = (int)$record->courseid;
            if ($current !== null && $courseid !== $current) {
                $rows[$current] = ['courseid' => $current] + $this->sessioniser->summarise($times);
                $times = [];
            }
            $current = $courseid;
            $times[] = (int)$record->timecreated;
        }
        $rs->close();
        if ($current !== null) {
            $rows[$current] = ['courseid' => $current] + $this->sessioniser->summarise($times);
        }
        return $rows;
    }

    /**
     * One streaming pass over the log, folded into per-user sessions.
     *
     * Ordered by userid so only one learner's timestamps are ever held in
     * memory -- a year of site-wide log on a large site will not fit otherwise.
     *
     * @param array $scope column => value restrictions (courseid, userid).
     * @param int $from
     * @param int $to
     * @param int[] $userids
     * @return array[]
     */
    protected function aggregate(array $scope, int $from, int $to, array $userids): array {
        global $DB;
        [$where, $params] = $this->conditions($scope, $from, $to, $userids);
        $sql = "SELECT id, userid, timecreated
                  FROM {logstore_standard_log}
                 WHERE $where
              ORDER BY userid, timecreated";

        $rows = [];
        $current = null;
        $times = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $record) {
            $userid = (int)$record->userid;
            if ($current !== null && $userid !== $current) {
                $rows[$current] = ['userid' => $current] + $this->sessioniser->summarise($times);
                $times = [];
            }
            $current = $userid;
            $times[] = (int)$record->timecreated;
        }
        $rs->close();
        if ($current !== null) {
            $rows[$current] = ['userid' => $current] + $this->sessioniser->summarise($times);
        }
        return array_filter($rows, static fn($row) => $row['sessions'] > 0);
    }

    /**
     * Shared WHERE clause.
     *
     * @param array $scope
     * @param int $from
     * @param int $to
     * @param int[] $userids
     * @return array [string $where, array $params]
     */
    protected function conditions(array $scope, int $from, int $to, array $userids): array {
        global $DB;
        $where = ['userid > 0'];
        $params = [];
        foreach ($scope as $column => $value) {
            $where[] = "$column = :$column";
            $params[$column] = $value;
        }
        if ($from > 0) {
            $where[] = 'timecreated >= :fromtime';
            $params['fromtime'] = $from;
        }
        if ($to > 0) {
            $where[] = 'timecreated <= :totime';
            $params['totime'] = $to;
        }
        if ($userids) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
            $where[] = "userid $insql";
            $params += $inparams;
        }
        [$originsql, $originparams] = $DB->get_in_or_equal(self::IGNORED_ORIGINS, SQL_PARAMS_NAMED, 'o', false);
        $where[] = "(origin IS NULL OR origin $originsql)";
        $params += $originparams;

        return [implode(' AND ', $where), $params];
    }

    /**
     * The methodology line that goes on every export, so a figure can be defended.
     *
     * @return string
     */
    public function methodology(): string {
        $p = $this->sessioniser->parameters();
        return get_string('methodology', 'report_timeoncourse', (object)[
            'gap' => format_time($p['gap']),
            'minsession' => format_time($p['minsession']),
            'singleclick' => $p['singleclick'],
            'ignored' => implode(', ', self::IGNORED_ORIGINS),
        ]);
    }
}
