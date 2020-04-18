<?php
namespace auth_entsync\farm;

defined('MOODLE_INTERNAL') || die;

class instances {
    protected $rne_index;
    protected $instances_index;
    protected $indexesBuilt = false;
    public static $conf;
    public function __construct() {
    }
    public function get_instances($filters = [], $sort = '', $order = 'ASC', $skip = 0, $limit = 0) {
        return instance::get_records($filters, $sort, $order, $skip, $limit);
    }
    public function get_instance($filters = []) {
        return instance::get_record($filters);
    }
    public function get_instancesForRnes($rnes) {
        $rne_index = $this->rnesIndex();
        $insts = [];
        foreach ($rnes as $rne) {
            if ($i = @$rne_index[$rne]) {
                foreach ($i as $r) $insts[] = $r;
            }
        }
        $insts = \array_unique($insts);
        $instances_index = $this->instancesIndex();
        $rep = [];
        foreach ($insts as $r) $rep[$r] = $instances_index[$r];
        return $rep;
    }
    protected function ensureIndexes() {
        if ($this->indexesBuilt) return true;
        $cache = \cache::make('auth_entsync', 'farm');
        if ((false === ($rne_index = $cache->get('rne_index'))) ||
            (false === ($instances_index = $cache->get('instances_index')))) {
                $rne_index = [];
                $instances_index = [];
                foreach ($this->get_instances() as $inst) {
                    $dir = $inst->get('dir');
                    $name = $inst->get('name');
                    $rnes = $inst->rnes();
                    $instances_index[$dir] = ['name' => $name, 'rnes' => $rnes];
                    foreach ($rnes as $rne) {
                        if (\array_key_exists($rne, $rne_index)) {
                            $rne_index[$rne][] = $dir;
                        } else {
                            $rne_index[$rne] = [ $dir ];
                        }
                    }
                }
                $cache->set('rne_index', $rne_index);
                $cache->set('instances_index', $instances_index);
            }
            $this->rne_index = $rne_index;
            $this->instances_index = $instances_index;
            $this->indexesBuilt = true;
            return true;
    }
    public function rnesIndex() {
        $this->ensureIndexes();
        return $this->rne_index;
    }
    public function instancesIndex() {
        $this->ensureIndexes();
        return $this->instances_index;
    }
    public function instance($id, $record = null) {
        return new instance($id, $record);
    }
    public function instanceClass() {
        return instance::class;
    }
    public static function invalCache() {
        $cache = \cache::make('auth_entsync', 'farm');
        $cache->delete('instances_json');
        $cache->delete('instances_index');
        $cache->delete('rne_index');
    }
    public function instances_json($admin = false) {
        if ($admin) {
            $lst = [];
            foreach ($this->get_instances([], 'name') as $inst) {
                $lst[] = [
                    'id' => $inst->get('id'),
                    'dir' => $inst->get('dir'),
                    'name' => $inst->get('name'),
                    'rne' => $inst->get('rne')];
            }
            return json_encode($lst);
        } else {
            $cache = \cache::make('auth_entsync', 'farm');
            if (!false === ($json = $cache->get('instances_json'))) {
                return $json;
            }
            $lst = [];
            foreach ($this->get_instances([], 'name') as $inst) {
                if ($inst->get('rne') !== '00') {
                    $lst[] = ['dir' => $inst->get('dir'), 'name' => $inst->get('name')];
                }
            }
            $json = json_encode($lst);
            $cache->set('instances_json', $json);
            return $json;
        }
    }
}
