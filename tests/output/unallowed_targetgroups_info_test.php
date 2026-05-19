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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the unallowed_targetgroups_info output class.
 *
 * @package    local_groupmerge
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_groupmerge\output\unallowed_targetgroups_info::class)]
final class unallowed_targetgroups_info_test extends \advanced_testcase {
    /**
     * Test that a single disallowed group produces one entry with correct data.
     */
    public function test_single_group_single_reason(): void {
        global $PAGE;

        $unallowed = [10 => 'blocked by plugin X'];
        $groupoptions = [10 => 'Group A', 20 => 'Group B'];

        $widget = new unallowed_targetgroups_info($unallowed, $groupoptions);
        $data = $widget->export_for_template($PAGE->get_renderer('core'));

        $this->assertCount(1, $data->groups_by_reason);

        $entry = $data->groups_by_reason[0];
        $this->assertSame('blocked by plugin X', $entry['reason']);
        $this->assertCount(1, $entry['groupnames']);
        $this->assertSame('Group A', $entry['groupnames'][0]['name']);
        $this->assertTrue($entry['groupnames'][0]['last']);
    }

    /**
     * Test that multiple groups with the same reason are grouped together.
     */
    public function test_multiple_groups_same_reason(): void {
        global $PAGE;

        $unallowed = [
            10 => 'same reason',
            20 => 'same reason',
            30 => 'same reason',
        ];
        $groupoptions = [10 => 'Group A', 20 => 'Group B', 30 => 'Group C'];

        $widget = new unallowed_targetgroups_info($unallowed, $groupoptions);
        $data = $widget->export_for_template($PAGE->get_renderer('core'));

        $this->assertCount(1, $data->groups_by_reason);

        $entry = $data->groups_by_reason[0];
        $this->assertSame('same reason', $entry['reason']);
        $this->assertCount(3, $entry['groupnames']);

        // Only the last entry should have 'last' => true.
        $this->assertFalse($entry['groupnames'][0]['last']);
        $this->assertFalse($entry['groupnames'][1]['last']);
        $this->assertTrue($entry['groupnames'][2]['last']);

        // Verify names are in the correct order.
        $this->assertSame('Group A', $entry['groupnames'][0]['name']);
        $this->assertSame('Group B', $entry['groupnames'][1]['name']);
        $this->assertSame('Group C', $entry['groupnames'][2]['name']);
    }

    /**
     * Test that groups with different reasons produce separate entries.
     */
    public function test_multiple_groups_different_reasons(): void {
        global $PAGE;

        $unallowed = [
            10 => 'reason alpha',
            20 => 'reason beta',
            30 => 'reason alpha',
        ];
        $groupoptions = [10 => 'Group A', 20 => 'Group B', 30 => 'Group C'];

        $widget = new unallowed_targetgroups_info($unallowed, $groupoptions);
        $data = $widget->export_for_template($PAGE->get_renderer('core'));

        $this->assertCount(2, $data->groups_by_reason);

        // First entry: reason alpha with Group A and Group C.
        $this->assertSame('reason alpha', $data->groups_by_reason[0]['reason']);
        $this->assertCount(2, $data->groups_by_reason[0]['groupnames']);
        $this->assertSame('Group A', $data->groups_by_reason[0]['groupnames'][0]['name']);
        $this->assertFalse($data->groups_by_reason[0]['groupnames'][0]['last']);
        $this->assertSame('Group C', $data->groups_by_reason[0]['groupnames'][1]['name']);
        $this->assertTrue($data->groups_by_reason[0]['groupnames'][1]['last']);

        // Second entry: reason beta with Group B.
        $this->assertSame('reason beta', $data->groups_by_reason[1]['reason']);
        $this->assertCount(1, $data->groups_by_reason[1]['groupnames']);
        $this->assertSame('Group B', $data->groups_by_reason[1]['groupnames'][0]['name']);
        $this->assertTrue($data->groups_by_reason[1]['groupnames'][0]['last']);
    }

    /**
     * Test that a group id not present in groupoptions falls back to the numeric id as string.
     */
    public function test_unknown_group_id_falls_back_to_id_string(): void {
        global $PAGE;

        $unallowed = [999 => 'unknown group reason'];
        $groupoptions = [10 => 'Group A'];

        $widget = new unallowed_targetgroups_info($unallowed, $groupoptions);
        $data = $widget->export_for_template($PAGE->get_renderer('core'));

        $this->assertCount(1, $data->groups_by_reason);
        $this->assertSame('999', $data->groups_by_reason[0]['groupnames'][0]['name']);
    }

    /**
     * Test that an empty unallowed array produces an empty groups_by_reason list.
     */
    public function test_empty_unallowed_produces_empty_context(): void {
        global $PAGE;

        $widget = new unallowed_targetgroups_info([], [10 => 'Group A']);
        $data = $widget->export_for_template($PAGE->get_renderer('core'));

        $this->assertEmpty($data->groups_by_reason);
    }

    /**
     * Test that rendering via $OUTPUT->render() produces valid HTML output.
     */
    public function test_render_produces_html_output(): void {
        global $OUTPUT;

        $unallowed = [
            10 => 'blocked',
            20 => 'blocked',
            30 => 'other reason',
        ];
        $groupoptions = [10 => 'Group A', 20 => 'Group B', 30 => 'Group C'];

        $widget = new unallowed_targetgroups_info($unallowed, $groupoptions);
        $html = $OUTPUT->render($widget);

        // Verify the rendered HTML contains the expected group names and reasons.
        $this->assertStringContainsString('Group A', $html);
        $this->assertStringContainsString('Group B', $html);
        $this->assertStringContainsString('Group C', $html);
        $this->assertStringContainsString('blocked', $html);
        $this->assertStringContainsString('other reason', $html);
        // Verify it's actually an HTML list.
        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('<li>', $html);
    }
}
