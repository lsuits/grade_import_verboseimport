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

require_once($CFG->libdir.'/gradelib.php');


/**
 * given an import code, commits all entries in buffer tables
 * (grade_import_value and grade_import_newitem)
 * Copy of grade/import/lib.php function for null grades. 
 * If this function is called, we assume that all data collected
 * up to this point is fine and we can go ahead and commit
 * @param int courseid - id of the course
 * @param string importcode - import batch identifier
 * @param feedback print feedback and continue button
 * @return bool success
 */
function grade_verbose_import_commit($courseid, $importcode, $importfeedback=true, $verbose=true, $nullignore=false) {
    global $CFG, $USER, $DB, $OUTPUT;

    $commitstart = time(); // start time in case we need to roll back
    $newitemids = array(); // array to hold new grade_item ids from grade_import_newitem table, mapping array
    // UCSB want counts of commited
    $newcnts=array(); $oldcnts=array();
    $prt_counts=0;

    /// first select distinct new grade_items with this batch
    $params = array($importcode, $USER->id);
    if ($newitems = $DB->get_records_sql("SELECT *
                                           FROM {grade_import_newitem}
                                          WHERE importcode = ? AND importer=?", $params)) {

        // instances of the new grade_items created, cached
        // in case grade_update fails, so that we can remove them
        $instances = array();
        $failed = false;
        foreach ($newitems as $newitem) {
            // get all grades with this item
            $newcnts[$newitem->itemname]=0;
            if ($grades = $DB->get_records('grade_import_values', array('newgradeitem' => $newitem->id, 'importcode' => $importcode, 'importer' => $USER->id))) {
                /// create a new grade item for this - must use false as second param!
                /// TODO: we need some bounds here too
                $gradeitem = new grade_item(array('courseid'=>$courseid, 'itemtype'=>'manual', 'itemname'=>$newitem->itemname), false);
                $gradeitem->insert('import');
                $instances[] = $gradeitem;

                // insert each individual grade to this new grade item
                foreach ($grades as $grade) {
                    $newcnts[$newitem->itemname]++;
                    if (!$gradeitem->update_final_grade($grade->userid, $grade->finalgrade, 'import', $grade->feedback, FORMAT_MOODLE)) {
                        $failed = true;
                        break 2;
                    }
                }
            }
        }

        if ($failed) {
            foreach ($instances as $instance) {
                $gradeitem->delete('import');
            }
            import_cleanup($importcode);
            return false;
        }
    }

    /// then find all existing items

    if ($gradeitems = $DB->get_records_sql("SELECT DISTINCT (itemid)
                                             FROM {grade_import_values}
                                            WHERE importcode = ? AND importer=? AND itemid > 0",
                                            array($importcode, $USER->id))) {

        $modifieditems = array();

        foreach ($gradeitems as $itemid=>$notused) {

            if (!$gradeitem = new grade_item(array('id'=>$itemid))) {
                // not supposed to happen, but just in case
                import_cleanup($importcode);
                return false;
            }
            $gitem = $DB->get_record('grade_items', array('id' => $itemid));
            // get all grades with this item
            if ($grades = $DB->get_records('grade_import_values', array('itemid' => $itemid,'importcode' => $importcode, 'importer' => $USER->id))) {

                // make the grades array for update_grade
                foreach ($grades as $grade) {
                    if (!$importfeedback) {
                        $grade->feedback = false; // ignore it
                    }
// TFW UCSB 14/02 - if nulls, prevent issue...
                    if ($nullignore and $grade->finalgrade === NULL) { $grade->finalgrade = false; }
                    if ($grade->feedback === NULL) { $grade->feedback = false; }

                    if (!$gradeitem->update_final_grade($grade->userid, $grade->finalgrade, 'import', $grade->feedback)) {
                        $failed = 1;
                        break 2;
                    }
                    $oldcnts[$gitem->itemname]++;
                }
                //$itemdetails -> idnumber = $gradeitem->idnumber;
                $modifieditems[] = $itemid;

            }

            if (!empty($failed)) {
                import_cleanup($importcode);
                return false;
            }
        }
    }

    if ($verbose) {
        // if($prt_counts){
        print str_replace('Array','New grades',print_r($newcnts,true))."<hr>";
        print str_replace('Array','Updates',print_r($oldcnts,true))."<hr>";
        // }
        echo $OUTPUT->notification(get_string('importsuccess', 'grades'), 'notifysuccess');
        $unenrolledusers = get_unenrolled_users_in_import($importcode, $courseid);
        if ($unenrolledusers) {
            $list = array();
            foreach ($unenrolledusers as $u) {
                $u->fullname = fullname($u);
                $list[] = get_string('usergrade', 'grades', $u);
            }
            echo $OUTPUT->notification(get_string('unenrolledusersinimport', 'grades', html_writer::alist($list)), 'notifysuccess');
        }
        echo $OUTPUT->continue_button($CFG->wwwroot.'/grade/index.php?id='.$courseid);
    }
    // clean up
    import_cleanup($importcode);

    return true;
}

