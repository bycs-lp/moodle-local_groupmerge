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
 * German lang strings for local_groupmerge.
 *
 * @package   local_groupmerge
 * @copyright 2026 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['addmapping'] = 'Gruppenverknüpfung hinzufügen';
$string['deletemapping'] = 'Gruppenverknüpfung löschen';
$string['deletemapping_confirm'] = 'Möchten Sie diese Gruppenverknüpfung wirklich löschen? Mitglieder werden nicht mehr synchronisiert.';
$string['editgroupsettings'] = 'Gruppe „{$a}" – klicken, um Gruppeneinstellungen zu bearbeiten';
$string['editmapping'] = 'Gruppenverknüpfung bearbeiten';
$string['error_circular_mapping'] = 'Diese Zuordnung würde eine zirkuläre Abhängigkeit erzeugen (die Zielgruppe ist transitiv eine Quellgruppe einer der ausgewählten Quellgruppen).';
$string['error_target_unallowed'] = 'Diese Gruppe kann nicht als Zielgruppe verwendet werden: {$a}';
$string['error_targetalreadymapped'] = 'Diese Gruppe hat bereits eine Zuordnung. Bitte bearbeiten Sie die bestehende Zuordnung.';
$string['error_targetinsource'] = 'Die Zielgruppe darf nicht gleichzeitig eine der Quellgruppen sein.';
$string['error_targetnotavailable'] = 'Diese Gruppe ist nicht als Zielgruppe verfügbar. Sie wird möglicherweise bereits als Zielgruppe verwendet oder steht aus anderen Gründen nicht zur Verfügung.';
$string['groupmerge:manage'] = 'Gruppenverknüpfungen verwalten';
$string['managegroups'] = 'Gruppen verwalten';
$string['managemembers'] = '{$a} Gruppenmitglieder – klicken, um Mitglieder hinzuzufügen oder zu entfernen';
$string['mappingname'] = 'Name';
$string['mappingname_help'] = 'Ein optionaler Name für diese Gruppenverknüpfung. Verwenden Sie ihn, um den Zweck zu beschreiben, z. B. „Alle Tutoren".';
$string['mappings_removed_by_restriction'] = 'Die folgenden Gruppenverknüpfungen wurden automatisch entfernt, da ihre Zielgruppe nicht mehr verfügbar ist: {$a}';
$string['mappings_title'] = 'Gruppenverknüpfungen verwalten';
$string['mappingtype'] = 'Typ';
$string['mappingtype_help'] = 'Wählen Sie den Verknüpfungstyp. „Abdeckung" bedeutet, dass die Zielgruppe exakt die Mitglieder aller Quellgruppen enthält (zusätzliche Mitglieder werden entfernt). „Teilmenge" bedeutet, dass Quellgruppen-Mitglieder zur Zielgruppe hinzugefügt werden, vorhandene zusätzliche Mitglieder aber erhalten bleiben.';
$string['member_readded'] = 'Das Mitglied wurde automatisch wieder zur Gruppe „{$a->groupname}" hinzugefügt, da eine Gruppenverknüpfung dies erfordert. Falls Sie das nicht möchten, <a href="{$a->configurl}">entfernen Sie bitte die entsprechende Gruppenverknüpfung</a>.';
$string['nomappings'] = 'Noch keine Gruppenverknüpfungen definiert.';
$string['notenoughgroups'] = '„Gruppenverknüpfungen" benötigt mindestens 2 Gruppen in diesem Kurs. Bitte erstellen Sie zuerst weitere Gruppen.';
$string['plugin_desc'] = 'Dieses Plugin ermöglicht es, Gruppen miteinander zu verknüpfen, sodass Mitglieder automatisch synchronisiert werden. Wenn Sie eine Gruppenverknüpfung erstellen, werden alle Mitglieder der ausgewählten Quellgruppen zur Zielgruppe hinzugefügt. Wird später jemand zu einer Quellgruppe hinzugefügt oder daraus entfernt, wird die Zielgruppe automatisch aktualisiert.';
$string['pluginname'] = 'Gruppen kombinieren';
$string['plugintitle'] = 'Gruppen kombinieren';
$string['privacy:metadata'] = 'Dieses Plugin speichert keine personenbezogenen Daten';
$string['resolvedmappings_desc'] = 'Wenn Gruppenverknüpfungen verkettet sind (z. B. Gruppe A speist in Gruppe B, und Gruppe B speist in Gruppe C), zeigt diese Tabelle das vollständige Bild: Für jede Zielgruppe werden alle Gruppen aufgelistet, deren Mitglieder tatsächlich in dieser Zielgruppe landen – auch indirekte.';
$string['resolvedmappingstitle'] = 'Übersicht: Woher kommen die Mitglieder?';
$string['sourcegroupids'] = 'Quellgruppen';
$string['sourcegroupids_help'] = 'Wählen Sie die Quellgruppen. Alle Teilnehmer/innen dieser Gruppen werden auch der ausgewählten Zielgruppe zugeordnet. Wenn Teilnehmer/innen zu den Quellgruppen hinzugefügt oder daraus entfernt werden, werden sie auch in der Zielgruppe hinzugefügt oder entfernt.';
$string['sourcegroups'] = 'Quellgruppen';
$string['targetgroup'] = 'Zielgruppe';
$string['targetgroupid'] = 'Zielgruppe';
$string['targetgroupid_help'] = 'Wählen Sie die Gruppe, zu der alle Teilnehmer/innen der ausgewählten Quellgruppen hinzugefügt werden sollen. Wenn Teilnehmer/innen zu den Quellgruppen hinzugefügt oder daraus entfernt werden, werden sie auch in der Zielgruppe hinzugefügt oder entfernt.';
$string['type_cover'] = 'Vollständig';
$string['type_subset'] = 'Teilmenge';
$string['unallowed_targetgroups'] = 'Nicht verfügbare Zielgruppen';
