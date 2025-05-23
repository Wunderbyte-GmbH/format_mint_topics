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
 * Calculate the section progress.
 *
 * Adapted from core section_activity_summary() method.
 *
 * @param stdClass $section The course_section entry from DB.
 * @param stdClass $course the course record from DB.
 * @return bool/int false if none or the actual progress.
 */
function format_mint_topics_section_activity_progress($section, $course) {
    $modinfo = get_fast_modinfo($course);
    if ($section&&empty($modinfo->sections[$section->section])) {
        return false;
    }

    // Generate array with count of activities in this section:
    $sectionmods = array();
    $total = 0;
    $complete = 0;
    $cancomplete = isloggedin() && !isguestuser();
    $completioninfo = new \completion_info($course);

    if($section){
        foreach ($modinfo->sections[$section->section] as $cmid) {
            $thismod = $modinfo->cms[$cmid];

            // Labels counted for now, see: https://tracker.moodle.org/browse/MDL-65853.

            if ($thismod->uservisible) {
                if (isset($sectionmods[$thismod->modname])) {
                    $sectionmods[$thismod->modname]['name'] = $thismod->modplural;
                    $sectionmods[$thismod->modname]['count']++;
                } else {
                    $sectionmods[$thismod->modname]['name'] = $thismod->modfullname;
                    $sectionmods[$thismod->modname]['count'] = 1;
                }
                if ($cancomplete && $completioninfo->is_enabled($thismod) != COMPLETION_TRACKING_NONE) {
                    $total++;
                    $completiondata = $completioninfo->get_data($thismod, true);
                    if ($completiondata->completionstate == COMPLETION_COMPLETE ||
                        $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                        $complete++;
                    }
                }
            }
        }
    }else{
        foreach ($modinfo->sections as $ssection){
            foreach ($ssection as $cmid) {
                $thismod = $modinfo->cms[$cmid];

                // Labels counted for now, see: https://tracker.moodle.org/browse/MDL-65853.

                if ($thismod->uservisible) {
                    if (isset($sectionmods[$thismod->modname])) {
                        $sectionmods[$thismod->modname]['name'] = $thismod->modplural;
                        $sectionmods[$thismod->modname]['count']++;
                    } else {
                        $sectionmods[$thismod->modname]['name'] = $thismod->modfullname;
                        $sectionmods[$thismod->modname]['count'] = 1;
                    }
                    if ($cancomplete && $completioninfo->is_enabled($thismod) != COMPLETION_TRACKING_NONE) {
                        $total++;
                        $completiondata = $completioninfo->get_data($thismod, true);
                        if ($completiondata->completionstate == COMPLETION_COMPLETE ||
                            $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                            $complete++;
                        }
                    }
                }
            }
        }
    }

    if (empty($sectionmods)) {
        // No sections
        return false;
    }

    if ($total == 0) {
        return false;
    }

    return [$complete, $total];
}

/**
 * Generate the section completion graphic if any.
 *
 * @param stdClass $section The course_section entry from DB.
 * @param stdClass $course the course record from DB.
 * @return string the markup or empty if nothing to show.
 */
function format_mint_topics_section_completion_graphic($section, $course, $activitiesstat=false) {
    global $OUTPUT;
    $markup = '';
    if (($course->enablecompletion)) {
        if(list($complete,$total)= format_mintcampus_section_activity_progress($section, $course)){
            $percentage = round(($complete / $total) * 100);
            if($activitiesstat){
                $progressbar = ['progressbar'=>['percents'=>$percentage,'activities'=>"{$complete}/{$total}",'primarycolor'=>"#8139a3",'secondarycolor'=>'#d0b5dd','fontcolor'=>'#ffffff']];
                $markup = $OUTPUT->render_from_template('format_mintcampus/progressbar', $progressbar);
            }else{
                $progressbar = ['progressbar'=>['percents'=>$percentage,'activities'=>null,'primarycolor'=>"#8139a3",'secondarycolor'=>'#d0b5dd','fontcolor'=>'#ffffff']];
                $markup = $OUTPUT->render_from_template('format_mintcampus/progressbar', $progressbar);
            }
        }
    }
    return $markup;
}
