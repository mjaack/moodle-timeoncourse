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

/**
 * Strings.
 *
 * @package    report_timeoncourse
 * @copyright  2026 Marcus Jackman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Time on course';
$string['timeoncourse:view'] = 'View time on course for a course';
$string['timeoncourse:viewall'] = 'View time on course across the whole site';
$string['privacy:metadata'] = 'The Time on course report displays time calculated from the standard log. It stores no personal data of its own.';

$string['sessiongap'] = 'Session gap';
$string['sessiongap_desc'] = 'A silence longer than this starts a new session. Clicks closer together than this are treated as continuous work.';
$string['minsession'] = 'Minimum session';
$string['minsession_desc'] = 'Sessions shorter than this are discarded, so a single glance at a page is not credited as study time.';
$string['singleclick'] = 'Single-click credit';
$string['singleclick_desc'] = 'Seconds credited to a session containing exactly one click. Zero is the conservative choice and the default.';
$string['licensekey'] = 'Licence key';
$string['licensekey_desc'] = 'The key issued when you bought Time on course. Site-wide reports and exports need it; per-course reports do not.';
$string['licenceactive'] = 'Licence active.';
$string['licenceinactive'] = 'Licence not valid. Site-wide reporting and export are unavailable.';
$string['licenceabsent'] = 'No licence key entered. Per-course reporting is available; site-wide reporting and export are not.';

$string['from'] = 'From';
$string['to'] = 'To';
$string['scope'] = 'Scope';
$string['scopecourse'] = 'This course';
$string['scopesite'] = 'Whole site';
$string['generate'] = 'Show report';
$string['exportcsv'] = 'Download CSV';
$string['participant'] = 'Participant';
$string['course'] = 'Course';
$string['timespent'] = 'Time credited';
$string['sessions'] = 'Sessions';
$string['clicks'] = 'Clicks';
$string['firstaccess'] = 'First activity';
$string['lastaccess'] = 'Last activity';
$string['longest'] = 'Longest session';
$string['norows'] = 'No logged activity in this period.';
$string['methodology'] = 'Time is estimated from the standard log. Clicks within {$a->gap} of one another count as one session; sessions shorter than {$a->minsession} are discarded; a session of one click is credited {$a->singleclick} seconds. Activity originating from {$a->ignored} is excluded.';
$string['generatedon'] = 'Generated {$a->when} by {$a->who}';
