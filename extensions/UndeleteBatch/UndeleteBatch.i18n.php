<?php
/**
 * Internationalization file for the UndeleteBatch extension.
 *
 * @file
 * @ingroup Extensions
 */

$messages = array();

/** English
 * @author Nathan Larson
 */
$messages['en'] = array(
	'undeletebatch' => 'Undelete batch of pages',
	'undeletebatch-desc' => 'Allows to [[Special:UndeleteBatch|undelete a batch of pages]]',
	'undeletebatch-help' => 'Undelete a batch of pages.
You can either perform a single undelete, or undelete pages listed in a file.
Choose a user that will be shown in deletion logs.
Uploaded files should contain page name and optional reason, separated by a "|" character in each line.',
	'undeletebatch-caption' => 'Page list:',
	'undeletebatch-title' => 'Undelete batch',
	'undeletebatch-link-back' => 'Go back to the special page',
	'undeletebatch-as' => 'Run the script as:',
	'undeletebatch-both-modes' => 'Please choose either one specified page or a given list of pages.',
	'undeletebatch-or' => '<strong>or</strong>',
	'undeletebatch-delete' => 'Undelete',
	'undeletebatch-page' => 'Pages to be undeleted:',
	'undeletebatch-processing-from-file' => 'undeleting pages listed in the file',
	'undeletebatch-processing-from-form' => 'undeleting pages listed in the form',
	'undeletebatch-omitting-nonexistent' => 'Omitting non-existing page "$1".',
	'undeletebatch-omitting-invalid' => 'Omitting invalid page "$1".',
	'undeletebatch-file-bad-format' => 'The file should be plain text using charset UTF-8.',
	'undeletebatch-file-missing' => 'Unable to read given file.',
	'undeletebatch-select-script' => 'Undelete page script',
	'undeletebatch-select-yourself' => 'You',
	'undeletebatch-no-page' => 'Please specify at least one page to undelete or choose a file containing page list.',
	'right-deletebatch' => 'Batch undelete pages',
	'undeletebatch-undeleting-file-only' => 'File description page "$1" does not exist; undeleting the actual file only.
This action will not be logged.',
);

/** German (Deutsch)
 * @author Karsten Hoffmeyer (Kghbln)
 */
$messages['de'] = array(
	'undeletebatch' => 'Seiten gesammelt wiederherstellen',
	'undeletebatch-desc' => 'Ergänzt eine [[Special:UndeleteBatch|Spezialseite]], die das gesammelte Wiederherstellne gelöschter Seiten ermöglicht',
	'undeletebatch-help' => 'Seiten gesammelt Wiederherstellen.
Es können entweder in diesem Formular angegebene Seiten oder eine in einer Datei enthaltene Seitenliste wiederhergestellt werden.
Für die Aufzeichung im Löschlogbuch muß ausgewählt werden wer die Wiederherstellung ausgeführt hat.
Hochgeladene Dateien müssen zeilenweise den Seitennamen und optional den Wiederherstellungsgrund (durch einen senkrechten Strich „|“ vom jeweiligen Seitennamen getrennt) angeben.',
	'undeletebatch-caption' => 'Seitenliste:',
	'undeletebatch-title' => 'Gesammelt wiederherstellen',
	'undeletebatch-link-back' => 'Zurück zur Spezialseite (Formular)',
	'undeletebatch-as' => 'Die Wiederherstellung ausführen als:',
	'undeletebatch-both-modes' => 'Bitte eine Seite oder eine vorhandene Liste von Seiten auswählen.',
	'undeletebatch-or' => '<strong>oder</strong>',
	'undeletebatch-delete' => 'Wiederherstellen',
	'undeletebatch-page' => 'Wiederherzustellende Seiten:',
	'undeletebatch-processing-from-file' => 'Stelle die in der Datei aufgelisteten Seiten wieder her',
	'undeletebatch-processing-from-form' => 'Stelle die im Formular aufgelisteten Seiten wieder her',
	'undeletebatch-omitting-nonexistent' => 'Überspringe die nicht vorhandene Seite „$1“.',
	'undeletebatch-omitting-invalid' => 'Überspringe die die ungültige Seite „$1“.',
	'undeletebatch-file-bad-format' => 'Die Datei muß im Textformat und mit Zeichensatz UTF-8 erstellt werden.',
	'undeletebatch-file-missing' => 'Die Datei konnte nicht eingelesen werden.',
	'undeletebatch-select-script' => 'Wiederherstellungsskript',
	'undeletebatch-select-yourself' => 'Dein Benutzername',
	'undeletebatch-no-page' => 'Es muss mindestens eine Seite zum Wiederherstellen angegeben oder eine Datei mit Seitennamen ausgewählt werden.',
	'right-deletebatch' => 'Gesammelte Wiederstellung von Seiten',
	'undeletebatch-undeleting-file-only' => 'Die Dateibeschreibungsseite „$1“ ist nicht vorhanden. Es wird nur die tatsächlich vorhandene Datei gelöscht.
Diese Aktion wird nicht im Logbuch aufgezeichnet.',
);

/** German (formal address) (Deutsch (Sie-Form)‎)
 * @author Karsten Hoffmeyer (Kghbln)
 */
$messages['de-formal'] = array(
	'undeletebatch-select-yourself' => 'Ihr Benutzername'
);
