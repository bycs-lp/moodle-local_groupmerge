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
$string['addlink'] = 'Add group link';
$string['deletelink'] = 'Delete group link';
$string['deletelink_confirm'] = 'Are you sure you want to delete this group link? Members will no longer be synchronised.';
$string['editlink'] = 'Edit group link';
$string['error_circular_link'] = 'This link would create a circular dependency (the target group is transitively a source group of one of the selected source groups).';
$string['error_target_unallowed'] = 'This group cannot be used as a target group: {$a}';
$string['error_targetalreadylinked'] = 'This group already has a link. Please edit the existing link instead.';
$string['error_targetinsource'] = 'The target group must not be one of the source groups.';
$string['groupmerge:manage'] = 'Manage group links';
$string['linkname'] = 'Name';
$string['links_title'] = 'Group links';
$string['linktype'] = 'Type';
$string['linktype_help'] = 'Choose the link type. "Cover" means the target group will contain exactly the members of all source groups (extra members will be removed). "Subset" means source group members will be added to the target group, but existing extra members will be kept.';
$string['managegroups'] = 'Manage groups';
$string['member_readded'] = 'The member was automatically re-added to the group "{$a->groupname}" because a group link requires it. If you do not want this, please <a href="{$a->configurl}">remove the corresponding group link</a>.';
$string['nolinks'] = 'No group links defined yet.';
$string['notenoughgroups'] = '"Merge Groups" requires at least 2 groups in this course. Please create more groups first.';
$string['plugin_desc'] = 'This plugin lets you link groups together so that members are automatically synchronised. When you create a group link, all members of the selected source groups are added to the target group. If someone is later added to or removed from a source group, the target group is updated automatically.';
$string['pluginname'] = 'Merge Groups';
$string['plugintitle'] = 'Merge groups';
$string['privacy:metadata'] = 'This plugin does not store any personal data';
$string['resolvedmappings_desc'] = 'When group links are chained (e.g. Group A feeds into Group B, and Group B feeds into Group C), this table shows you the complete picture: for each target group, it lists all groups whose members will actually end up in that target — including indirect ones.';
$string['resolvedmappingstitle'] = 'Overview: Where do the members come from?';
$string['sourcegroupids'] = 'source groups';
$string['sourcegroupids_help'] = 'Choose the source groups. All participants of these groups will also be assigned to the selected target group. If participants will be added/removed to/from the source groups, they also will be added/removed to/from the target group.';
$string['sourcegroups'] = 'Source groups';
$string['targetgroup'] = 'Target group';
$string['targetgroupid'] = 'target group';
$string['targetgroupid_help'] = 'Choose the group to which all participants of the selected source groups should be added. If participants will be added/removed to/from the source groups, they also will be added/removed to/from the target group.';
$string['type_cover'] = 'Cover';
$string['type_subset'] = 'Subset';
$string['unallowed_targetgroups'] = 'Unavailable target groups';
