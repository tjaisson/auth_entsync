<?php
use auth_entsync\init\console;
use auth_entsync\init\roles;
use auth_entsync\init\h5p;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once(__DIR__ . '/../ent_defs.php');


$help =
"Initialise une instance.
    
Options:
--diag       Affiche un diagnostic.
--only-ko       N'affiche que les erreurs.
--run=<tasks,...> | all
-h, --help            Affiche l'aide.
    
tasks :
 theme : active le thème acparis
    
    
";

list($options, $unrecognised) = cli_get_params(
    [
        'diag'  => false,
        'only-ko'  => false,
        'run'  => false,
        'ent'  => false,
        'lib' => false,
        'help'    => false,
    ],
    [
        'h' => 'help',
    ]
    );

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL.'  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($help);
    exit(0);
}

$aes_container = \auth_entsync\container::services();
$aes_console = new console(!!$options['only-ko']);

if ($options['diag']) {
    $aes_roles = new roles($aes_console, $aes_container);
    $aes_roles->check(!!$options['lib']);
} else if ($options['run']) {
    if (strtolower(trim($options['run'])) === 'all') {
        $actions = [
            'homepage' => true,
            'theme' => true,
            'unoconv' => true,
            'entsync' => true,
            'roles' => true,
            'entsync-defaults' => true,
        ];
    } else {
        $parts = explode(',', $options['run']);
        $actions = [];
        for ($i = 0; $i < count($parts); $i++) {
            $actions[strtolower(trim($parts[$i]))] = true;
        }
    }
    if (array_key_exists('homepage', $actions)) {
        aes_conf::set_homepage();
    }
    if (array_key_exists('theme', $actions)) {
        aes_conf::set_theme();
    }
    if (array_key_exists('unoconv', $actions)) {
        aes_conf::enable_unoconv();
    }
    if (array_key_exists('entsync', $actions)) {
        aes_conf::enable_entsync();
    }
    if (array_key_exists('roles', $actions)) {
        $aes_roles = new roles($aes_console, $aes_container);
        $aes_roles->fix(!!$options['lib']);
    }
    if (array_key_exists('entsync-defaults', $actions)) {
        $ent = $options['ent'];
        if($ent) {
            aes_conf::configure_entsync($ent);
        } else {
            aes_writeln_ko('Veuillez indiquer l\'ENT pour configurer \'entsync\'.');
        }
    }
    if (array_key_exists('mdl_h5p', $actions)) {
        $aes_h5p = new h5p($aes_console, $aes_container);
        $aes_h5p->fix('mdl_h5p');
    }
    if (array_key_exists('mod_hvp', $actions)) {
        $aes_h5p = new h5p($aes_console, $aes_container);
        $aes_h5p->fix('mod_hvp');
    }
}

exit(0);

function aes_writeln_ok($msg) {
    global $aes_only_ko;
    if (! $aes_only_ko)
        cli_writeln(cli_ansi_format('<colour:green>' . 'OK : ' . '<colour:normal>' . $msg));
}
function aes_writeln_ko($msg) {
    cli_writeln(cli_ansi_format('<colour:red>' . 'KO : ' . '<colour:normal>' . $msg));
}


// Conf
class aes_conf {
    public static function check_homepage() {
        global $CFG;
        if ($CFG->defaulthomepage == HOMEPAGE_SITE) {
            aes_writeln_ok('La page d\'accueil est réglée sur \'Site\'.');
        } else  {
            aes_writeln_ko('La page d\'accueil n\'est pas réglée sur \'Site\'.');
        }
    }
    public static function set_homepage() {
        aes_writeln_ok('Set homepage as default');
        set_config('defaulthomepage', HOMEPAGE_SITE);
        set_config('frontpage', '');
        set_config('frontpageloggedin', '7,2');
        set_config('maxcategorydepth', '1');
    }
    public static function set_theme() {
        aes_writeln_ok('Configure le thème sur \'acparis\'.');
        $themename = core_useragent::get_device_type_cfg_var_name(core_useragent::DEVICETYPE_LEGACY);
        set_config($themename, null);
        $themename = core_useragent::get_device_type_cfg_var_name(core_useragent::DEVICETYPE_MOBILE);
        set_config($themename, null);
        $themename = core_useragent::get_device_type_cfg_var_name(core_useragent::DEVICETYPE_TABLET);
        set_config($themename, null);
        $theme = theme_config::load('acparis');
        $themename = core_useragent::get_device_type_cfg_var_name(core_useragent::DEVICETYPE_DEFAULT);
        set_config($themename, $theme->name);
    }
    public static function check_unoconv() {
        $type = 'fileconverter';
        $plugin ='unoconv';
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type($type);
        if (! array_key_exists($plugin, $plugins)) {
            aes_writeln_ko('Le plugin \'unoconv\' est absent.');
        } else {
            $plugin = $plugins[$plugin];
            if ($plugin->is_enabled()) {
                aes_writeln_ok('Le plugin \'unoconv\' est déjà activé.');
            } else {
                aes_writeln_ko('Le plugin \'unoconv\' n\'est pas activé.');
            }
        }
    }
    public static function enable_unoconv() {
        $type = 'fileconverter';
        $plugin ='unoconv';
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type($type);
        if (! array_key_exists($plugin, $plugins)) {
            aes_writeln_ko('Le plugin \'unoconv\' est absent.');
        } else {
            $plugin = $plugins[$plugin];
            if ($plugin->is_enabled()) {
                aes_writeln_ok('Le plugin \'unoconv\' est déjà activé.');
            } else {
                aes_writeln_ok('Active le plugin \'unoconv\'.');
                $plugin->set_enabled(true);
            }
        }
    }

    public static function enable_entsync() {
        if (!exists_auth_plugin('entsync')) {
            aes_writeln_ko('Le plugin \'entsync\' est absent.');
        } else {
            if (is_enabled_auth('entsync')) {
                aes_writeln_ok('Le plugin \'entsync\' est déjà activé.');
            } else {
                aes_writeln_ok('Active le plugin \'entsync\'.');
                $authsenabled = get_enabled_auth_plugins(true); // fix the list of enabled auths
                $authsenabled[] = 'entsync';
                $authsenabled = array_unique($authsenabled);
                set_config('auth', implode(',', $authsenabled));
                get_enabled_auth_plugins(true);
                core_plugin_manager::reset_caches();
            }
        }
    }

    public static function configure_entsync($e) {
        global $DB;
        $ent = auth_entsync_ent_base::get_ent($e);
        if ($ent) {
            if ($ent->is_enabled()) {
                aes_writeln_ok('L\'ENT ' . $ent->nomlong . ' est déjà activé.');
            } else {
                aes_writeln_ok('Activation de l\'ENT ' . $ent->nomlong . '.');
                auth_entsync_ent_base::enable_ent($ent->get_code());
            }
        } else {
            aes_writeln_ko('L\'ENT ' . $e .  ' n\'existe pas.');
        }
        $role_ens = get_config('auth_entsync', 'role_ens');
        if (false === $role_ens) $role_ens = 0;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'catcreator']);
        if ($roleid) {
            if ($roleid == $role_ens) {
                aes_writeln_ok('Role système des enseignants est déjà catcreator.');
            } else {
                aes_writeln_ok('Paramètrage du rôle système des enseignants sur catcreator.');
                set_config('role_ens', $roleid, 'auth_entsync');
            }
        } else {
            aes_writeln_ko('Le rôle catcreator n\'existe pas. Impossible de configurer le rôle système des enseignants.');
        }
    }
}
