<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * The main configuration form for the activity.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_aibranchedscenario\local\schema;

/**
 * Module instance settings form.
 *
 * @package    mod_aibranchedscenario
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_aibranchedscenario_mod_form extends moodleform_mod {
    /**
     * Define the form fields.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('activityname', 'mod_aibranchedscenario'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'activityname', 'mod_aibranchedscenario');

        $this->standard_intro_elements();

        $mform->addElement('header', 'presentation', get_string('presentation', 'mod_aibranchedscenario'));

        $themes = [];
        foreach (schema::theme_ids() as $theme) {
            $themes[$theme] = get_string('theme:' . $theme, 'mod_aibranchedscenario');
        }
        $mform->addElement('select', 'theme', get_string('theme', 'mod_aibranchedscenario'), $themes);
        $mform->setDefault('theme', self::default_for('theme', 'slate'));
        $mform->addHelpButton('theme', 'theme', 'mod_aibranchedscenario');

        $languages = [];
        foreach (schema::languages() as $language) {
            $languages[$language] = get_string(
                'language:' . strtolower(str_replace('-', '', $language)),
                'mod_aibranchedscenario'
            );
        }
        $mform->addElement('select', 'scenariolang', get_string('scenariolang', 'mod_aibranchedscenario'), $languages);
        $mform->setDefault('scenariolang', self::default_for('language', 'en-AU'));
        $mform->addHelpButton('scenariolang', 'scenariolang', 'mod_aibranchedscenario');

        $mform->addElement('selectyesno', 'showtimeline', get_string('showtimeline', 'mod_aibranchedscenario'));
        $mform->setDefault('showtimeline', self::default_for('showtimeline', 1));

        $mform->addElement('selectyesno', 'showmetrics', get_string('showmetrics', 'mod_aibranchedscenario'));
        $mform->setDefault('showmetrics', self::default_for('showmetrics', 1));
        $mform->addHelpButton('showmetrics', 'showmetrics', 'mod_aibranchedscenario');

        $mform->addElement('selectyesno', 'showdebrief', get_string('showdebrief', 'mod_aibranchedscenario'));
        $mform->setDefault('showdebrief', self::default_for('showdebrief', 1));

        $mform->addElement('selectyesno', 'enableimages', get_string('enableimages', 'mod_aibranchedscenario'));
        $mform->setDefault('enableimages', self::default_for('enableimages', 1));
        $mform->addHelpButton('enableimages', 'enableimages', 'mod_aibranchedscenario');

        $mform->addElement('selectyesno', 'enableaudio', get_string('enableaudio', 'mod_aibranchedscenario'));
        $mform->setDefault('enableaudio', self::default_for('enableaudio', 1));
        $mform->addHelpButton('enableaudio', 'enableaudio', 'mod_aibranchedscenario');

        $mform->addElement(
            'selectyesno',
            'requirelisten',
            get_string('requirelisten', 'mod_aibranchedscenario')
        );
        $mform->setDefault('requirelisten', self::default_for('requirelisten', 0));
        $mform->addHelpButton('requirelisten', 'requirelisten', 'mod_aibranchedscenario');
        $mform->hideIf('requirelisten', 'enableaudio', 'eq', 0);

        // Where a reading stops counting as good, and where it becomes a problem. A
        // de-escalation exercise and a sales conversation do not agree about what a
        // trust of 55 means, and these used to be two numbers inside the JavaScript.
        $mform->addElement(
            'text',
            'bandgreen',
            get_string('bandgreen', 'mod_aibranchedscenario'),
            ['size' => 4]
        );
        $mform->setType('bandgreen', PARAM_INT);
        $mform->setDefault('bandgreen', self::default_for('bandgreen', 67));
        $mform->addHelpButton('bandgreen', 'bandgreen', 'mod_aibranchedscenario');

        $mform->addElement(
            'text',
            'bandred',
            get_string('bandred', 'mod_aibranchedscenario'),
            ['size' => 4]
        );
        $mform->setType('bandred', PARAM_INT);
        $mform->setDefault('bandred', self::default_for('bandred', 34));
        $mform->addHelpButton('bandred', 'bandred', 'mod_aibranchedscenario');

        $mform->addElement('header', 'attempts', get_string('attemptsettings', 'mod_aibranchedscenario'));

        $attemptoptions = [0 => get_string('unlimited', 'mod_aibranchedscenario')];
        for ($i = 1; $i <= 10; $i++) {
            $attemptoptions[$i] = $i;
        }
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', 'mod_aibranchedscenario'), $attemptoptions);
        $mform->setDefault('maxattempts', self::default_for('maxattempts', 3));
        $mform->addHelpButton('maxattempts', 'maxattempts', 'mod_aibranchedscenario');

        $mform->addElement('selectyesno', 'allowreplay', get_string('allowreplay', 'mod_aibranchedscenario'));
        $mform->setDefault('allowreplay', self::default_for('allowreplay', 1));
        $mform->addHelpButton('allowreplay', 'allowreplay', 'mod_aibranchedscenario');

        $grademethods = [];
        foreach (schema::grademethods() as $method) {
            $grademethods[$method] = get_string('grademethod:' . $method, 'mod_aibranchedscenario');
        }
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'mod_aibranchedscenario'), $grademethods);
        $mform->setDefault('grademethod', self::default_for('grademethod', 'highest'));
        $mform->addHelpButton('grademethod', 'grademethod', 'mod_aibranchedscenario');

        $this->standard_grading_coursemodule_elements();

        // Grade to pass is core's element, so its default can only be set once core has
        // added it. A site that has never touched the setting gets the shipped value.
        if ($mform->elementExists('gradepass')) {
            $mform->setDefault('gradepass', self::default_for('gradepass', 100));
        }

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * The site's configured default for one activity setting, or the shipped value.
     *
     * Every default a teacher sees in this form is a site setting, so an administrator can
     * make new activities start the way their organisation wants them without anybody
     * editing code. The second argument is what this plugin ships with, used until the
     * setting is saved for the first time.
     *
     * Zero and '0' are legitimate saved values — unlimited attempts, a picker turned off —
     * so the check is for an unset setting rather than for emptiness.
     *
     * @param string $name Setting name below the defaults prefix.
     * @param mixed $shipped Value used when the site has never set one.
     * @return mixed
     */
    protected static function default_for(string $name, $shipped) {
        $value = get_config('mod_aibranchedscenario', 'default' . $name);
        return ($value === false || $value === null || $value === '') ? $shipped : $value;
    }

    /**
     * Add the activity specific completion rules.
     *
     * @return string[] Names of the added elements.
     */
    public function add_completion_rules() {
        $mform = $this->_form;

        $mform->addElement(
            'checkbox',
            'completionfinish',
            get_string('completiondetail:finish', 'mod_aibranchedscenario'),
            get_string('completionfinish', 'mod_aibranchedscenario')
        );
        $mform->setDefault('completionfinish', self::default_for('completionfinish', 1));

        $mform->addElement(
            'text',
            'completionminscore',
            get_string('completionminscore', 'mod_aibranchedscenario'),
            ['size' => 4]
        );
        $mform->setType('completionminscore', PARAM_INT);
        $mform->setDefault('completionminscore', self::default_for('completionminscore', 100));
        $mform->hideIf('completionminscore', 'completionfinish', 'notchecked');
        $mform->addHelpButton('completionminscore', 'completionminscore', 'mod_aibranchedscenario');

        return ['completionfinish', 'completionminscore'];
    }

    /**
     * Whether any activity specific completion rule is enabled.
     *
     * @param array $data Submitted form data.
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionfinish']);
    }

    /**
     * Validate the submitted data.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['completionfinish'])) {
            $score = (int)($data['completionminscore'] ?? 0);
            if ($score < 0 || $score > 100) {
                $errors['completionminscore'] = get_string('error:minscorerange', 'mod_aibranchedscenario');
            }
        }

        $green = (int)($data['bandgreen'] ?? 67);
        $red = (int)($data['bandred'] ?? 34);
        foreach (['bandgreen' => $green, 'bandred' => $red] as $field => $value) {
            if ($value < 0 || $value > 100) {
                $errors[$field] = get_string('error:bandrange', 'mod_aibranchedscenario');
            }
        }
        if (!isset($errors['bandgreen']) && !isset($errors['bandred']) && $green <= $red) {
            $errors['bandgreen'] = get_string('error:bandorder', 'mod_aibranchedscenario');
        }

        return $errors;
    }

    /**
     * Prepare the data shown in the form.
     *
     * @param array $defaultvalues Values to be shown.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        if (empty($defaultvalues['completionfinish'])) {
            $defaultvalues['completionminscore'] = 0;
        }
    }

    /**
     * Clean up the submitted data before it is saved.
     *
     * @param stdClass $data Submitted data.
     * @return void
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (!$autocompletion || empty($data->completionfinish)) {
                $data->completionfinish = 0;
                $data->completionminscore = 0;
            }
        }
    }
}
