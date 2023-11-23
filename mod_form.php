<?php //$Id: mod_form.php,v 1.3 2008/08/10 08:05:15 mudrd8mz Exp $

/**
 * This file defines de main exagames configuration form
 * It uses the standard core Moodle (>1.8) formslib. For
 * more info about them, please visit:
 *
 * http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * The form must provide support for, at least these fields:
 *   - name: text element of 64cc max
 *
 * Also, it's usual to use these fields:
 *   - intro: one htmlarea element to describe the activity
 *            (will be showed in the list of activities of
 *             exagames type (index.php) and in the header
 *             of the exagames main page (view.php).
 *   - introformat: The format used to write the contents
 *             of the intro field. It automatically defaults
 *             to HTML when the htmleditor is used and can be
 *             manually selected if the htmleditor is not used
 *             (standard formats are: MOODLE, HTML, PLAIN, MARKDOWN)
 *             See lib/weblib.php Constants and the format_text()
 *             function for more info
 */

require_once('moodleform_mod.php');

class mod_exameta_mod_form extends moodleform_mod
{       
/**
* List of modform features
*/
    protected $_features;

    function definition()
    {

        global $COURSE, $CFG, $DB, $OUTPUT, $PAGE, $USER;
        $mform =& $this->_form;
//-------------------------------------------------------------------------------
        /// Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));
        /// Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('metaTitle', 'exameta'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements(null);
        // If the 'show description' feature is enabled, this checkbox appears below the intro.
        // We want to hide that when using the singleactivity course format because it is confusing.
        $mform->addElement('hidden', 'showdescription', 1);
        $mform->setType('showdescription', PARAM_INT);
//-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();

    }

    protected function init_features() {
        global $CFG;

        $this->_features = new stdClass();
        $this->_features->showdescription = plugin_supports('mod', $this->_modname, FEATURE_SHOW_DESCRIPTION, false);
     }
}

?>

<script src="https://code.jquery.com/jquery-3.6.0.js"
                integrity="sha256-H+K7U5CnXl1h5ywQfKtSj8PCmoN9aaq30gDh27Xc0jk=" crossorigin="anonymous"></script>
<script>

</script>