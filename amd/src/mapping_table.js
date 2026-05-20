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
 * JS module for managing group merge mappings.
 *
 * @module     local_groupmerge/mapping_table
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalForm from 'core_form/modalform';
import ModalEvents from 'core/modal_events';
import {getString} from 'core/str';
import Notification from 'core/notification';

const SELECTORS = {
    TABLE: '#local_groupmerge-mapping-table',
    ADD_BUTTON: '[data-action="addmapping"]',
    EDIT_BUTTON: '[data-action="edit"]',
    DELETE_BUTTON: '[data-action="delete"]',
};

const FORM_CLASS = 'local_groupmerge\\form\\mapping_form';

/**
 * Initialise event listeners for the mapping table.
 */
export const init = () => {
    const table = document.querySelector(SELECTORS.TABLE);
    if (!table) {
        return;
    }

    const courseid = parseInt(table.dataset.courseid);
    registerListeners(courseid);
};

/**
 * Open the mapping modal form.
 *
 * @param {number} courseid The course id
 * @param {number} mappingid The mapping id (0 for new mapping, >0 for editing an existing one)
 */
const openMappingForm = (courseid, mappingid) => {
    const title = mappingid
        ? getString('editmapping', 'local_groupmerge')
        : getString('addmapping', 'local_groupmerge');

    const modalForm = new ModalForm({
        formClass: FORM_CLASS,
        args: {courseid, mappingid},
        modalConfig: {title},
    });
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => window.location.reload());

    modalForm.show();
};

/**
 * Delete a mapping after user confirmation.
 *
 * @param {number} mappingid The mapping id to delete
 */
const deleteMappingWithConfirmation = async(mappingid) => {
    try {
        const confirmMessage = await getString('deletemapping_confirm', 'local_groupmerge');
        const confirmTitle = await getString('deletemapping', 'local_groupmerge');
        const deleteLabel = await getString('delete');

        await Notification.saveCancelPromise(confirmTitle, confirmMessage, deleteLabel);
        await Ajax.call([{
            methodname: 'local_groupmerge_delete_mapping',
            args: {mappingid},
        }])[0];
        window.location.reload();
    } catch (error) {
        // The saveCancelPromise rejects with an event when the user cancels the dialog.
        if (error?.type !== ModalEvents.cancel) {
            Notification.exception(error);
        }
    }
};

/**
 * Register all click listeners for the mapping table.
 *
 * @param {number} courseid The course id
 */
const registerListeners = (courseid) => {
    const addButton = document.querySelector(SELECTORS.ADD_BUTTON);
    if (addButton) {
        addButton.addEventListener('click', (e) => {
            e.preventDefault();
            openMappingForm(courseid, 0);
        });
    }

    document.querySelectorAll(SELECTORS.EDIT_BUTTON).forEach((editButton) => {
        editButton.addEventListener('click', (e) => {
            e.preventDefault();
            const mappingid = parseInt(editButton.dataset.local_groupmergeMappingid);
            openMappingForm(courseid, mappingid);
        });
    });

    document.querySelectorAll(SELECTORS.DELETE_BUTTON).forEach((deleteButton) => {
        deleteButton.addEventListener('click', (e) => {
            e.preventDefault();
            const mappingid = parseInt(deleteButton.dataset.local_groupmergeMappingid);
            deleteMappingWithConfirmation(mappingid);
        });
    });
};
