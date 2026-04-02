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
 * Unit tests for the restrict_target_groups hook class.
 *
 * @package    local_groupmerge
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_groupmerge\hook\restrict_target_groups
 */
final class restrict_target_groups_test extends \advanced_testcase {
    /**
     * Test that get_courseid returns the course id passed to the constructor.
     */
    public function test_get_courseid(): void {
        $hook = new restrict_target_groups(42);
        $this->assertSame(42, $hook->get_courseid());
    }

    /**
     * Test that a freshly created hook has no unallowed group ids.
     */
    public function test_initial_unallowed_targetgroupids_empty(): void {
        $hook = new restrict_target_groups(1);
        $this->assertEmpty($hook->get_unallowed_targetgroupids());
    }

    /**
     * Test adding a single unallowed group id.
     */
    public function test_add_single_unallowed_groupid(): void {
        $hook = new restrict_target_groups(1);
        $hook->add_unallowed_groupid(25, 'reserved by another plugin');

        $unallowed = $hook->get_unallowed_targetgroupids();
        $this->assertCount(1, $unallowed);
        $this->assertArrayHasKey(25, $unallowed);
        $this->assertSame('reserved by another plugin', $unallowed[25]);
    }

    /**
     * Test adding multiple unallowed group ids.
     */
    public function test_add_multiple_unallowed_groupids(): void {
        $hook = new restrict_target_groups(1);
        $hook->add_unallowed_groupid(25, 'reason one');
        $hook->add_unallowed_groupid(30, 'reason two');
        $hook->add_unallowed_groupid(99, 'reason three');

        $unallowed = $hook->get_unallowed_targetgroupids();
        $this->assertCount(3, $unallowed);
        $this->assertSame('reason one', $unallowed[25]);
        $this->assertSame('reason two', $unallowed[30]);
        $this->assertSame('reason three', $unallowed[99]);
    }

    /**
     * Test that adding the same group id twice overwrites the reason.
     */
    public function test_add_duplicate_groupid_overwrites_reason(): void {
        $hook = new restrict_target_groups(1);
        $hook->add_unallowed_groupid(25, 'first reason');
        $hook->add_unallowed_groupid(25, 'updated reason');

        $unallowed = $hook->get_unallowed_targetgroupids();
        $this->assertCount(1, $unallowed);
        $this->assertSame('updated reason', $unallowed[25]);
    }

    /**
     * Test is_groupid_unallowed returns false when no groups are disallowed.
     */
    public function test_is_groupid_unallowed_returns_false_when_empty(): void {
        $hook = new restrict_target_groups(1);
        $this->assertFalse($hook->is_groupid_unallowed(42));
    }

    /**
     * Test is_groupid_unallowed returns true for a disallowed group.
     */
    public function test_is_groupid_unallowed_returns_true_for_disallowed(): void {
        $hook = new restrict_target_groups(1);
        $hook->add_unallowed_groupid(25, 'reserved');

        $this->assertTrue($hook->is_groupid_unallowed(25));
    }

    /**
     * Test is_groupid_unallowed returns false for a group that was not disallowed.
     */
    public function test_is_groupid_unallowed_returns_false_for_allowed(): void {
        $hook = new restrict_target_groups(1);
        $hook->add_unallowed_groupid(25, 'reserved');

        $this->assertFalse($hook->is_groupid_unallowed(99));
    }
}
