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

namespace auth_entsync\sw;
defined('MOODLE_INTERNAL') || die;

class instance_form extends \core\form\persistent {
    /** @var string Persistent class name. */
    protected static $persistentclass = 'auth_entsync\\sw\\instance';
    
    public function definition() {
        $mform = $this->_form;
        
        // Rne.
        $mform->addElement('text', 'rne', 'RNE de l\'instance');
        
        // Name.
        $mform->addElement('text', 'name', 'Nom de l\'instance');
        
        // Other Rne.
        $mform->addElement('text', 'otherrne', 'Autres RNE associés à cette instance');
        
        $this->add_action_buttons();
    }
}

