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

use core\output\help_icon;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use local_groupmerge\local\group_syncer;
use local_groupmerge\local\utils;
use stdClass;

/**
 * Class mapping_table
 *
 * @package   local_groupmerge
 * @copyright 2026 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mapping_table implements renderable, templatable {
    /**
     * Create the mapping_table widget object.
     */
    public function __construct(
        /** @var int $courseid the id of the course to create the widget for */
        private readonly int $courseid
    ) {
    }
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass the template context
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $grouped = utils::get_group_mappings_with_group_name($this->courseid);

        // Collect all unique group ids and count members once per group.
        $groupids = [];
        foreach ($grouped as $mapping) {
            $groupids[$mapping->targetgroup->id] = true;
            foreach ($mapping->sourcegroups as $sourcegroup) {
                $groupids[$sourcegroup->id] = true;
            }
        }
        $membercounts = [];
        foreach (array_keys($groupids) as $groupid) {
            // Not superefficient to use a single query per group.
            // However, we usually do not have that many mappings and the queries are fast, so using
            // the core method is fine here.
            $membercounts[$groupid] = count(groups_get_members($groupid, 'u.id'));
        }

        // Add member counts and group management URLs to each group.
        foreach ($grouped as $mapping) {
            $targetid = $mapping->targetgroup->id;
            $mapping->targetgroup->membercount = $membercounts[$targetid];
            $mapping->targetgroup->editurl = (new \moodle_url(
                '/group/group.php',
                ['id' => $targetid, 'courseid' => $this->courseid]
            ))->out(false);
            $mapping->targetgroup->membersurl = (new \moodle_url(
                '/group/members.php',
                ['group' => $targetid]
            ))->out(false);
            foreach ($mapping->sourcegroups as $sourcegroup) {
                $sourcegroup->membercount = $membercounts[$sourcegroup->id];
                $sourcegroup->editurl = (new \moodle_url(
                    '/group/group.php',
                    ['id' => $sourcegroup->id, 'courseid' => $this->courseid]
                ))->out(false);
                $sourcegroup->membersurl = (new \moodle_url(
                    '/group/members.php',
                    ['group' => $sourcegroup->id]
                ))->out(false);
            }
            $mapping->typename = $mapping->type === group_syncer::TYPE_COVER
                ? get_string('type_cover', 'local_groupmerge')
                : get_string('type_subset', 'local_groupmerge');
            $mapping->typeicon = $mapping->type === group_syncer::TYPE_COVER
                ? 'type_cover'
                : 'type_subset';
            // Ensure mappingname is available for template.
            $mapping->mappingname = $mapping->mappingname ?? '';
        }

        $canaddmapping = utils::can_add_mapping($this->courseid);

        $data = new stdClass();
        $data->courseid = $this->courseid;
        $data->canaddmapping = $canaddmapping;
        $data->mappings = $grouped;
        $mappinghelpicon = new help_icon('mappingtype', 'local_groupmerge');
        $data->helpicon = $mappinghelpicon->export_for_template($output);
        $namehelpicon = new help_icon('mappingname', 'local_groupmerge');
        $data->namehelpicon = $namehelpicon->export_for_template($output);

        // Build resolved (transitive) mappings for the second table.
        $resolved = utils::get_resolved_mappings_for_course($this->courseid);
        $data->resolvedmappings = $resolved;
        $data->hasresolvedmappings = !empty($resolved);

        return $data;
    }
}
