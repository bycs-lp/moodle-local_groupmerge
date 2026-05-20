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

namespace local_groupmerge\hook;

/**
 * Hook dispatched before the list of available target groups is presented.
 *
 * Subscribers can mark individual groups as disallowed by calling
 * {@see add_unallowed_groupid()}. Disallowed groups will be removed
 * from the target group selector when creating a new mapping.
 *
 * @package    local_groupmerge
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to restrict which groups may be used as mapping targets in local_groupmerge.')]
#[\core\attribute\tags('group', 'local_groupmerge')]
class restrict_target_groups {
    /**
     * Groups that must not be offered as target groups.
     *
     * Keys are group ids (int), values are human-readable reasons (string).
     *
     * @var array<int, string>
     */
    private array $unallowedtargetgroupids = [];

    /**
     * Constructor.
     *
     * @param int $courseid The course id for which target groups are being fetched.
     */
    public function __construct(
        /** @var int The course id for which target groups are being fetched. */
        private readonly int $courseid,
    ) {
    }

    /**
     * Get the course id for which target groups are being fetched.
     *
     * @return int The course id.
     */
    public function get_courseid(): int {
        return $this->courseid;
    }

    /**
     * Mark a group as not allowed to be used as a mapping target.
     *
     * @param int $groupid The group id that should be disallowed.
     * @param string $reason A human-readable reason why the group is disallowed.
     */
    public function add_unallowed_groupid(int $groupid, string $reason): void {
        $this->unallowedtargetgroupids[$groupid] = $reason;
    }

    /**
     * Check whether a specific group is disallowed as a mapping target.
     *
     * @param int $groupid The group id to check.
     * @return bool True if the group has been marked as disallowed.
     */
    public function is_groupid_unallowed(int $groupid): bool {
        return array_key_exists($groupid, $this->unallowedtargetgroupids);
    }

    /**
     * Get all disallowed target group ids with their reasons.
     *
     * Example:
     * [
     *     25 => 'group reserved by local_myothergroupplugin',
     *     30 => 'group has too many members to be a target',
     * ]
     *
     * @return array<int, string> Group ids as keys, reasons as values.
     */
    public function get_unallowed_targetgroupids(): array {
        return $this->unallowedtargetgroupids;
    }
}
