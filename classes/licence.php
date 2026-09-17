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
 * Subscription check for the paid features.
 *
 * The Moodle Marketplace records the transaction but does not manage
 * entitlement, so the key a customer pastes in is their Stripe subscription
 * id and Stripe stays the single source of truth. The result is cached so a
 * site that loses outbound network keeps working for the grace period rather
 * than losing its reports mid-audit.
 *
 * Nothing here gates the free tier: per-course reports always work.
 *
 * @package    report_timeoncourse
 * @copyright  2026 Marcus Jackman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class licence {
    /** @var string Verification endpoint. */
    public const ENDPOINT = 'https://edgethirteen.com/api/tools/timeoncourse/verify-license';

    /** @var int Re-check this often. */
    public const RECHECK = 3 * DAYSECS;

    /** @var int Keep trusting the last good answer this long if the check cannot run. */
    public const GRACE = 14 * DAYSECS;

    /**
     * Is this site entitled to the paid features right now?
     *
     * @param bool $force skip the cache.
     * @return bool
     */
    public static function active(bool $force = false): bool {
        $key = trim((string)get_config('report_timeoncourse', 'licensekey'));
        if ($key === '') {
            return false;
        }

        $checked = (int)get_config('report_timeoncourse', 'licensechecked');
        $valid = (int)get_config('report_timeoncourse', 'licensevalid') === 1;
        $fresh = $checked > 0 && (time() - $checked) < self::RECHECK;
        if (!$force && $fresh) {
            return $valid;
        }

        $result = self::verify($key);
        if ($result === null) {
            // Endpoint unreachable: hold the last good answer until the grace runs out.
            return $valid && $checked > 0 && (time() - $checked) < self::GRACE;
        }

        set_config('licensevalid', $result ? 1 : 0, 'report_timeoncourse');
        set_config('licensechecked', time(), 'report_timeoncourse');
        return $result;
    }

    /**
     * Ask the endpoint about a key.
     *
     * @param string $key
     * @return bool|null true/false on an answer, null when the check could not be made.
     */
    public static function verify(string $key): ?bool {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $response = $curl->post(self::ENDPOINT, json_encode([
            'key' => $key,
            'site' => $CFG->wwwroot,
            'component' => 'report_timeoncourse',
        ]), [
            'CURLOPT_HTTPHEADER' => ['Content-Type: application/json'],
            'CURLOPT_TIMEOUT' => 10,
            'CURLOPT_CONNECTTIMEOUT' => 5,
        ]);

        $info = $curl->get_info();
        if ($curl->get_errno() || empty($info['http_code']) || $info['http_code'] >= 500) {
            return null;
        }
        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded) || !array_key_exists('valid', $decoded)) {
            return null;
        }
        return (bool)$decoded['valid'];
    }

    /**
     * Wording for the settings page and the report header.
     *
     * @return string
     */
    public static function status_text(): string {
        if (trim((string)get_config('report_timeoncourse', 'licensekey')) === '') {
            return get_string('licenceabsent', 'report_timeoncourse');
        }
        return self::active()
            ? get_string('licenceactive', 'report_timeoncourse')
            : get_string('licenceinactive', 'report_timeoncourse');
    }
}
