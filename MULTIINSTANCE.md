# Config multi instance
=============================


## Notes liées à wamp
wampserver definit par defaut un virtualhost sur son www. Je ne sais pas pourquoi.
Du coup les rewrite définis au niveau serveur ne fonctionnent pas.

Pour qu'une clause RewriteRule substitue vers le système de fichier, il faut que le début
de la substitution existe dans le système de fichier et commence par 'C:\\foo\\bar/suite'
## httpd.conf
    RewriteEngine On  
    RewriteRule "^/m(\d)(\d)\.inst\d\d(.*)$"  "c:\\Users\\Tom/moodledev/www/moodle-$1.$2$3" [L]


## Moodle config file
    <?php  // Moodle configuration file

    unset($CFG);
    global $CFG;
    $CFG = new stdClass();

    $pamroot = 'http://localhost';
    $pamdataroot = 'C:\\Users\\Tom\\moodledev\\data\\';
    list(,$repertoire) = explode('/',$_SERVER['REQUEST_URI']);

    $CFG->dbtype    = 'mysqli';
    $CFG->dblibrary = 'native';
    $CFG->dbhost    = 'localhost';
    $CFG->dbname    = $repertoire;
    $CFG->dbuser    = 'root';
    $CFG->dbpass    = '';;
    $CFG->prefix    = 'mdl_';
    $CFG->dboptions = array (
        'dbpersist' => 0,
        'dbport' => '',
        'dbsocket' => '',
        'dbcollation' => 'utf8mb4_unicode_ci',
    );

    $CFG->wwwroot   = $pamroot . '/' . $repertoire;
    $CFG->dataroot  = $pamdataroot . $repertoire;
    $CFG->admin     = 'admin';

    $CFG->langlocalroot    = __DIR__ . '\\lang';
    $CFG->langotherroot    = __DIR__ . '\\lang';
    $CFG->skiplangupgrade  = true;
    $CFG->lang = 'fr';

    $CFG->forced_plugin_settings = [];
    $CFG->forced_plugin_settings['auth_entsync'] = [
        'gw' => 'm33.inst01',
        'inst' => $repertoire,
        'pamroot' => $pamroot
    ];

    unset($repertoire, $pamdataroot, $pamroot);

    $CFG->directorypermissions = 0777;

    require_once(__DIR__ . '/lib/setup.php');

    // There is no php closing tag in this file,
    // it is intentional because it prevents trailing whitespace problems!
