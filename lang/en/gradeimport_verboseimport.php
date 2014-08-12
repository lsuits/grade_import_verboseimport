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
$string['pastebutton']='Change to Import File';
$string['filebutton']='Change to Copy and Paste from Excel';
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
$string['csvrptnserts']='Potential inserts';
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
