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

defined('MOODLE_INTERNAL') || die();

function local_assign_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case    FEATURE_MOD_INTRO :
            return false;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        default:
            return null;
    }
}

/*
 * This function tells if a module is used into a given course
 * @param string $modulename
 * @param int $courseid
 * @return boolean
 */
function assign_is_used_in_course($courseid) {
    global $DB;
    $moduleid = get_assign_id();
    $courseinfo = $DB->get_records('course_modules', array('course' => $courseid, 'module' => $moduleid), 'id');
    return (!empty($courseinfo));
}


function assign_is_in_team($assignid) {
     global $DB;
     $info = $DB->get_record_select('assign', "id='$assignid'", null, 'teamsubmission');
     return $info->teamsubmission;
}
/*
 * This function gives the id of a given module
 * @param string $moodulename
 * @return int  id of the module if exists, false otherwise
 */
function get_assign_id() {
    global $DB;
    $mods = $DB->get_records('modules');
    foreach ($mods as $module) {
        if ($module->name == 'assign') {
            return $module->id;
        }
    }
    return false;
}

/*
 * This function adds the course navigation menu to add a link to this tool.
 */
function local_assign_extend_navigation_course(navigation_node $parentnode, stdClass $course , context_course $context  ) {
     global $CFG;
     // Show only if assign tool is activated.
    if (assign_is_used_in_course($course->id)  && has_capability('moodle/course:manageactivities', $context)) {
        $link = new moodle_url($CFG->wwwroot .'/local/assign/index.php', array('id' => $course->id));
        $parentnode->add(get_string('assigns', 'local_assign'), $link, navigation_node::TYPE_SETTING);
    }
}

/*
 * This function is usefull to extend settings navigation
 */
function local_assign_extend_settings_navigation($settingsnav, $context) {
    global $PAGE, $COURSE;

    // Only add this settings item on non-site course pages.
    if (!$PAGE->course or $PAGE->course->id == 1) {
        return;
    }
    if ($settingnode = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING)) {
        if ($PAGE->cm->modname == 'assign') {
            $assignid = $PAGE->cm->instance;
            $groupmode = assign_is_in_team($assignid);
            if ($groupmode) {
                $url = new moodle_url('/local/assign/group_view.php', array('id' =>$PAGE->cm->id));                
                $settingnode->add(get_string('display_group_view', 'local_assign'), $url, settings_navigation::TYPE_SETTING);
            }
        }
    }
}

/*
 * Give the list of assigns (seminars) for this course in array form
 * the key of the array is the id of the assign
 * each element of the array consists of some infos of this assign :
 *  - cmid : id of the instance corresponding to this assign in course_modules
 *  - name : title of the assign
 *  - dudate : Due date for the assign
 *  - fromdate : The value of allowsubmissionfromdate for this assign
 * @param courseid : int
 * @return seminarlist : array
 */
function get_seminar_list( $courseid ) {
    global $DB;
    $seminarlist = array();

    // Get the semainarlist for this course.
    $list = $DB->get_records_select('assign', "course='$courseid'", null, 'duedate,allowsubmissionsfromdate');

    if (empty($list)) {
        return $seminarlist;
    }

    // Get the id correspondign to the assign tool.
    $moduleid = get_module_id ('assign');

    foreach ($list as $value) {
        // Foreach assign of the list get the corresponding instance in the course_modules table.
        $info = $DB->get_record_select('course_modules',
                "course='$courseid' AND module='$moduleid' AND instance='".$value->id."'", null, 'id');
        // Construct the array with the corresponding value of this assign.
        if ($info) {
            $seminarlist[$value->id] = array('cmid' => $info->id, 'name' => $value->name,
            'duedate' => $value->duedate, 'fromdate' => $value->allowsubmissionsfromdate);
        }
    }
    return $seminarlist;
}
/*
 * Get the info for a given user azccording to the seminarlist.
 * @param userid : int
 * @param seminarlist : array
 * @return seminarinfouser : array
 */
function get_seminar_info_for_user ( $userid, $seminarlist ) {
    global $DB;
    $seminarinfoforuser = array();
    foreach (array_keys($seminarlist) as $seminarid) {
        // Foreach assign of the list get the infos concerning submisisons for the given user.
        $list = $DB->get_records('assign_submission', array('userid' => $userid,
            'assignment' => $seminarid, 'status' => 'submitted'));
        if (!empty($list)) {
            foreach ($list as $info) {
                $seminarinfoforuser[$seminarid][] = array('id' => $info->id, 'timecreated' => $info->timecreated,
                    'timemodified' => $info->timemodified);
            }
        }
    }
    return $seminarinfoforuser;
}

/*
 * Clean some html tags
 * @param content : string
 * @return output : string
 */
function clean_html ($content) {
    $output = strip_tags($content);
    return $output;
}

/*
 * Get all documents of a given user for all the assigns of this course
 * @param userid : int
 * @param seminarlist : array
 * @param format : string
 * @return a fiel is proposed to download
 */
function get_docs_for_student ( $userid , $seminarlist, $format) {
    global $course, $CFG;
    $filesforzipping = array();
    list($filename, $filesforzipping, $text) = send_content_for_user($userid, $course, $seminarlist, $filesforzipping, $format);
    if ($format != 'text') {
        // If format is zip, from the collected fields generate the zip archive.
        if (count($filesforzipping) != 0) {
            $tempzip = tempnam($CFG->tempdir . '/', 'assignment_');
            // Zip files.
            $zipper = new zip_packer();
            if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
                // Send the zip file to download.
                send_temp_file($tempzip, $filename);
            }
        }
    } else {
        // If format is txt, send the onlinetext collected in a filename.
        send_content_in_file ($text, $filename);
    }
    return true;
}

/*
 * Get the online content for a submission
 * @param submissionid : int
 * @return string
 */
function get_onlinetext_for_submission ($submissionid) {
    global $DB;
    $list = $DB->get_record_select('assignsubmission_onlinetext', "submission='$submissionid'", null, 'onlinetext');
    if (!empty($list)) {
        foreach ($list as $value) {
            return $value;
        }
    }
    return '';
}

/*
 * This function gets all documents for a given user and courselist in a specific format
 */
function get_docs_for_all_students ( $userlist , $seminarlist, $format = '' ) {
     global $course, $CFG;
     /* @todo : Il faudrait creer un répertoire qui contienne un dossier avec le nom de l'étudiant
     *  et dans ce dossier toutes les soumissions
     */

    $filesforzipping = array();
    foreach ($userlist as $user) {
        $userid = $user['id'];
        // Get the identity of the user according to its id.
        list($filename, $filesforzipping) = send_content_for_user($userid, $course, $seminarlist, $filesforzipping, $format, true);
    }
    // Send the zip file to download.

    if (count($filesforzipping) != 0) {
        $tempzip = tempnam($CFG->tempdir . '/', 'assignment_');
        // Zip files.
        $zipper = new zip_packer();
        if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
            // Send the zip file to download.
            send_temp_file($tempzip, $filename);
        }
    }
}

function send_content_for_user($userid, $course, $seminarlist, $filesforzipping, $format = '', $multi = false) {
    $student = get_user_identity($userid);
    if (!$multi) {
        // The filename is based on the lastname and firstname of the user.
        $filename = clean_filename($course->shortname) . '_' . clean_filename($student->lastname . '_' .$student->firstname);
    } else {
        // Filename is the shortname of the course.
         $filename = clean_filename($course->shortname);
    }

    ($format == '') ? $filename .= '.zip' : $filename .= '.txt';

    $i = 0;
    $text = '';
    foreach ($seminarlist as $seminar) {
        $i++;
        // Foreach seminarlist get courseinfo and instance info of this assign.
        list ($course, $cm) = get_course_and_cm_from_cmid($seminar['cmid'], 'assign');
        // Get the context instance of this instance.
        $context = context_module::instance($cm->id);
        // Construct the assign.
        $assign = new assign($context, $cm, $course);

        $groupname = '';
        if ($assign->get_instance()->teamsubmission) {
            $submission = $assign->get_group_submission($userid, 0, false);
            $submissiongroup = $assign->get_submission_group($userid);
            if ($submissiongroup) {
                $groupname = $submissiongroup->name . '';
            } else {
                $groupname = get_string('defaultteam', 'assign');
            }
            $filename = $groupname . '.zip';
        } else {
            // Submission is individual, no group.
            $submission = $assign->get_user_submission($userid, false);
        }

        if ($assign->is_blind_marking()) {
            $prefix = str_replace('_', ' ', $groupname . get_string('participant', 'assign'));
            $prefix = clean_filename($prefix . '_' . $assign->get_uniqueid_for_user($userid) . '_');
        } else {
            if (!$assign->get_instance()->teamsubmission) {
                 $prefix = str_replace('_', ' ', $groupname . $student->lastname);
            } else {
                $prefix = str_replace('_', ' ', $groupname);
            }
            $prefix = clean_filename($prefix . '_' . $assign->get_uniqueid_for_user($userid) . '_');
        }
        if ($submission) {
            // If there is a submission, contruct the return according to the format (zip/txt).
            if ($format != '.txt') {
                // If format is zip, then get all the files related to the submissions of this user.
                foreach ($assign->get_submission_plugins() as $plugin) {
                    if ($plugin->is_enabled() && $plugin->is_visible()) {
                        $pluginfiles = $plugin->get_files($submission, $student);
                        foreach ($pluginfiles as $zipfilename => $file) {
                            $subtype = $plugin->get_subtype();
                            $type = $plugin->get_type();
                            $prefixedfilename = clean_filename($prefix .
                                                               $subtype .
                                                               '_' .
                                                               $type .
                                                               '_' .
                                                               $zipfilename);
                            $filesforzipping[$prefixedfilename] = $file;
                        }
                    }
                }
            } else {
                // If format is text, get all the onlinetext for the submissions of this user.
                $mydate = $seminar['fromdate'];
                if ( !$mydate ) {
                    $mydate = $seminar['duedate'];
                }
                $text .= 'Seminaire '. $i . ' : '. $seminar['name'] . ' du '. utf8_decode(userdate($mydate))
                        . "\n \n";
                $content = get_onlinetext_for_submission ($submission->id);
                if ($content != '') {
                    $text .= clean_html($content) . "\n\n";
                } else {
                    $text .= ':  Pas de descriptif ' . "\r\n" . "\r\n";
                }
                $text .= '-----------------------------------------------------' . "\n";
            }
        }
    }
    return array($filename, $filesforzipping, $text);
}

/*
 * This function sends content in a given file
 * @param string $content
 * @patam string $filename
 */
function send_content_in_file ($content, $filename) {
    header("Content-Type: application/zip");
    header("Content-Disposition: attachment; filename=$filename");
    echo $content;
    exit;
} 
