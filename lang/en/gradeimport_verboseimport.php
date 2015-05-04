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
 * Strings for component 'gradeimport_verboseimport', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   gradeimport_verboseimport
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['verboseimport:view'] = 'Import grades from CSV';
$string['pluginname'] = 'Verbose Import';
$string['importdatafromcopypaste']='Import Data from Copy/Paste';
$string['pastebutton']='Switch to Import File';
$string['filebutton']='Switch to Paste Data';
$string['csvstudenterr']='student {$a->name} not found in system mapped to {$a->key}';
$string['csvstudentgrouperr']='student {$a->name} not found in group {$a->key}';
$string['csvscoreerr']='invalid grade for {$a}';
$string['csvnoscoreerr']='missing grade for {$a}';
$string['csvscaleerr']='invalid scale value for {$a}';
$string['csvgradeerr']='invalid grade value for {$a}';
$string['csvovermax']='grade exceeds item max ({$a->name}) for {$a->key}';
$string['csvdupgrade']='duplicate grade for {$a->name} from {$a->key} to {$a->key2}';
$string['csvchggrade']='changing grade for {$a->name} from {$a->key} to {$a->key2}';
$string['csvaddgrade']='adding grade for {$a->name} as {$a->key}';
$string['csvrptstudenterr']=' - Number of students that do not exist in System: ';
$string['csvrptgrperr']=' - Number of student score not in this group: ';
$string['csvrptscorebad']=' - Number of scores that have invalid data in input file: ';
$string['csvrptlocked']=' - Number of scores that are locked: ';
$string['csvrptovermax']=' - Number of grade scores over the max grade item: ';
$string['csvrptduplicate']='  - Number of scores that will not update as the grade is identical: ';
$string['csvrptnullvals']='  - Number of scores that have a null value in input file: ';
$string['csvrptsummary']='Import File Summary';
$string['csvrptdetail']='Detail';
$string['csvrptupdates']='Potential updates';
$string['csvrptinserts']='Potential inserts';
$string['csvrptoverwarn']='Warning: Accepted grade exceeds Item Maximum';
$string['csvrptovererror']='Error: Grade exceeds Item Maximum';
$string['csvrptlocked']='Error: Grade score bypassed - Locked grade';
$string['csvrptnograde']='Error: Grade score bypassed - No grade';
$string['csvrptbadgrade']='Error: Grade score bypassed - Invalid grade';
$string['csvrptbadgroup']='Error: Grade score bypassed - Invalid group';
$string['csvrptbadstudent']='Error: Grade score bypassed - Invalid student';
$string['csvrptdupescore']='Warning: Grade score bypassed - Duplicate score skipped';

$string['csvoriginal']='ORIGINAL ';
$string['csvfixed']='FIXED ';
$string['csvtoofewcols']='too few columns';
$string['csvtoomanycols']='too many columns';
$string['csvnullswill']='NULLs will ';
$string['csvnullsignored']='be ignored';
$string['csvnullsokay']='overwrite';

$string['csvbtnupdate']='Update';
$string['csvbtnreturn']='Return to menu';
$string['csvbtnshowdetail']='Show Detail';
$string['csvbtnmoredetail']='Show more detail';

$string['importhelp'] = 'Import Help';
$string['importhelp_help'] = 'Changing to "Switch to Import File" will allow you to import a file similar to the way it is done in the CSV file tab. The difference is that this option will do partial updates for those grades that are correct and give error messages and not upgrade those grades that are not acceptable. The CSV file tab will only accept imports where scores are all acceptable.<br/>Changing the button to "Switch to Paste Data" will bring this back to the original setting of copying and pasting data into the data tab.';
$string['mapfrom'] = 'Map from';
$string['mapfrom_help'] = 'Select the column from the provided spreadsheet that contains information to distinguish the grades pertaining to one user from those of another user.';
$string['mappings'] = 'Grade item mappings';
$string['mappings_help'] = 'Each column in the provided spreadsheet is represented below as a string of text with a select box next to it. For the column(s) that you would like to import into the gradebook, select the column in the gradebook that you would like to override. In example, if you have a column in your spreadsheet called Project #1 with the grades that you would like to be placed under the first assignment in the gradebook, select that assignment in the dropdown next to "Project #1".';
$string['mapto'] = 'Map to';
$string['mapto_help'] = 'Select the user information contained in Moodle to match the identifying information selected in "Map to:". If you selected a column that contains the user\'s full name, select "Username", if you selected a column that contains the user\'s email address, select "Email address", and so on.';
$string['separator'] = 'Separator';
$string['separator_help'] = 'If you are importing a .csv file, select “Comma”. If you are importing any other file, make sure that the data is separated by a tab, a comma, a colon, or a semicolon and select that option.';
$string['rowpreviewnum'] = 'Preview rows';
$string['rowpreviewnum_help'] = 'Sets the number of rows that are available during the preview step of the pasted data. The preview step allows the user to ensure that the data was interpreted correctly.';
$string['verbosescales'] = 'Verbose scales';
$string['verbosescales_help'] = 'Verbose scales is for importing grades that have non-numeric grading scales (Pass/Fail, Satisfactory, etc.). Having it on will not affect numeric scores. Turning this setting off will prevent any scores from being updated that do not have numeric values, even if the grading type is a non-numeric scale.';
