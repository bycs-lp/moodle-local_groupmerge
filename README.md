# local_groupmerge - Automatic group membership synchronisation

This plugin allows teachers to link Moodle course groups together so that members of selected source groups are automatically synchronised into a target group. When members are later added to or removed from a source group, the target group is updated in real-time via event observers. Two synchronisation modes are available: **Cover** (target contains exactly the union of all source members) and **Subset** (source members are added but extra members in the target are kept).

## Features

Key features are:

- **Two link types**:
    - *Cover*: The target group will always contain exactly the members of all source groups — extra members are automatically removed.
    - *Subset*: Source group members are added to the target group, but any existing extra members are kept untouched.
- **Real-time synchronisation**: Uses Moodle event observers (`group_member_added`, `group_member_removed`, `group_deleted`) to immediately propagate membership changes.
- **Re-add protection**: If a user is manually removed from a target group while a group link requires their membership, the user is automatically re-added and a notification is shown to the teacher.
- **Circular dependency detection**: The plugin prevents creating group links that would result in circular dependencies (direct or transitive).
- **Transitive chain resolution**: When group links are chained (A -> B -> C), the plugin displays a resolved overview showing the effective source groups for each target.
- **Hook-based extensibility**: Other plugins can restrict which groups may be used as mapping targets by subscribing to the `local_groupmerge\hook\restrict_target_groups` hook.
- **Configuration**: Configuration page accessible via the course navigation under _Participants > Groups > Merge groups_, with modal dialogs for creating/editing links.

## Accessing the configuration

Once installed, teachers with the `local/groupmerge:manage` capability will see a new navigation entry under _Participants > Groups > Merge groups_ in any course. This leads to the configuration page where group links can be created, edited and deleted.

## Link types explained

| Type | Behaviour on member added to source | Behaviour on member removed from source | Extra members in target |
|------|--------------------------------------|------------------------------------------|-------------------------|
| **Cover** | Added to target | Removed from target | Removed automatically |
| **Subset** | Added to target | Kept in target | Kept |

## Hook: Restricting target groups

Other plugins can prevent certain groups from being used as merge targets by implementing a hook callback for `local_groupmerge\hook\restrict_target_groups`:

```php
namespace local_myplugin\hook_listener;

use local_groupmerge\hook\restrict_target_groups;

class groupmerge_listener {
    public static function restrict_managed_groups(restrict_target_groups $hook): void {
        $courseid = $hook->get_courseid();
        // Determine which groups should not be usable as targets.
        $protectedgroups = self::get_protected_group_ids($courseid);

        foreach ($protectedgroups as $groupid) {
            $hook->add_unallowed_groupid(
                $groupid,
                get_string('reason_protected', 'local_myplugin')
            );
        }
    }
}
```

Restricted groups will be removed from the target group selector. A message is displayed that explains why some groups are not available.

## Requirements

- Moodle 4.4 or later (requires hooks and dependency injection support)
- At least 2 groups in a course to create a group link

## Installing via uploaded ZIP file

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/local/groupmerge

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Capabilities

| Capability | Description | Default roles |
|-----------|-------------|---------------|
| `local/groupmerge:manage` | Create, edit and delete group links | editingteacher, manager |

## License

2026, ISB Bayern

Lead developer: Philipp Memmel <philipp.memmel@mailbox.org>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.
