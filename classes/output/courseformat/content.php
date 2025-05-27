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
 * Contains the default content output class.
 *
 * @package   format_mint_topics
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_mint_topics\output\courseformat;

use renderer_base;
use core_courseformat\output\local\content as content_base;
use stdClass;

require_once($CFG->dirroot . '/course/format/mint_topics/locallib.php');

/**
 * Base class to render a course content.
 *
 * @package   format_mint_topics
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends content_base {

    /**
     * @var bool Topic format has add section after each topic.
     *
     * The responsible for the buttons is core_courseformat\output\local\content\section.
     */
    protected $hasaddsection = false;

    private $sectioncompletionpercentage = array();
    private $sectioncompletionmarkup = array();
    private $sectioncompletioncalculated = array();

    /**
     * Export this data so it can be used as the context for a mustache template (core/inplace_editable).
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a Mustache template
     */
    public function export_for_template(\renderer_base $output) {
        global $PAGE;
        $format = $this->format;

        // Most formats uses section 0 as a separate section so we remove from the list.
        $sections = $this->export_sections($output);
        $course = $format->get_course();
        $initialsection = '';
        if (!empty($sections)) {
            $initialsection = array_shift($sections);
        }

        $data = (object)[
            'title' => $format->page_title(), // This method should be in the course_format class.
            'initialsection' => $initialsection,
            'sections' => $sections,
            'format' => $format->get_format(),
            'sectionreturn' => 0,
            'forumpost' => $this->get_last_forum_post($course) ? $this->get_last_forum_post($course) : false,
            'scgraphic'=> $this->section_completion_graphic(false, $course, false, $output),
        ];

        // The single section format has extra navigation.
        $singlesection = $this->format->get_sectionnum();
        if ($singlesection) {
            if (!$PAGE->theme->usescourseindex) {
                $sectionnavigation = new $this->sectionnavigationclass($format, $singlesection);
                $data->sectionnavigation = $sectionnavigation->export_for_template($output);

                $sectionselector = new $this->sectionselectorclass($format, $sectionnavigation);
                $data->sectionselector = $sectionselector->export_for_template($output);
            }
            $data->hasnavigation = true;
            $data->singlesection = array_shift($data->sections);
            $data->sectionreturn = $singlesection;
        }

        if ($this->hasaddsection) {
            $addsection = new $this->addsectionclass($format);
            $data->numsections = $addsection->export_for_template($output);
        }

        return $data;
    }


    /**
     * Get last forum post from section 0
     *
     * @param $course
     * @return false|string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function get_last_forum_post($course){
        global $USER;

        if($forums = get_all_instances_in_course("forum", $course)){
            $vaultfactory = \mod_forum\local\container::get_vault_factory();
            $forumvault = $vaultfactory->get_forum_vault();

            foreach ($forums as $forum) {

                if ($forum->section == 0) {

                    $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id);
                    $context = \context_module::instance($cm->id);
                    if (has_capability('mod/forum:viewdiscussion', $context)) {

                        $forum =  $forumvault->get_from_id($forum->id);

                        $discussionvault = $vaultfactory->get_discussion_vault();
                        if($discussion = $discussionvault->get_last_discussion_in_forum($forum)){
                            $posts = forum_get_all_discussion_posts($discussion->get_id(),'p.created DESC');
                            $post = array_shift($posts);

                            $postvault = $vaultfactory->get_post_vault();
                            $post = $postvault->get_from_id($post->id);

                            $rendererfactory = \mod_forum\local\container::get_renderer_factory();
                            $discussionrenderer = $rendererfactory->get_discussion_renderer($forum, $discussion, 3);

                            return $discussionrenderer->render($USER, $post, []);
                        }else{
                            return false;
                        }
                    }
                }
            }
        }
        return false;
    }

    public function get_template_name(\renderer_base $renderer): string {
        return 'format_mint_topics/local/content';
    }


    /**
     * Generate the section completion graphic if any.
     *
     * @param stdClass $section The course_section entry from DB.
     * @param stdClass $course the course record from DB.
     * @return string the markup or empty if nothing to show.
     */
    public function section_completion_graphic($section, $course, $activitiesstat,\renderer_base $output) {
        $markup = '';
        if (($course->enablecompletion)) {
            if(list($complete, $total) = $this->section_activity_progress($section, $course)){
                $percentage = round(($complete / $total) * 100);
                if($activitiesstat){
                    $progressbar = ['progressbar' => ['percents' => $percentage,'activities'=>"{$complete}/{$total}",'primarycolor'=>"#8139a3",'secondarycolor'=>'#d0b5dd','fontcolor'=>'#ffffff']];
                    $markup = $output->render_from_template('format_mintcampus/progressbar', $progressbar);
                }else{
                    $progressbar = ['progressbar' => ['percents' => $percentage,'activities'=>null,'primarycolor'=>"#8139a3",'secondarycolor'=>'#d0b5dd','fontcolor'=>'#ffffff']];
                    $markup = $output->render_from_template('format_mintcampus/progressbar', $progressbar);
                }
                $markup = "<h3>Mein Kursfortschritt</h3>" . $markup;

            }
        }
        return $markup;
    }
    /**
     * Calculate the section progress.
     *
     * Adapted from core section_activity_summary() method.
     *
     * @param stdClass $section The course_section entry from DB.
     * @param stdClass $course the course record from DB.
     * @return bool/int false if none or the actual progress.
     */
    protected function section_activity_progress($section, $course) {
        $modinfo = get_fast_modinfo($course);
        if ($section && empty($modinfo->sections[$section->section])) {
            return false;
        }

        // Generate array with count of activities in this section:
        $sectionmods = array();
        $total = 0;
        $complete = 0;
        $cancomplete = isloggedin() && !isguestuser();
        $completioninfo = new \completion_info($course);
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
     * Calculate and generate the markup for completion of the activities in a section.
     *
     * @param stdClass $section The course_section.
     * @param stdClass $course the course.
     * @param stdClass $modinfo the course module information.
     * @param renderer_base $output typically, the renderer that's calling this method.
     */
    protected function calculate_section_activity_completion($section, $course, $modinfo, \renderer_base $output) {
        if (empty($this->sectioncompletioncalculated[$section->section])) {
            $this->sectioncompletionmarkup[$section->section] = '';
            if (empty($modinfo->sections[$section->section])) {
                $this->sectioncompletioncalculated[$section->section] = true;
                return;
            }

            // Generate array with count of activities in this section.
            $total = 0;
            $complete = 0;
            $cancomplete = isloggedin() && !isguestuser();
            $asectionisvisible = false;
            if ($cancomplete) {
                $completioninfo = new \completion_info($course);
                foreach ($modinfo->sections[$section->section] as $cmid) {
                    $thismod = $modinfo->cms[$cmid];

                    if ($thismod->visible) {
                        $asectionisvisible = true;
                        if ($completioninfo->is_enabled($thismod) != COMPLETION_TRACKING_NONE) {
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

            if ((!$asectionisvisible) || (!$cancomplete)) {
                // No sections or no completion.
                $this->sectioncompletioncalculated[$section->section] = true;
                return;
            }

            // Output section completion data.
            if ($total > 0) {
                $percentage = round(($complete / $total) * 100);
                $this->sectioncompletionpercentage[$section->section] = $percentage;

                $data = new \stdClass();
                $data->percentagevalue = $this->sectioncompletionpercentage[$section->section];
                if ($data->percentagevalue < 11) {
                    $data->percentagecolour = 'low';
                } else if ($data->percentagevalue < 90) {
                    $data->percentagecolour = 'middle';
                } else {
                    $data->percentagecolour = 'high';
                }
                if ($data->percentagevalue < 1) {
                    $data->percentagequarter = 0;
                } else if ($data->percentagevalue < 26) {
                    $data->percentagequarter = 1;
                } else if ($data->percentagevalue < 51) {
                    $data->percentagequarter = 2;
                } else if ($data->percentagevalue < 76) {
                    $data->percentagequarter = 3;
                } else {
                    $data->percentagequarter = 4;
                }
                $this->sectioncompletionmarkup[$section->section] = $output->render_from_template('format_mintcampus/mintcampus_completion', $data);
            }

            $this->sectioncompletioncalculated[$section->section] = true;
        }
    }
}
