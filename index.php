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

require_once("../../../config.php");
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->dirroot. '/grade/import/verboseimport/grade_import_form.php');
require_once($CFG->dirroot.'/grade/import/lib.php');
require_once($CFG->libdir . '/csvlib.class.php');

           
$id            = required_param('id', PARAM_INT); // course id
$separator     = optional_param('separator', '', PARAM_ALPHA);
$verbosescales = optional_param('verbosescales', 1, PARAM_BOOL);
$iid           = optional_param('iid', null, PARAM_INT);
$importcode    = optional_param('importcode', '', PARAM_FILE);

$paste         = optional_param('paste', 1, PARAM_BOOL);
$cancelupd     = optional_param('cancelupd', 0, PARAM_BOOL);
$cancelmap     = optional_param('cancel', 0, PARAM_BOOL);
$showdetail    = optional_param('showdetail', 0, PARAM_INT);
$indexphp = basename($_SERVER["SCRIPT_FILENAME"]);
$url = new moodle_url('/grade/import/verboseimport/'.$indexphp, array('id'=>$id)); // index.php

$CSVsettings = new stdClass();
/* switches to control the preview */

// Do not just update, ask if detail wanted first, otherwise 0 mimics old CSV. 
$CSVsettings->csvimportauditcsv = 1;

// Fix uneven columns running patch function.
$CSVsettings->csvimportcsvpatch = 1; 

// These control record bypass in csv import... moodle default was to abort, we just skip.
// ? is student valid in site.
$CSVsettings->csvimportskipnonuser = 1;
// ? is there junk in the data, non-scale or non-numeric.
$CSVsettings->csvimportskipbaddata = 1;
// ? if no grade, do we zap an existing grade or skip.
$CSVsettings->csvimportskipnullgrade = 1;
// ? abort if any permissions to group won't allow update or just skip.
$CSVsettings->csvimportskipnongroup = 1;
// ? abort if any student grade is locked.
$CSVsettings->csvimportskiplockedgrade = 1;

// warn/remind... depends on CFG->unlimitedgrades whether error or warn.
$CSVsettings->csvimportwarngradeovermax = 1;

// want to update/insert audit detail with import?
$CSVsettings->csvimportauditchangegrade = 1;
$CSVsettings->csvimportauditnewgrade = 1;

// skip grade update matching existing grade.
$CSVsettings->csvimportskipsamegrade = 1;
 
// prevent updates always, for dryrun mode.
$CSVsettings->csvimportpreviewonly = 0;

/* ==== */

$forcecsv=0; // do not patch uneven csv files with even columns (titled)
if (array_key_exists('csvimportauditcsv',$CSVsettings) and $CSVsettings->csvimportauditcsv) {
    $doupdate      = optional_param('doupdate', 0, PARAM_BOOL);
    if (!empty($CSVsettings->csvimportcsvpatch)) {
        $forcecsv=1;
    }
    if (!empty($CSVsettings->csvimportpreviewonly)) { // and no updates
        $noupdates=true;
    } else {
        $noupdates=false;
    }
} else {
    // might as well clear everything... act as old CSV import
    $CSVsettings=array();
    $doupdate      = optional_param('doupdate', 1, PARAM_BOOL);
}
if (array_key_exists('csvimportskipnullgrade',$CSVsettings) and $CSVsettings->csvimportskipnullgrade) {
  $nulldefault = $CSVsettings->csvimportskipnullgrade;
} else {
  $nulldefault = 0;
}

if ($separator !== '') {
    $url->param('separator', $separator);
}
if ($verbosescales !== 1) {
    $url->param('verbosescales', $verbosescales);
}
if ($paste !== 0) {
    $url->param('paste', $paste);
}
if ($cancelmap) $cancelupd = $cancelmap;

$nullignore    = optional_param('nullignore', $nulldefault, PARAM_BOOL);
if ($nullignore !== 0) {
    $url->param('nullignore', $nullignore);
}

$PAGE->set_url($url);

if (!$course = $DB->get_record('course', array('id'=>$id))) {
    print_error('nocourseid');
}

require_login($course);
$context = context_course::instance($id);
require_capability('moodle/grade:import', $context);
require_capability('gradeimport/verboseimport:view', $context);

$separatemode = (groups_get_course_groupmode($COURSE) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context));
$currentgroup = groups_get_course_group($course);

print_grade_page_head($course->id, 'import', 'verboseimport', get_string('importcsv', 'grades').' with Preview');

// Determine which state we are in for log...
$logentry=(empty($separator)) ? 'select': 'map';
$logentry=(empty($iid)) ? $logentry : 'preview';
$logentry=(empty($doupdate)) ? $logentry : 'process';
vi_add_to_log($course->id,'csvpreview','view','',$logentry);

// Set up the grade import mapping form.
$gradeitems = array();

// Invalid lines/grades in the file, warnings, audit
$skipped_lines = array(); // empty
$bad_grades = array();
$locked_grades = array();
$notin_group = array();
$notin_site = array();
$same_grades = array();
$ovrmax_grades = array(); // warning
$update_grades= array(); // audit
$new_grades = array();
$lcnt_in = $lcnt_ok = $lcnt_mt = 0; 
$icnt_fb = $icnt_upd = $icnt_new = 0;
$scnt_all = $scnt_new = $scnt_upd = 0;
$gcnt_ok = 0;
$ecnts = new stdClass();
$ecnts->ecnt_null = 0 ; $ecnts->ecnt_lok = 0; $ecnts->ecnt_bad = 0;
$ecnts->ecnt_ngrp = 0 ; $ecnts->ecnt_nouser = 0; $ecnts->ecnt_noupd = 0;
$ecnts->ecnt_overmax = 0; // prevented by system setting
$wcnt_overmax = 0; // allowed by system setting
$colerr=0;
$rebuild=0;
if ($iid or $showdetail) $rebuild=1;

/**
 * bbb_add_to_log hack for using legacy add to log without debug screaming at us
 */
function vi_add_to_log($courseid, $module, $action, $url='', $info='', $cm=0, $user=0) {
    if (function_exists('get_log_manager')) {
        $manager = get_log_manager();
        $manager->legacy_add_to_log($courseid, $module, $action, $url, $info, $cm, $user);
    } else if (function_exists('add_to_log')) {
        add_to_log($courseid, $module, $action, $url, $info, $cm, $user);
    }
}

function outputSection($SecName, $Content,$output=null)
{
    global $OUTPUT;
    
    $grnspan='<span style="color: green;">';
    $redspan='<span style="color: red;">';
    $orgspan='<span style="color: #AA6600;">';
    if(strpos($SecName,'Error') !== false){
        $secnam=$redspan.$SecName.'</span>';
        $hlevel=2;
    } elseif(strpos($SecName,'Warning') !== false){
        $secnam=$orgspan.$SecName.'</span>';
        $hlevel=3;
    } else {
        $secnam=$grnspan.$SecName.'</span>';
    }
    
    if ($output=='boolean') {
        print $Content;
    } elseif ($output == 'excel') {
        print $SecName;
        print nl2br($Content);
    } else {
        echo $OUTPUT->container_start('block');
        echo $OUTPUT->container_start('header');
        echo $OUTPUT->container_start('title');
        echo $OUTPUT->heading($secnam, 2); // <h2>
        echo $OUTPUT->container_end();
        echo $OUTPUT->container_end();
        echo $OUTPUT->container_start('content');
        echo $Content;
        echo $OUTPUT->container_end();
        echo $OUTPUT->container_end();
    }
} // end function outputSection

function patch_csv($text,$separator) {
    global $OUTPUT, $lcnt_mt, $pcnt, $colerr;
//  - ensure common eol
    $text = preg_replace("/\r/", "\n", preg_replace("/\r\n/", "\n", $text));
//  - and blanks; not needed, csvimport ignores these
//        $yext = preg_replace("/\n[,]*\n/", "\n", $text);
//        $rcnt = count(explode("\n",$yext));
//        $discard=$tcnt - $rcnt; 
    $sep = csv_import_reader::get_delimiter($separator);
    $newchr='~^~';
    $text = explode("\n",$text);
    $tcnt = count($text); 
    $rcnt=0; $ccnt=0;
    $tdta=array();
    $msg='';
    foreach($text as $line) {
        $j=0;
        for($i=0;$i<strlen($line);$i++) {
            if ($line[$i]=='"')$j++; 
        }
        if ($j % 2) { print "uneven quotes - rejected line $line<br>"; continue; }
        $x = array();
        $array = str_getcsv($line,$sep);
        foreach($array as $item) {
            $x[] = str_replace($sep, $newchr, $item);
        }
        $x=implode($sep,$x);
        $cols=count(explode($sep,$x)); 
        $hdrmsg =<<< EOX
We detected that some of your records didn't have the same number of data elements as your header row indicated ($ccnt).  We are listing the ones we've fixed, but you might chose to fix this in your spreadsheet and try again.  
EOX;
        if ($rcnt==0) { // header? just save col count
            $ccnt=$cols; 
        } else { 
            if ($cols==1) { // print "empty<br>"; 
                $lcnt_mt++; continue; 
            }
            if ($cols < $ccnt) {
                $colerr++; 
                if($colerr==1)print $OUTPUT->notification($hdrmsg,array());
                print "&nbsp; " . get_string('csvoriginal','gradeimport_verboseimport')
                    .get_string('csvtoofewcols','gradeimport_verboseimport') . " (&lt;$ccnt) -<br>&nbsp; $line<br>";
                $line=str_replace($newchr,$sep,$x).str_repeat($sep,($ccnt-$cols));
                print "&nbsp;" . get_string('csvfixed','gradeimport_verboseimport') . " -<br>&nbsp; $line<br>";
            }
            if ($cols > $ccnt) {
                $colerr++; 
                if($colerr==1)echo $OUTPUT->notification($hdrmsg,array());
                print "&nbsp; " . get_string('csvoriginal','gradeimport_verboseimport')
                    .get_string('csvtoomanycols','gradeimport_verboseimport') . " (&lt;$ccnt) -<br>&nbsp; $line<br>";
                $rcol = ($cols - $ccnt);
                $rchr=sprintf("%c",0x0c);
                $line=strrev(preg_replace("/$sep/",$rchr,strrev($x),$rcol));

                $line=explode($rchr,$line); $line=$line[0];
                $line=str_replace($newchr,$sep,$line);
                print "&nbsp; " . get_string('csvfixed','gradeimport_verboseimport') . " -<br>&nbsp; $line<br>";
//              continue;
            }
//            print "$cols <> $ccnt : $colerr<hr>";
        }
        $rcnt++;
        $tdta[]=$line;
    }    
    $pcnt = count($text);
    $text=implode("\n",$tdta);
    return $text;
} // end function patch_csv 

if ($cancelupd) {
    import_cleanup($importcode);
    redirect($url);
    die();
}

if ($id) {
    if ($grade_items = grade_item::fetch_all(array('courseid'=>$id))) {
        foreach ($grade_items as $grade_item) {
            // Skip course type and category type.
            if ($grade_item->itemtype == 'course' || $grade_item->itemtype == 'category') {
                continue;
            }

            $displaystring = null;
            if (!empty($grade_item->itemmodule)) {
                $displaystring = get_string('modulename', $grade_item->itemmodule).get_string('labelsep', 'langconfig')
                        .$grade_item->get_name();
            } else {
                $displaystring = $grade_item->get_name();
            }
            $gradeitems[$grade_item->id] = $displaystring;
        }
    }
}

// Set up the import form.
// The 'includeseparator' gives an opportunity to select a different one...
$mform = new grade_import_form('?id=' . $id, array('includeseparator'=>true, 'verbosescales'=>true, 'paste' => $paste));
// $mform->set_data(array('paste' => $paste, 'action'=>'?id='.$id ));

// If the csv file hasn't been imported yet then look for a form submission or
// show the initial submission form.
if (!$iid) {
    // If the import form has been submitted.
    if ($formdata = $mform->get_data()) {

        // Large files are likely to take their time and memory. Let PHP know
        // that we'll take longer, and that the process should be recycled soon
        // to free up memory.
        @set_time_limit(0);
        raise_memory_limit(MEMORY_EXTRA);
        $pcnt=0;

        // Use current (non-conflicting) time stamp.
        $importcode = get_new_importcode();

        if($paste) {
            $text=$formdata->userdata;
        } else {  
            $text = $mform->get_file_content('userfile');
        }    

        if($forcecsv)$text = patch_csv($text,$separator);
        $iid = csv_import_reader::get_new_iid('grade');
        $csvimport = new csv_import_reader($iid, 'grade');

        $tcnt=$csvimport->load_csv_content($text, $formdata->encoding, $separator);
// TODD - do we want to abort or skip line
        if (!$tcnt)print $csvimport->get_error(); // exit?

        // --- get header (field names) ---
        $header = $csvimport->get_columns();

        $cols = count($header); 
        // Print a preview of the data.
        $numlines = 0; // 0 lines previewed so far.

        echo $OUTPUT->heading(get_string('importpreview', 'grades'));
        $hdrmsg = <<< EOX
We detected $cols data elements in your first row of your input file, and $colerr lines did not have $cols data elements and might import invalid data. 
EOX;
        if ($cols == 1 or !$tcnt) { 
            // print_error("<strong>Likely you have selected the wrong delim>iter</strong>"); }
            if ($cols == 1)print("<strong>Likely you have selected the wrong delimiter</strong>");
            echo $OUTPUT->single_button($url, 'GoBack');
            return;
        }
        if($colerr)echo $OUTPUT->notification($hdrmsg,array());
        foreach ($header as $i => $h) {
            $h = trim($h); // Remove whitespace.
            $h = clean_param($h, PARAM_RAW); // Clean the header.
            $header[$i] = $h;
        }

        $table = new html_table();
        $table->head = $header;
        $csvimport->init();
        $previewdata = array();
        while ($numlines <= $formdata->previewrows) {
            $lines = $csvimport->next();
            if ($lines) {
                $previewdata[] = $lines;
            }
            $numlines ++;
        }
        $table->data = $previewdata;
        echo html_writer::table($table);
    } else {
        // Display the standard upload file form.
        groups_print_course_menu($course, $indexphp.'?id='.$id);  // index.php
        echo html_writer::start_tag('div', array('class' => 'clearer'));
        echo html_writer::end_tag('div');

        $optionp=$_REQUEST;
        $optionp['paste'] = !$paste;
        echo $OUTPUT->single_button(new moodle_url($indexphp.'?id='.$id, $optionp),
           ($paste ?  get_string('pastebutton', 'gradeimport_verboseimport') : get_string('filebutton', 'gradeimport_verboseimport')));
        $mform->display();
        echo $OUTPUT->footer();
        die();
    }
}

// Data has already been submitted so we can use the $iid to retrieve it.
$csvimport = new csv_import_reader($iid, 'grade');
$header = $csvimport->get_columns();

// we create a form to handle mapping data from the file to the database.
$mform2 = new grade_import_mapping_form(null, array('gradeitems'=>$gradeitems, 'header'=>$header, 'zerotoupdate' => '1'.$nullignore));
$mform2->set_data(array('iid' => $iid, 'id' => $id, 'importcode'=>$importcode, 'verbosescales' => $verbosescales));

// Here, if we have data, we process the fields and enter the information into the database.
if ($formdata = $mform2->get_data() or $rebuild) {
    $options=$_REQUEST;
// print_r($options);print "_request<br>";

//    if ($rebuild)$formdata=$_REQUEST;  // big cheat here if array worked as standard object
//    print_r($formdata); print " formdata<br>";
    foreach ($header as $i => $h) {
        $h = trim($h); // Remove whitespace.
        $h = clean_param($h, PARAM_RAW); // Clean the header.
        $header[$i] = $h;
    }

    $map = array();
    // loops mapping_0, mapping_1 .. mapping_n and construct $map array
    foreach ($header as $i => $head) {
        if ($rebuild) {
            if (array_key_exists('mapping_'.$i,$_REQUEST)) { //  and $_REQUEST['mapping_'.$i] != 0) {
                $map[$i] = clean_param($_REQUEST['mapping_'.$i],PARAM_RAW);
            }
        } else{
            if (isset($formdata->{'mapping_'.$i})) {
                $map[$i] = clean_param($formdata->{'mapping_'.$i},PARAM_RAW);
            }
        }
    }
 //print_r($header); print" header<br>";
 //print_r($map); print" map<br>";

    // if mapping information is supplied
    if ($rebuild) {
        $map[$_REQUEST['mapfrom']]=clean_param($_REQUEST['mapto'],PARAM_RAW);
    } else {
        $map[clean_param($formdata->mapfrom, PARAM_RAW)] = clean_param($formdata->mapto, PARAM_RAW);
    }
// print_r($map); print" postmap<br>";
    // check for mapto collisions
    $maperrors = array();
    foreach ($map as $i => $j) {
        if ($j == 0) {
            // you can have multiple ignores
            continue;
        } else {
            if (!isset($maperrors[$j])) {
                $maperrors[$j] = true;
            } else {
                // collision
                print_error('cannotmapfield', '', '', $j);
            }
        }
    }

    // Large files are likely to take their time and memory. Let PHP know
    // that we'll take longer, and that the process should be recycled soon
    // to free up memory.
    @set_time_limit(0);
    raise_memory_limit(MEMORY_EXTRA);

    $csvimport->init();

    $newgradeitems = array(); // temporary array to keep track of what new headers are processed
    $status = true;
    $header = $csvimport->get_columns();
    $lines_hdr = $header; $lines_hdr[]='msg';
    $cols = count($header);
    while ($line = $csvimport->next()) {
        $lcnt_in++;
        if (count($line) <= 1) {
            // there is no data on this line, move on
            $lcnt_mt++;
            continue;
        }
        // array to hold all grades to be inserted
        $newgrades = array();
        // array to hold all feedback
        $newfeedbacks = array();
        // each line is a student record
        $studentname='';
// var_dump($line); print "<br>";
        foreach ($line as $key => $value) {

            $value = clean_param($value, PARAM_RAW);
            $value = trim($value);

            /*
             * the options are
             * 1) userid, useridnumber, usermail, username - used to identify user row
             * 2) new - new grade item
             * 3) id - id of the old grade item to map onto
             * 3) feedback_id - feedback for grade item id
             */
// print "<br>map key: $key = ".$map[$key]." = $value<br>";

            $t = explode("_", $map[$key]);
            $t0 = $t[0];
            if (isset($t[1])) {
                $t1 = (int)$t[1];
            } else {
                $t1 = '';
            }

            switch ($t0) {
                case 'userid': //
                    if (!$user = $DB->get_record('user', array('id' => $value))) {
                        // user not found, abort whole import
                        if (array_key_exists('csvimportskipnonuser',$CSVsettings) and $CSVsettings->csvimportskipnonuser) {
                            $a = new stdClass();
                            $a->name  = $value;
                            $a->key = $header[$key];
                            $line['msg']=get_string('csvstudenterr','gradeimport_verboseimport',$a);  // value,$header[$key]);
                            $notin_site[] = $line; $ecnts->ecnt_nouser++;
                            continue 3;  // next line
                        } else {
                            import_cleanup($importcode);
                            echo $OUTPUT->notification("user mapping error, could not find user with id \"$value\"");
                            $status = false;
                            break 3;
                        }  
                    }
                    $studentid = $value;
                    $studentname=$user->username;
                break;
                case 'useridnumber':
                    // hey, i'm getting "found more than 1" - think for a blank 'value'
                    if (trim($value) == '' or !$user = $DB->get_record('user', array('idnumber' => $value))) {
                        // user not found, abort whole import
                        if (array_key_exists('csvimportskipnonuser',$CSVsettings) and $CSVsettings->csvimportskipnonuser) {
                            $a = new stdClass();
                            $a->name  = $value;
                            $a->key = $header[$key];
                            $line['msg']=get_string('csvstudenterr','gradeimport_verboseimport',$a);  // value,$header[$key]);
                            $notin_site[] = $line; $ecnts->ecnt_nouser++;
                            continue 3;
                        } else {
                            import_cleanup($importcode);
                            echo $OUTPUT->notification("user mapping error, could not find user with idnumber \"$value\"");
                            $status = false;
                            break 3;
                        }  
                    }
                    $studentid = $user->id;
                    $studentname=$user->username;
                break;
                case 'useremail':
                    if (!$user = $DB->get_record('user', array('email' => $value))) {
                        if (array_key_exists('csvimportskipnonuser',$CSVsettings) and $CSVsettings->csvimportskipnonuser) {
                            $a = new stdClass();
                            $a->name  = $value;
                            $a->key = $header[$key];
                            $line['msg']=get_string('csvstudenterr','gradeimport_verboseimport',$a);  // value,$header[$key]);
                            $notin_site[] = $line; $ecnts->ecnt_nouser++;
                            continue 3;
                        } else {
                            import_cleanup($importcode);
                            echo $OUTPUT->notification("user mapping error, could not find user with email address \"$value\"");
                            $status = false;
                            break 3;
                        }  
                    }
                    $studentid = $user->id;
                    $studentname=$user->username;
                break;
                case 'username':
                    if (!$user = $DB->get_record('user', array('username' => $value))) {
                        if (array_key_exists('csvimportskipnonuser',$CSVsettings) and $CSVsettings->csvimportskipnonuser) {
                            $a = new stdClass();
                            $a->name  = $value;
                            $a->key = $header[$key];
                            $line['msg']=get_string('csvstudenterr','gradeimport_verboseimport',$a);  // value,$header[$key]);
                            $notin_site[] = $line; $ecnts->ecnt_nouser++;
                            continue 3;
                        } else {
                            import_cleanup($importcode);
                            echo $OUTPUT->notification("user mapping error, could not find user with username \"$value\"");
                            $status = false;
                            break 3;
                        }  
                    }
                    $studentid = $user->id;
                    $studentname=$user->username;
                break;
                case 'new':
                    // first check if header is already in temp database

                    $scnt_all++; $scnt_new++; 
                    if (empty($newgradeitems[$key])) {

                        $newgradeitem = new stdClass();
                        $newgradeitem->itemname = $header[$key];
                        $newgradeitem->importcode = $importcode;
                        $newgradeitem->importer   = $USER->id;

                        // insert into new grade item buffer
                        $newgradeitems[$key] = $DB->insert_record('grade_import_newitem', $newgradeitem);
                    }
                    $newgrade = new stdClass();
                    $newgrade->newgradeitem = $newgradeitems[$key];

                    // if the user has a grade for this grade item
                    if (trim($value) == '-') {
                    } else {
                        // If the value has a local decimal or can correctly be unformatted, do it.
                        $validvalue = unformat_float($value, true);
// print "col: $key item:$t0 val: $value dec: $validvalue<hr>";
// print ($validvalue !== false) ? "okay<hr>":"false<hr>"; 
                        if ($validvalue !== false) {
                            $value = $validvalue;
                        } else {
                            if (array_key_exists('csvimportskipbaddata',$CSVsettings) and $CSVsettings->csvimportskipbaddata) {
                                // $line['msg']="invalid grade for $header[$key] "; // in column $key";
                                $line['msg']=get_string('csvscoreerr','gradeimport_verboseimport',$header[$key]);
                                $bad_grades[] = $line; $ecnts->ecnt_bad++;
                                continue;
                            } else {
                                // Non numeric grade value supplied, possibly mapped wrong column.
                                echo "<br/>t0 is $t0";
                                echo "<br/>grade is $value";
                                $status = false;
                                import_cleanup($importcode);
                                echo $OUTPUT->notification(get_string('badgrade', 'grades'));
                                break 3;
                            }
                        } 
                        $newgrade->finalgrade   = $value;
                        if (array_key_exists('csvimportskipnullgrade',$CSVsettings) and $CSVsettings->csvimportskipnullgrade) {
                            if ($newgrade->finalgrade === null // drop nulls?
                            or ($nullignore && $newgrade->finalgrade == null)) { // or 0.0s?
                                // $line['msg']="missing grade for $header[$key] "; // in column $key";
                                $line['msg']=get_string('csvnoscoreerr','gradeimport_verboseimport',$header[$key]);
                                $skipped_lines[] = $line; $ecnts->ecnt_null++;
                                continue;
                            }
                        }
                        $newgrade->studentname  = $studentname;
                        $newgrades[] = $newgrade;
                    }
                break;
                case 'feedback':
                    if ($t1) {
                        // case of an id, only maps id of a grade_item
                        // this was idnumber
                        if (!$gradeitem = new grade_item(array('id'=>$t1, 'courseid'=>$course->id))) {
                            // supplied bad mapping, should not be possible since user
                            // had to pick mapping
                            $status = false;
                            import_cleanup($importcode);
                            echo $OUTPUT->notification(get_string('importfailed', 'grades'));
                            break 3;
                        }

                        // t1 is the id of the grade item
                        $feedback = new stdClass();
                        $feedback->itemid   = $t1;
                        $feedback->feedback = $value;
                        $newfeedbacks[] = $feedback;
                    }
                break;
                default:
                    // existing grade items
                    if (!empty($map[$key])) {
                        $scnt_all++; $scnt_upd++; 
                        // case of an id, only maps id of a grade_item
                        // this was idnumber
                        if (!$gradeitem = new grade_item(array('id'=>$map[$key], 'courseid'=>$course->id))) {
                            // supplied bad mapping, should not be possible since user
                            // had to pick mapping
                            $status = false;
                            import_cleanup($importcode);
                            echo $OUTPUT->notification(get_string('importfailed', 'grades'));
                            break 3;
                        }
                        // check if grade item is locked if so, abort
                        if ($gradeitem->is_locked()) {
                            $status = false;
                            import_cleanup($importcode);
                            echo $OUTPUT->notification(get_string('gradeitemlocked', 'grades'));
                            break 3;
                        }

                        $newgrade = new stdClass();
                        $newgrade->itemid     = $gradeitem->id;
                        $newgrade->studentname  = $studentname;
                        if ($gradeitem->gradetype == GRADE_TYPE_SCALE and $verbosescales) {
                            if ($value === '' or $value == '-') {
                                $value = null; // no grade
                            } else {
                                $scale = $gradeitem->load_scale();
                                $scales = explode(',', $scale->scale);
                                $scales = array_map('trim', $scales); //hack - trim whitespace around scale options
                                array_unshift($scales, '-'); // scales start at key 1
                                $key = array_search($value, $scales);
                                if ($key === false) {
                                    if (array_key_exists('csvimportskipbaddata',$CSVsettings) and $CSVsettings->csvimportskipbaddata) {
                                        $line['msg']=get_string('csvscaleerr','gradeimport_verboseimport',$header[$key]);
                                        $bad_grades[] = $line; $ecnts->ecnt_bad++; continue;
                                    } else {
                                        echo "<br/>t0 is $t0";
                                        echo "<br/>grade is $value";
                                        $status = false;
                                        import_cleanup($importcode);
                                        echo $OUTPUT->notification(get_string('badgrade', 'grades'));
                                        break 3;
                                    }
                                }
                                $value = $key;
                            }
                            $newgrade->finalgrade = $value;
                        } else {
                            if ($value === '' or $value == '-') {
                                $value = null; // No grade.
                            } else {
                                // If the value has a local decimal or can correctly be unformatted, do it.
                                $validvalue = unformat_float($value, true);
                                if ($validvalue !== false) {
                                    $value = $validvalue;
                                } else {
                                    if (array_key_exists('csvimportskipbaddata',$CSVsettings) and $CSVsettings->csvimportskipbaddata) {
                                        $line['msg']=get_string('csvgradeerr','gradeimport_verboseimport',$header[$key]);
                                        $bad_grades[] = $line; $ecnts->ecnt_bad++;
                                        continue;
                                    } else {
                                    // Non numeric grade value supplied, possibly mapped wrong column.
                                        echo "<br/>t0 is $t0";
                                        echo "<br/>grade is $value";
                                        $status = false;
                                        import_cleanup($importcode);
                                        echo $OUTPUT->notification(get_string('badgrade', 'grades'));
                                        break 3;
                                    }
                                }
                            }
                            $newgrade->finalgrade = $value;
                        }
                        if (array_key_exists('csvimportskipnullgrade',$CSVsettings) and $CSVsettings->csvimportskipnullgrade) {
                            if ($newgrade->finalgrade === null // drop nulls?
                            or ($nullignore && $newgrade->finalgrade == null)) { // or 0.0s?
                                // $line['msg']="missing grade for $header[$key] "; // in column $key";
                                $line['msg']=get_string('csvnoscoreerr','gradeimport_verboseimport',$header[$key]);
                                $skipped_lines[] = $line; $ecnts->ecnt_null++;
                                continue;
                            }
                        }
                        if (!empty($CSVsettings->csvimportwarngradeovermax)) {
                            if (round($gradeitem->grademax,3) < round($newgrade->finalgrade,2)) {
                                $maxg=round($gradeitem->grademax,3);
                                $a = new stdClass();
                                $a->name  = $maxg;
                                $a->key = $header[$key];
                                if (!empty($CFG->unlimitedgrades)) {
                                    // $line['msg']="grade exceeds item max($maxg) for $header[$key] - ok"; // in column $key";
                                    $line['msg']=get_string('csvovermax','gradeimport_verboseimport',$a)." - ok";
                                    $ovrmax_grades[] = $line; $wcnt_overmax++; // warning only - continue;
                                } else {
                                    // $line['msg']="grade exceeds item max($maxg) for $header[$key] "; // in column $key";
                                    $line['msg']=get_string('csvovermax','gradeimport_verboseimport',$a);
                                    $ovrmax_grades[] = $line; $ecnts->ecnt_overmax++;
                                    continue;
                                }
                            }
                        }
                        if ($gradeitem->grademax == GRADE_TYPE_VALUE) {
                            $ng = grade_format_gradevalue($gradeitem->grademax, $newgrade->finalgrade);

                            print "grade $ng<br>";
                        }
                        $newgrades[] = $newgrade;
                    } // otherwise, we ignore this column altogether
                      // because user has chosen to ignore them (e.g. institution, address etc)
                break;
            } // end case
        } // end foreach line key

        // no user mapping supplied at all, or user mapping failed
        if (empty($studentid) || !is_numeric($studentid)) {
            // user not found, abort whole import
            $status = false;
            import_cleanup($importcode);
            echo $OUTPUT->notification('user mapping error, could not find user!');
            break;
        }

        if ($separatemode and !groups_is_member($currentgroup, $studentid)) {
            // not allowed to import into this group, abort
            $status = false;
            if (array_key_exists('csvimportskipnongroup',$CSVsettings) and $CSVsettings->csvimportskipnongroup) {
                // $line['msg']="student $studentid not in group $currentgroup";
                $a = new stdClass();
                $a->name  = $studentid;
                $a->key = $currentgroup;
                $line['msg']=get_string('csvstudentgrouperr','gradeimport_verboseimport',$a);  // value,$header[$key]);
                $notin_group[] = $line; $ecnts->ecnt_ngrp++;
                continue;
            } else {
                import_cleanup($importcode);
                echo $OUTPUT->notification('user not member of current group, can not update!');
                break;
            }
        }
        
        // insert results of this students into buffer
        if ($status and !empty($newgrades)) {

            foreach ($newgrades as $newgrade) {
                $newgrade->importcode = $importcode;
                $newgrade->userid     = $studentid;
                $newgrade->importer   = $USER->id;
// TODD
// need to save locked list[item_studentid - to lookup and bypass in 'feedback loop' ;)
                // check if grade_grade is locked and if so, abort
                if (!empty($newgrade->itemid) and $grade_grade = new grade_grade(array('itemid'=>$newgrade->itemid, 'userid'=>$studentid))) {
// print_r($grade_grade); print "gradegrade"; return;
                    if ($grade_grade->is_locked()) {
                        // individual grade locked
                        if (array_key_exists('csvimportskiplockedgrade',$CSVsettings) and $CSVsettings->csvimportskiplockedgrade) {
                            $locked_grades[] = $newgrade; $ecnts->ecnt_lok++;
                            continue;
                        } else {
                            $status = false;
                            import_cleanup($importcode);
                            echo $OUTPUT->notification(get_string('gradelocked', 'grades'));
                            break 2;
                        }
                    }

                    if (array_key_exists('csvimportskipsamegrade',$CSVsettings) and $CSVsettings->csvimportskipsamegrade) {
                        if (sprintf("%f",round($grade_grade->finalgrade,5)) == sprintf("%f",round($newgrade->finalgrade,5))) {
                            // $newgrade->msg="duplicate grade for $studentid from ".round($grade_grade->finalgrade,2)." to $newgrade->finalgrade";
                            $a = new stdClass();
                            $a->name  = $studentid;
                            $a->key = round($grade_grade->finalgrade,2);
                            $a->key2= $newgrade->finalgrade;
                            $newgrade->msg=get_string('csvdupgrade','gradeimport_verboseimport',$a);  // value,$header[$key]);
                            $same_grades[] = $newgrade; $ecnts->ecnt_noupd++;
                            continue;
                        }
                    }

                    if (array_key_exists('csvimportauditchangegrade',$CSVsettings) and $CSVsettings->csvimportauditchangegrade) {
                        // $newgrade->msg="changing grade for $studentid from ".round($grade_grade->finalgrade,2)." to $newgrade->finalgrade";
                        $a = new stdClass();
                        $a->name  = $studentid;
                        $a->key = round($grade_grade->finalgrade,2);
                        $a->key2= $newgrade->finalgrade;
                        $newgrade->msg=get_string('csvchggrade','gradeimport_verboseimport',$a);  // value,$header[$key]);
                        $update_grades[] = $newgrade;
                    }
                    $icnt_upd++;
                } else {
                    if (array_key_exists('csvimportauditnewgrade',$CSVsettings) and $CSVsettings->csvimportauditnewgrade) {
                        // $newgrade->msg="adding grade for $studentid as $newgrade->finalgrade";
                        $a = new stdClass();
                        $a->name  = $studentid;
                        $a->key= $newgrade->finalgrade;
                        $newgrade->msg=get_string('csvaddgrade','gradeimport_verboseimport',$a);  // value,$header[$key]);
                        $new_grades[] = $newgrade;
                    }
                    $icnt_new++;
                }
                $gcnt_ok++;

                $DB->insert_record('grade_import_values', $newgrade);
            }
        } // end status and have grades
        // updating/inserting all comments here
        if ($status and !empty($newfeedbacks)) {
            foreach ($newfeedbacks as $newfeedback) {
                $sql = "SELECT *
                          FROM {grade_import_values}
                         WHERE importcode=? AND userid=?
                         AND itemid=? AND importer=?";
                if ($feedback = $DB->get_record_sql($sql, array($importcode, $studentid, $newfeedback->itemid, $USER->id))) {
                    $newfeedback->id = $feedback->id;
                    $DB->update_record('grade_import_values', $newfeedback);

                } else {
                    // the grade item for this is not updated
                    $newfeedback->importcode = $importcode;
                    $newfeedback->userid     = $studentid;
                    $newfeedback->importer   = $USER->id;
                    $DB->insert_record('grade_import_values', $newfeedback);
                }
                $icnt_fb++;
            }
        }
        $lcnt_ok++;
    } // end while lines
    if (array_key_exists('csvimportauditcsv',$CSVsettings) and $CSVsettings->csvimportauditcsv) {
        $spc="&nbsp;&nbsp;";
        $gcnt_tot = $icnt_upd + $icnt_new;

        $rptb = <<< EOX

Grades that will be updated:
    - Number of grades that will be inserted:  $icnt_new 
    - Number of grades that will be replaced with new score:  $icnt_upd 
    Warnings: 
       - Number of grade scores over the max grade item:  $wcnt_overmax 

Grades that will not be updated: 
  - Number of scores that are locked: $ecnts->ecnt_lok
  - Number of scores that have invalid data in input file: $ecnts->ecnt_bad
  - Number of scores that have a null value in input file: $ecnts->ecnt_null 
  - Number of scores that will not update as the grade is identical: $ecnts->ecnt_noupd
  - Number of scores not in group: $ecnts->ecnt_ngrp 
  - Number of students that do not exist in System: $ecnts->ecnt_nouser 

Input File Information: 
 - Number of lines/rows:  $lcnt_in 
 - ???Number of Empty lines: $lcnt_mt 
 - Number of Valid Lines: $lcnt_ok 
 - Number of grades that will update in System:  $gcnt_tot
 - ???Feedback ???:  $icnt_fb
EOX;

        $rptb = array();
        $rptb[]="<p>Errors:";
        $rptb[]=get_string('csvrptstudenterr','gradeimport_verboseimport')." $ecnts->ecnt_nouser ";
        if($notin_group){
            $rptb[]=get_string('csvrptgrperr','gradeimport_verboseimport')." $ecnts->ecnt_ngrp ";
        }
        $rptb[]=get_string('csvrptscorebad','gradeimport_verboseimport')." $ecnts->ecnt_bad";
        if (!empty($CSVsettings->csvimportskiplockedgrade) and $ecnts->ecnt_lok > 0) {
            $rptb[]=get_string('csvrptlocked','gradeimport_verboseimport')." - Number of scores that are locked: $ecnts->ecnt_lok";
        }

        if ($ecnts->ecnt_overmax > 0) {
            $rptb[]=get_string('csvrptovermax','gradeimport_verboseimport')." - Number of grade scores over the max grade item:  $ecnts->ecnt_overmax ";
        }

        if($ecnts->ecnt_noupd > 0 or $ecnts->ecnt_null > 0){
            $rptb[]="<p>Warnings - will not update: ";
            if (!empty($CSVsettings->csvimportskipsamegrade) and $ecnts->ecnt_noupd > 0) {
                $rptb[]=get_string('csvrptduplicate','gradeimport_verboseimport')." $ecnts->ecnt_noupd ";
            }
            if ($nullignore !== 0 and $ecnts->ecnt_null > 0) {
                $rptb[]=get_string('csvrptnullvals','gradeimport_verboseimport')." $ecnts->ecnt_null ";
            }
        }

        if ($wcnt_overmax > 0) {
            $rptb[]="<p>Warnings - these will attempt to update: ";
            $rptb[]=get_string('csvrptovermax','gradeimport_verboseimport')." $wcnt_overmax ";
        }

        $data_cnt = ($lcnt_in - 1);
        $bad_cnt = $scnt_all - $gcnt_tot;
        $bad_new = $scnt_new - $icnt_new;
        $bad_upd = $scnt_upd - $icnt_upd;

        $rpta = "\n\nScores that will update: $gcnt_tot\n";
        if($bad_cnt) $rpta .= "Scores that will not update: $bad_cnt\n";
        if($icnt_fb) $rpta .= "Feedback that will update: $icnt_fb\n"; 

        $Content=nl2br($rpta.'<br>');
        $Content.='<br> - '.  get_string('csvnullswill','gradeimport_verboseimport')
            .($nullignore ? get_string('csvnullsignored','gradeimport_verboseimport')  
                : get_string('csvnullsokay','gradeimport_verboseimport'));
//        outputSection('Import File Summary', $Content);
        outputSection(get_string('csvrptsummary','gradeimport_verboseimport'), $Content);

    }
    if ($showdetail) {
        $Content=implode("<br>",$rptb);
//        outputSection('Detail', $Content);
        outputSection(get_string('csvrptdetail','gradeimport_verboseimport'), $Content);
    }
    
    if ($showdetail==2) {
  // review?
        if ($update_grades) {
            $table = new html_table();
            $table->head = array_keys((array)$update_grades[0]);
            $table->data = array_values($update_grades);
            $Content=html_writer::table($table);
            // outputSection('Potential updates', $Content);
            outputSection(get_string('csvrptupdates','gradeimport_verboseimport'), $Content);
        }
        if ($new_grades) {
            $table = new html_table();
            $table->head = array_keys((array)$new_grades[0]);
            $table->data = array_values($new_grades);
            $Content=html_writer::table($table);
            // outputSection('Potential inserts', $Content);
            outputSection(get_string('csvrptinserts','gradeimport_verboseimport'), $Content);
        }
  // warnings?
        if ($ovrmax_grades) {
            $table = new html_table();
            $table->head = $lines_hdr; // array_keys((array)$new_grades[0]);
            $table->data = array_values($ovrmax_grades);
            $Content=html_writer::table($table);
            if($wcnt_overmax > 0){
                // outputSection('Warning: Accepted grade exceeds Item Maximum', $Content);
                outputSection(get_string('csvrptoverwarn','gradeimport_verboseimport'), $Content);
            } else {
                // outputSection('Error: Grade exceeds Item Maximum', $Content);
                outputSection(get_string('csvrptovererror','gradeimport_verboseimport'), $Content);
            }
        }
// errors?
        if ($locked_grades) {
            $table = new html_table();
            $table->head = array_keys((array)$locked_grades[0]);
            $table->data = array_values($locked_grades);
            $Content=html_writer::table($table);
            // outputSection('Error: Grade score bypassed - Locked grade', $Content);
            outputSection(get_string('csvrptlocked','gradeimport_verboseimport'), $Content);
        }
        if ($skipped_lines) {
            $table = new html_table();
            $table->head = $lines_hdr; // array_keys((array)$skipped_lines[0]);
            $table->data = array_values($skipped_lines);
            $Content=html_writer::table($table);
            // outputSection('Error: Grade score bypassed - No grade', $Content);
            outputSection(get_string('csvrptnograde','gradeimport_verboseimport'), $Content);
        }
        if ($bad_grades) {  
            $table = new html_table();
            $table->head = $lines_hdr; // array_keys((array)$bad_grades[0]);
            $table->data = array_values($bad_grades);
            $Content=html_writer::table($table);
            // outputSection('Error: Grade score bypassed - Invalid grades bypassed', $Content);
            outputSection(get_string('csvrptbadgrade','gradeimport_verboseimport'), $Content);
        }
        if ($notin_group) {
            $table = new html_table();
            $table->head = $lines_hdr; // array_keys((array)$notin_group[0]);
            $table->data = array_values($notin_group);
            $Content=html_writer::table($table);
            // outputSection('Error: Grade score bypassed - Invalid group bypassed', $Content);
            outputSection(get_string('csvrptbadgroup','gradeimport_verboseimport'), $Content);
        }
        if ($notin_site) {
            $table = new html_table();
            $table->head = $lines_hdr; // array_keys((array)$notin_site[0]);
            $table->data = array_values($notin_site);
            $Content=html_writer::table($table);
            // outputSection('Error: Grade score bypassed - Invalid student bypassed', $Content);
            outputSection(get_string('csvrptbadstudent','gradeimport_verboseimport'), $Content);
        }
        if ($same_grades) {
            $table = new html_table();
            $table->head = array_keys((array)$same_grades[0]);
            $table->data = array_values($same_grades);
            $Content=html_writer::table($table);
            // outputSection('Warning: Grade score bypassed - Duplicate update skipped', $Content);
            outputSection(get_string('csvrptdupescore','gradeimport_verboseimport'), $Content);
        }
    }
if (!empty($debug)) print "cancel: $cancelupd showdetail: $showdetail doupdate: $doupdate<br>";

    if (array_key_exists('csvimportauditcsv',$CSVsettings) and $CSVsettings->csvimportauditcsv) {
        if (!$doupdate) { // have confirmed
            $optionc=$options; $optiond=$options;
            $options['showdetail']=0;
            $optionc['cancelupd']=1;
            $buttonc = new single_button(new moodle_url($indexphp, $optionc), 'Cancel');
            $options['showdetail']=0;
            $options['doupdate']=1;
            if(empty($CSVsettings->csvimportpreviewonly)) {
                $buttongo = new single_button(new moodle_url($indexphp, $options),
                     get_string('csvbtnupdate','gradeimport_verboseimport'));
            } else {
                $buttongo = new single_button(new moodle_url('/grade/import/verboseimport/', array('id'=>$id)),
                     get_string('csvbtnreturn','gradeimport_verboseimport'));
            }     
            $buttondet='';
            if (!$showdetail) { // prompt to show detail
                $optiond['showdetail']=1;
                $buttondet = $OUTPUT->single_button(new moodle_url($indexphp, $optiond),
                     get_string('csvbtnshowdetail','gradeimport_verboseimport'));
            }
            if ($showdetail==1) { // prompt to show more detail
                $optiond['showdetail']=2;
                $buttondet = $OUTPUT->single_button(new moodle_url($indexphp, $optiond),
                     get_string('csvbtnmoredetail','gradeimport_verboseimport'));
            }
            echo $OUTPUT->confirm($buttondet, $buttongo,$buttonc);
            echo $OUTPUT->footer();
            $status=false;
        }
    }  

    if ($noupdates) {
        $status=false;
    }
    /// at this stage if things are all ok, we commit the changes from temp table
    if ($status) {
 print "updating...<br>";
        grade_import_commit($course->id, $importcode);
    } else {
        import_cleanup($importcode);
    }
} else {
    // If data hasn't been submitted then display the data mapping form.
    $mform2->display();
    echo $OUTPUT->footer();
}
