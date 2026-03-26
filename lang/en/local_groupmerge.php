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
 * Lang strings for local_groupmerge.
 *
 * @package   local_groupmerge
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['addmapping'] = 'Add group mapping';
$string['deletemapping'] = 'Delete group mapping';
$string['deletemapping_confirm'] = 'Are you sure you want to delete this group mapping? Members will no longer be synchronised.';
$string['editmapping'] = 'Edit group mapping';
$string['error_circular_mapping'] = 'This mapping would create a circular dependency (the target group is transitively a source group of one of the selected source groups).';
$string['error_targetalreadymapped'] = 'This group already has a mapping. Please edit the existing mapping instead.';
$string['error_targetinsource'] = 'The target group must not be one of the source groups.';
$string['groupmerge:manage'] = 'Manage group merge mappings';
$string['managegroups'] = 'Manage groups';
$string['mappingtype'] = 'Type';
$string['mappingtype_help'] = 'Choose the mapping type. "Cover" means the target group will contain exactly the members of all source groups (extra members will be removed). "Subset" means source group members will be added to the target group, but existing extra members will be kept.';
$string['nomappings'] = 'No group mappings defined yet.';
$string['notenoughgroups'] = 'Merge Groups requires at least 2 groups in this course. Please create more groups first.';
$string['pluginname'] = 'Merge Groups';
$string['plugintitle'] = 'Merge groups';
$string['privacy:metadata'] = 'This plugin does not store any personal data';
$string['sourcegroups'] = 'Source groups';
$string['sourcegroupids'] = 'source groups';
$string['sourcegroupids_help'] = 'Choose the source groups. All participants of these groups will also be assigned to the selected target group. If participants will be added/removed to/from the source groups, they also will be added/removed to/from the target group.';
$string['targetgroup'] = 'Target group';
$string['targetgroupid'] = 'target group';
$string['targetgroupid_help'] = 'Choose the group to which all participants of the selected source groups should be added. If participants will be added/removed to/from the source groups, they also will be added/removed to/from the target group.';
$string['type_cover'] = 'Cover';
$string['type_subset'] = 'Subset';
