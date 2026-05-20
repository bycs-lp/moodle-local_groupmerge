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

namespace local_groupmerge\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use stdClass;

/**
 * Renderable for displaying disallowed target groups grouped by reason.
 *
 * @package    local_groupmerge
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unallowed_targetgroups_info implements renderable, templatable {
    /**
     * Constructor.
     *
     * @param array<int, string> $unallowedtargetgroupids Group ids as keys, reasons as values.
     * @param array<int, string> $groupoptions All available group options (id => name) for name lookup.
     */
    public function __construct(
        /** @var array<int, string> Group ids as keys, reasons as values. */
        private readonly array $unallowedtargetgroupids,
        /** @var array<int, string> All available group options (id => name) for name lookup. */
        private readonly array $groupoptions,
    ) {
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass The template context.
     */
    public function export_for_template(renderer_base $output): stdClass {
        // Group the disallowed group ids by their reason string.
        $groupsbyreason = [];
        foreach ($this->unallowedtargetgroupids as $groupid => $reason) {
            $groupsbyreason[$reason][] = $groupid;
        }

        // Build template context.
        $data = new stdClass();
        $data->groups_by_reason = [];
        foreach ($groupsbyreason as $reason => $groupids) {
            $groupnames = [];
            $lastindex = count($groupids) - 1;
            foreach (array_values($groupids) as $index => $gid) {
                $groupnames[] = [
                    'name' => $this->groupoptions[$gid] ?? (string) $gid,
                    'last' => $index === $lastindex,
                ];
            }
            $data->groups_by_reason[] = [
                'reason' => $reason,
                'groupnames' => $groupnames,
            ];
        }

        return $data;
    }
}
