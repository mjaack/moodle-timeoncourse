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
 * Time on course report.
 *
 * @package    report_timeoncourse
 * @copyright  2026 Marcus Jackman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use report_timeoncourse\licence;
use report_timeoncourse\report;

$courseid = optional_param('id', 0, PARAM_INT);
$fromdate = optional_param('from', '', PARAM_RAW_TRIMMED);
$todate = optional_param('to', '', PARAM_RAW_TRIMMED);
$download = optional_param('download', 0, PARAM_BOOL);

$from = $fromdate ? (int)strtotime($fromdate . ' 00:00:00') : 0;
$to = $todate ? (int)strtotime($todate . ' 23:59:59') : 0;

$params = array_filter(['id' => $courseid, 'from' => $fromdate, 'to' => $todate]);
$url = new moodle_url('/report/timeoncourse/index.php', $params);

if ($courseid) {
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($course->id);
    require_capability('report/timeoncourse:view', $context);
    $heading = format_string($course->fullname);
} else {
    require_login();
    $context = context_system::instance();
    require_capability('report/timeoncourse:viewall', $context);
    $course = null;
    $heading = get_string('scopesite', 'report_timeoncourse');
    if (!licence::active()) {
        throw new moodle_exception('licenceinactive', 'report_timeoncourse', new moodle_url('/'));
    }
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout($courseid ? 'incourse' : 'admin');
$PAGE->set_title(get_string('pluginname', 'report_timeoncourse'));
$PAGE->set_heading($heading);

$report = new report();
$rows = $courseid
    ? $report->course($courseid, $from, $to)
    : $report->site($from, $to);

$userids = array_column($rows, 'userid');
$users = $userids ? $DB->get_records_list('user', 'id', $userids, '', 'id, firstname, lastname, email, idnumber') : [];

if ($download) {
    if (!licence::active()) {
        throw new moodle_exception('licenceinactive', 'report_timeoncourse', $url);
    }
    require_sesskey();
    $csv = new csv_export_writer();
    $csv->set_filename('timeoncourse-' . ($courseid ?: 'site') . '-' . date('Ymd'));
    $csv->add_data([
        get_string('participant', 'report_timeoncourse'),
        'email',
        'idnumber',
        get_string('timespent', 'report_timeoncourse') . ' (hh:mm:ss)',
        get_string('timespent', 'report_timeoncourse') . ' (seconds)',
        get_string('sessions', 'report_timeoncourse'),
        get_string('clicks', 'report_timeoncourse'),
        get_string('firstaccess', 'report_timeoncourse'),
        get_string('lastaccess', 'report_timeoncourse'),
        get_string('longest', 'report_timeoncourse'),
    ]);
    foreach ($rows as $row) {
        $user = $users[$row['userid']] ?? null;
        $csv->add_data([
            $user ? fullname($user) : (string)$row['userid'],
            $user->email ?? '',
            $user->idnumber ?? '',
            report_timeoncourse_hms($row['seconds']),
            (string)$row['seconds'],
            (string)$row['sessions'],
            (string)$row['clicks'],
            $row['first'] ? userdate($row['first']) : '',
            $row['last'] ? userdate($row['last']) : '',
            report_timeoncourse_hms($row['longest']),
        ]);
    }
    $csv->add_data([]);
    $csv->add_data([$report->methodology()]);
    $csv->add_data([get_string('generatedon', 'report_timeoncourse', (object)[
        'when' => userdate(time()),
        'who' => fullname($USER),
    ])]);
    $csv->download_file();
    exit;
}

/**
 * Seconds as hh:mm:ss, which is what an evidence file is expected to carry.
 *
 * @param int $seconds
 * @return string
 */
function report_timeoncourse_hms(int $seconds): string {
    return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'report_timeoncourse'));

echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'mb-3 d-flex flex-wrap gap-2 align-items-end']);
if ($courseid) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
}
foreach (['from' => $fromdate, 'to' => $todate] as $name => $value) {
    echo html_writer::start_div('me-2');
    echo html_writer::label(get_string($name, 'report_timeoncourse'), 'tc-' . $name, true, ['class' => 'd-block']);
    echo html_writer::empty_tag('input', [
        'type' => 'date', 'id' => 'tc-' . $name, 'name' => $name,
        'value' => s($value), 'class' => 'form-control',
    ]);
    echo html_writer::end_div();
}
echo html_writer::empty_tag('input', [
    'type' => 'submit', 'class' => 'btn btn-primary',
    'value' => get_string('generate', 'report_timeoncourse'),
]);
echo html_writer::end_tag('form');

if (!$rows) {
    echo $OUTPUT->notification(get_string('norows', 'report_timeoncourse'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('participant', 'report_timeoncourse'),
        get_string('timespent', 'report_timeoncourse'),
        get_string('sessions', 'report_timeoncourse'),
        get_string('clicks', 'report_timeoncourse'),
        get_string('firstaccess', 'report_timeoncourse'),
        get_string('lastaccess', 'report_timeoncourse'),
        get_string('longest', 'report_timeoncourse'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($rows as $row) {
        $user = $users[$row['userid']] ?? null;
        $table->data[] = [
            $user ? fullname($user) : (string)$row['userid'],
            report_timeoncourse_hms($row['seconds']),
            $row['sessions'],
            $row['clicks'],
            $row['first'] ? userdate($row['first']) : '',
            $row['last'] ? userdate($row['last']) : '',
            report_timeoncourse_hms($row['longest']),
        ];
    }
    echo html_writer::table($table);

    if (licence::active()) {
        echo $OUTPUT->single_button(
            new moodle_url($url, ['download' => 1, 'sesskey' => sesskey()]),
            get_string('exportcsv', 'report_timeoncourse'),
            'get'
        );
    } else {
        echo $OUTPUT->notification(licence::status_text(), 'info');
    }
}

echo html_writer::tag('p', s($report->methodology()), ['class' => 'text-muted small mt-3']);
echo $OUTPUT->footer();
