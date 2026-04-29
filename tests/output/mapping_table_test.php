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

use local_groupmerge\local\group_syncer;
use local_groupmerge\local\utils;

/**
 * Unit tests for the mapping_table output class.
 *
 * @package    local_groupmerge
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_groupmerge\output\mapping_table
 */
final class mapping_table_test extends \advanced_testcase {
    /**
     * Test that export_for_template returns correct data for a course without any mappings.
     */
    public function test_export_for_template_empty_course(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $table = new mapping_table($course->id);
        $data = $table->export_for_template($PAGE->get_renderer('core'));

        $this->assertEquals($course->id, $data->courseid);
        $this->assertEmpty($data->mappings);
        $this->assertFalse($data->canaddmapping);
        $this->assertFalse($data->hasresolvedmappings);
        $this->assertEmpty($data->resolvedmappings);
    }

    /**
     * Test that canaddmapping is true when the course has at least 2 groups.
     */
    public function test_export_for_template_canaddmapping_with_enough_groups(): void {
        global $PAGE;
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(2);
        $course = $data['course'];

        $table = new mapping_table($course->id);
        $result = $table->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($result->canaddmapping);
    }

    /**
     * Test that export_for_template includes mappings with member counts and group URLs.
     */
    public function test_export_for_template_with_mappings_includes_member_counts_and_urls(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(3);
        $course = $data['course'];
        $groups = $data['groups'];

        // Add 2 users to source group.
        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $user2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        groups_add_member($groups['Group 1']->id, $user1->id);
        groups_add_member($groups['Group 1']->id, $user2->id);

        utils::create_mapping(
            $course->id,
            $groups['Group 3']->id,
            [$groups['Group 1']->id],
            group_syncer::TYPE_COVER,
            'My mapping'
        );

        $table = new mapping_table($course->id);
        $result = $table->export_for_template($PAGE->get_renderer('core'));

        $this->assertCount(1, $result->mappings);

        $mapping = $result->mappings[0];
        $this->assertEquals('My mapping', $mapping->mappingname);
        $this->assertEquals(group_syncer::TYPE_COVER, $mapping->type);
        $this->assertEquals(get_string('type_cover', 'local_groupmerge'), $mapping->typename);
        $this->assertEquals('type_cover', $mapping->typeicon);

        // Target group: name, member count and URLs.
        $this->assertEquals('Group 3', $mapping->targetgroup->name);
        $this->assertIsInt($mapping->targetgroup->membercount);
        $expectedtargetediturl = (new \moodle_url(
            '/group/group.php',
            ['id' => $groups['Group 3']->id, 'courseid' => $course->id]
        ))->out(false);
        $this->assertEquals($expectedtargetediturl, $mapping->targetgroup->editurl);
        $expectedtargetmembersurl = (new \moodle_url(
            '/group/members.php',
            ['group' => $groups['Group 3']->id]
        ))->out(false);
        $this->assertEquals($expectedtargetmembersurl, $mapping->targetgroup->membersurl);

        // Source group: name, member count "(2)" and URLs.
        $this->assertCount(1, $mapping->sourcegroups);
        $source = $mapping->sourcegroups[0];
        $this->assertEquals('Group 1', $source->name);
        $this->assertEquals(2, $source->membercount);
        $expectedsourceediturl = (new \moodle_url(
            '/group/group.php',
            ['id' => $groups['Group 1']->id, 'courseid' => $course->id]
        ))->out(false);
        $this->assertEquals($expectedsourceediturl, $source->editurl);
        $expectedsourcemembersurl = (new \moodle_url(
            '/group/members.php',
            ['group' => $groups['Group 1']->id]
        ))->out(false);
        $this->assertEquals($expectedsourcemembersurl, $source->membersurl);
    }

    /**
     * Test that subset type is correctly resolved in the template data.
     */
    public function test_export_for_template_subset_type(): void {
        global $PAGE;
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(3);
        $course = $data['course'];
        $groups = $data['groups'];

        utils::create_mapping(
            $course->id,
            $groups['Group 3']->id,
            [$groups['Group 1']->id],
            group_syncer::TYPE_SUBSET
        );

        $table = new mapping_table($course->id);
        $result = $table->export_for_template($PAGE->get_renderer('core'));

        $this->assertCount(1, $result->mappings);
        $this->assertEquals(get_string('type_subset', 'local_groupmerge'), $result->mappings[0]->typename);
        $this->assertEquals('type_subset', $result->mappings[0]->typeicon);
    }

    /**
     * Test that resolved (transitive) mappings are included when chained mappings exist.
     */
    public function test_export_for_template_includes_resolved_mappings(): void {
        global $PAGE;
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(4);
        $course = $data['course'];
        $groups = $data['groups'];

        // Chain: Group 1 -> Group 2, Group 2 -> Group 3.
        utils::create_mapping(
            $course->id,
            $groups['Group 2']->id,
            [$groups['Group 1']->id],
            group_syncer::TYPE_COVER
        );
        utils::create_mapping(
            $course->id,
            $groups['Group 3']->id,
            [$groups['Group 2']->id],
            group_syncer::TYPE_COVER
        );

        $table = new mapping_table($course->id);
        $result = $table->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($result->hasresolvedmappings);
        $this->assertNotEmpty($result->resolvedmappings);

        // Group 3 should have Group 1 as resolved source (transitive through Group 2).
        $resolvedtargetnames = array_map(fn($r) => $r->targetgroup->name, $result->resolvedmappings);
        $this->assertContains('Group 3', $resolvedtargetnames);
    }

    /**
     * Test that no resolved mappings are shown when there are no chained mappings.
     */
    public function test_export_for_template_no_resolved_when_not_chained(): void {
        global $PAGE;
        $this->resetAfterTest();

        $data = $this->getDataGenerator()->get_plugin_generator('local_groupmerge')->create_course_with_groups(3);
        $course = $data['course'];
        $groups = $data['groups'];

        // Single mapping, no chain.
        utils::create_mapping(
            $course->id,
            $groups['Group 3']->id,
            [$groups['Group 1']->id, $groups['Group 2']->id],
            group_syncer::TYPE_COVER
        );

        $table = new mapping_table($course->id);
        $result = $table->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($result->hasresolvedmappings);
        $this->assertEmpty($result->resolvedmappings);
    }

    /**
     * Test that the help icon data is included in the export.
     */
    public function test_export_for_template_includes_helpicon(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $table = new mapping_table($course->id);
        $result = $table->export_for_template($PAGE->get_renderer('core'));

        $this->assertNotEmpty($result->helpicon);
    }
}
