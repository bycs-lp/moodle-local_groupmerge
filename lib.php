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
 * lib file for local_groupmerge.
 *
 * @package    local_groupmerge
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Hook function to add a navigation node for the config page to the groups tertiary navigation.
 *
 * @param navigation_node $parentnode the navigation course node
 * @param stdClass $course the course object
 * @param context_course $context the course context object
 */
function local_groupmerge_extend_navigation_course(
        navigation_node $parentnode,
        stdClass $course,
        context_course $context
) {
    if (!has_capability('local/groupmerge:use', $context)) {
        return;
    }
    $usersnode = $parentnode->find('users', navigation_node::TYPE_UNKNOWN);
    $groupparentnode = $parentnode->find('groups', navigation_node::TYPE_SETTING);

    if ($groupparentnode && $usersnode) {
        $groupnode = navigation_node::create(
                $groupparentnode->text,
                $groupparentnode->action,
                $groupparentnode->type,
                $groupparentnode->shorttext,
                $groupparentnode->key,
                $groupparentnode->icon
        );

        $groupparentnode->type = navigation_node::TYPE_UNKNOWN;
        $groupparentnode->action = null;
        $groupparentnode->key = 'groupsparent';

        $groupparentnode->add_node($groupnode);

        // Now add new link for local_groupmerge.
        $url = new moodle_url('/local/groupmerge/groupmerge_config.php', ['courseid' => $course->id]);

        $groupparentnode->add(
                get_string('plugintitle', 'local_groupmerge'),
                $url
        );
    }
}
