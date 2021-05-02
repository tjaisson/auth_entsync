<?php
namespace auth_entsync\farm;

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
    public function rnes() {
        return \array_map('\trim', \explode(',', $this->raw_get('rne')));
    }
    protected function after_create() {
        instances::invalCache();
    }
    protected function after_delete($result) {
        instances::invalCache();
    }
    protected function after_update($result) {
        instances::invalCache();
    }
}
