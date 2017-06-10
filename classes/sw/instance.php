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

class instance extends \core\persistent {
    const TABLE = 'auth_entsync_instances';

    /**
     * Define properties.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'dir' => [
                'type' => PARAM_TEXT,
            ],
            'rne' => [
                'type' => PARAM_TEXT,
            ],
            'name' => [
                'type' => PARAM_TEXT,
            ],
        ];
    }
    
    public function has_rne($rnes) {
        $instrnes = array_map('trim', explode(',', $this->raw_get('rne')));
        $i = array_uintersect($instrnes, $rnes, "strcasecmp");
        return (count($i) > 0);
    }
}

