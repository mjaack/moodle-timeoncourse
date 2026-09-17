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
 * Turns a stream of log timestamps into sessions and a total time.
 *
 * Deliberately free of any Moodle dependency so the rule that decides how many
 * hours a learner is credited with can be unit tested on its own, and so the
 * same numbers can be reproduced outside Moodle when an auditor asks how a
 * figure was arrived at.
 *
 * @package    report_timeoncourse
 * @copyright  2026 Marcus Jackman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sessioniser {
    /** @var int Largest gap between two clicks that keeps them in one session. */
    protected int $gap;

    /** @var int Sessions shorter than this are discarded entirely. */
    protected int $minsession;

    /** @var int Seconds credited to a session that contains a single click. */
    protected int $singleclick;

    /**
     * Fix the rule that will be applied to every learner in a report.
     *
     * @param int $gap seconds; a longer silence starts a new session.
     * @param int $minsession seconds; sessions below this are dropped.
     * @param int $singleclick seconds credited to a one-click session.
     */
    public function __construct(int $gap = 3600, int $minsession = 60, int $singleclick = 0) {
        if ($gap < 1) {
            throw new \coding_exception('Session gap must be at least one second.');
        }
        if ($minsession < 0 || $singleclick < 0) {
            throw new \coding_exception('Session thresholds cannot be negative.');
        }
        $this->gap = $gap;
        $this->minsession = $minsession;
        $this->singleclick = $singleclick;
    }

    /**
     * Split timestamps into sessions.
     *
     * @param int[] $timestamps unix seconds, any order.
     * @return array[] list of ['start' => int, 'end' => int, 'clicks' => int, 'duration' => int]
     */
    public function sessions(array $timestamps): array {
        $times = array_values(array_filter($timestamps, static fn($t) => is_int($t) || ctype_digit((string)$t)));
        $times = array_map('intval', $times);
        sort($times, SORT_NUMERIC);

        $sessions = [];
        $current = null;
        foreach ($times as $time) {
            if ($current === null) {
                $current = ['start' => $time, 'end' => $time, 'clicks' => 1];
                continue;
            }
            if ($time - $current['end'] <= $this->gap) {
                $current['end'] = $time;
                $current['clicks']++;
                continue;
            }
            $sessions[] = $current;
            $current = ['start' => $time, 'end' => $time, 'clicks' => 1];
        }
        if ($current !== null) {
            $sessions[] = $current;
        }

        $out = [];
        foreach ($sessions as $session) {
            $session['duration'] = $session['clicks'] === 1
                ? $this->singleclick
                : $session['end'] - $session['start'];
            if ($session['duration'] < $this->minsession) {
                continue;
            }
            $out[] = $session;
        }
        return $out;
    }

    /**
     * Total credited seconds for one learner.
     *
     * @param int[] $timestamps unix seconds.
     * @return int seconds
     */
    public function total(array $timestamps): int {
        return array_sum(array_column($this->sessions($timestamps), 'duration'));
    }

    /**
     * Everything a report row needs, in one pass.
     *
     * @param int[] $timestamps unix seconds.
     * @return array ['seconds' => int, 'sessions' => int, 'clicks' => int,
     *                'first' => int|null, 'last' => int|null, 'longest' => int]
     */
    public function summarise(array $timestamps): array {
        $sessions = $this->sessions($timestamps);
        if (!$sessions) {
            return ['seconds' => 0, 'sessions' => 0, 'clicks' => 0,
                    'first' => null, 'last' => null, 'longest' => 0];
        }
        return [
            'seconds' => array_sum(array_column($sessions, 'duration')),
            'sessions' => count($sessions),
            'clicks' => array_sum(array_column($sessions, 'clicks')),
            'first' => $sessions[0]['start'],
            'last' => $sessions[count($sessions) - 1]['end'],
            'longest' => max(array_column($sessions, 'duration')),
        ];
    }

    /**
     * The settings behind a figure, for the audit note printed on every export.
     *
     * @return array
     */
    public function parameters(): array {
        return ['gap' => $this->gap, 'minsession' => $this->minsession, 'singleclick' => $this->singleclick];
    }
}
