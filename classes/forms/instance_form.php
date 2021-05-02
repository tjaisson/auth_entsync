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
 *
 * @package    tool_entsync
 * @copyright 2016 Thomas Jaisson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_entsync\forms;
defined('MOODLE_INTERNAL') || die;
class instance_form {
    public function __construct($instances) {
        _instance_form::setClass($instances->instanceClass());
    }
    public function createForm($action = null, $customdata = null) {
        return new _instance_form($action, $customdata);
    }
}

class _instance_form extends \core\form\persistent {
    /** @var string Persistent class name. */
    protected static $persistentclass = 'auth_entsync\\farm\\instance';
    public static function setClass($class) {
        self::$persistentclass = $class;
    }
    public function definition() {
        $mform = $this->_form;
        
        // Rne.
        $mform->addElement('text', 'dir', 'Répertoire de l\'instance');
        
        // Name.
        $mform->addElement('text', 'name', 'Nom de l\'instance');
        
        // Other Rne.
        $mform->addElement('text', 'rne', 'Le ou les RNE séparés par \',\'');
        
        $this->add_action_buttons();
    }
}

