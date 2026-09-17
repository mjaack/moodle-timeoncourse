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
 * Admin settings.
 *
 * @package    report_timeoncourse
 * @copyright  2026 Marcus Jackman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'report_timeoncourse',
        get_string('pluginname', 'report_timeoncourse')
    );

    $settings->add(new admin_setting_configduration(
        'report_timeoncourse/sessiongap',
        get_string('sessiongap', 'report_timeoncourse'),
        get_string('sessiongap_desc', 'report_timeoncourse'),
        HOURSECS
    ));

    $settings->add(new admin_setting_configduration(
        'report_timeoncourse/minsession',
        get_string('minsession', 'report_timeoncourse'),
        get_string('minsession_desc', 'report_timeoncourse'),
        MINSECS
    ));

    $settings->add(new admin_setting_configduration(
        'report_timeoncourse/singleclick',
        get_string('singleclick', 'report_timeoncourse'),
        get_string('singleclick_desc', 'report_timeoncourse'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'report_timeoncourse/licensekey',
        get_string('licensekey', 'report_timeoncourse'),
        get_string('licensekey_desc', 'report_timeoncourse'),
        '',
        PARAM_TEXT
    ));

    $ADMIN->add('reports', $settings);
    $ADMIN->add('reports', new admin_externalpage(
        'reporttimeoncourse',
        get_string('pluginname', 'report_timeoncourse'),
        new moodle_url('/report/timeoncourse/index.php'),
        'report/timeoncourse:viewall'
    ));
}
