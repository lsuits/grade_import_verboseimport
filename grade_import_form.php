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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once $CFG->libdir.'/formslib.php';
require_once($CFG->libdir.'/gradelib.php');

class grade_import_form extends moodleform {
    function definition () {
        global $COURSE;

        $mform =& $this->_form;

        if (isset($this->_customdata)) {  // hardcoding plugin names here is hacky
            $features = $this->_customdata;
        } else {
            $features = array();
        }

        // course id needs to be passed for auth purposes
        $mform->addElement('hidden', 'id', optional_param('id', 0, PARAM_INT));
        $mform->setType('id', PARAM_INT);
        if (empty($features['paste'])) {
            // Restrict the possible upload file types.
            if (!empty($features['acceptedtypes'])) {
                $acceptedtypes = $features['acceptedtypes'];
            } else {
                $acceptedtypes = '*';
            }

            $mform->addElement('header', 'general', get_string('importfile', 'grades'));
            // file upload
            $mform->addElement('filepicker', 'userfile', get_string('file'), null, array('accepted_types' => $acceptedtypes));
            $mform->addRule('userfile', null, 'required');
        } else {
            $mform->addElement('header', 'general', get_string('importdatafromcopypaste','gradeimport_verboseimport'));
            // file upload
            $mform->addElement('textarea', 'userdata', 'Data',array('rows'=>10, 'style'=>'margin: 0px 0px 10px; width: 475px; height: 209px;'));
            $mform->addRule('userdata', null, 'required');
        }
        $encodings = textlib::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'grades'), $encodings);
        if (!empty($features['includeseparator'])) {
            $radio = array();
            $radio[] = $mform->createElement('radio', 'separator', null, get_string('septab', 'grades'), 'tab');
            $radio[] = $mform->createElement('radio', 'separator', null, get_string('sepcomma', 'grades'), 'comma');
            $radio[] = $mform->createElement('radio', 'separator', null, get_string('sepcolon', 'grades'), 'colon');
            $radio[] = $mform->createElement('radio', 'separator', null, get_string('sepsemicolon', 'grades'), 'semicolon');
            $mform->addGroup($radio, 'separator', get_string('separator', 'grades'), ' ', false);
        }
        if (empty($features['paste'])) {
            $mform->setDefault('separator', 'comma');
        } else {
            $mform->setDefault('separator', 'tab');
        }

        if (!empty($features['verbosescales'])) {
            $options = array(1=>get_string('yes'), 0=>get_string('no'));
            $mform->addElement('select', 'verbosescales', get_string('verbosescales', 'grades'), $options);
        }

        $options = array('10'=>10, '20'=>20, '100'=>100, '1000'=>1000, '100000'=>100000);
        $mform->addElement('select', 'previewrows', get_string('rowpreviewnum', 'grades'), $options); // TODO: localize
        $mform->setType('previewrows', PARAM_INT);
        if(array_key_exists('paste',$features)) {
            $mform->addElement('hidden','paste',$features['paste']);
            $mform->setType('paste', PARAM_INT);
        }
        $mform->addElement('hidden', 'groupid', groups_get_course_group($COURSE));
        $mform->setType('groupid', PARAM_INT);
        $this->add_action_buttons(false, get_string('uploadgrades', 'grades'));
    }
} // end class grade_import_form

class grade_import_mapping_form extends moodleform {

    function definition () {
        global $CFG, $COURSE;
        $mform =& $this->_form;

        // this is an array of headers
        $header = $this->_customdata['header'];
        // course id

        $mform->addElement('header', 'general', get_string('identifier', 'grades'));
        $mapfromoptions = array();

        if ($header) {
            foreach ($header as $i=>$h) {
                $mapfromoptions[$i] = s($h);
            }
        }
        $mform->addElement('select', 'mapfrom', get_string('mapfrom', 'grades'), $mapfromoptions);

        //  deleted some etries and renamed perm
        $maptooptions = array( 'useridnumber'=>'student id number', 'useremail'=>'email address', 'username'=>'username', '0'=>'ignore');
        $mform->addElement('select', 'mapto', get_string('mapto', 'grades'), $maptooptions);

        $mform->addElement('header', 'general', get_string('mappings', 'grades'));

        // Add a feedback option.
        $feedbacks = array();
        if ($gradeitems = $this->_customdata['gradeitems']) {
            foreach ($gradeitems as $itemid => $itemname) {
                $feedbacks['feedback_'.$itemid] = get_string('feedbackforgradeitems', 'grades', $itemname);
            }
        }

        if ($header) {
            $i = 0; // index
            foreach ($header as $h) {
                $h = trim($h);
                // This is what each header maps to.
                $headermapsto = array(
                    get_string('others', 'grades')     => array(
                        '0'   => get_string('ignore', 'grades'),
                        'new' => get_string('newitem', 'grades')
                    ),
                    get_string('gradeitems', 'grades') => $gradeitems,
                    get_string('feedbacks', 'grades')  => $feedbacks
                );
                $mform->addElement('selectgroups', 'mapping_'.$i, s($h), $headermapsto);
                $i++;
            }
        }
        // Show null/zero prompt?
        $nulltoupdate = empty($this->_customdata['nulltoupdate']) ? '0' : '1';
        if ($nulltoupdate) {
            $nullignore=substr($this->_customdata['nulltoupdate'],-1);
            $mform->addElement('header', 'general', 'Data options');
            $options = array(1=>get_string('yes'), 0=>get_string('no'));
            $mform->addElement('select', 'nullignore', 'Ignore NULL grades', $options,'Otherewise these will overlay existing scores');
            $mform->setDefault('nullignore', !empty($nullignore) ? 1 : 0);
        }
        // course id needs to be passed for auth purposes
        $mform->addElement('hidden', 'map', 1);
        $mform->setType('map', PARAM_INT);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'iid');
        $mform->setType('iid', PARAM_INT);
        $mform->addElement('hidden', 'importcode');
        $mform->setType('importcode', PARAM_FILE);
        $mform->addElement('hidden', 'verbosescales', 1);
        $mform->setType('verbosescales', PARAM_INT);
        $mform->addElement('hidden', 'groupid', groups_get_course_group($COURSE));
        $mform->setType('groupid', PARAM_INT);
        $this->add_action_buttons(true, get_string('uploadgrades', 'grades'));

    }
} // end class grade_import_mapping_form
