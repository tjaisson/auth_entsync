<?php
namespace auth_entsync\init;
use auth_entsync\init\console;
use mod_hvp\framework as hvp_framework;
use core_h5p\factory as h5p_factory;
use auth_entsync\container;
use auth_entsync\conf;

defined('MOODLE_INTERNAL') || die();

class h5p {
    protected console $console;
    protected conf $conf;
    protected $contenttypes = false;

    public function __construct(console $console, container $container) {
        $this->console = $console;
        $this->conf = $container->query('conf');
    }
    public function check($lib) {
        $this->start_section($lib);
        if ($lib === 'mod_hvp') {
            $this->check_latest_content_types(new mod_hvp_installer());
        } else if ($lib === 'mdl_h5p') {
            $this->check_latest_content_types(new mdl_h5p_installer());
        } else {
            $this->console->writeln('librairie devrait être mod_hvp ou mdl_h5p');    
        }
    }
    public function init($lib) {
        $this->fix($lib);
    }
    public function fix($lib) {
        $this->start_section($lib);
        if ($lib === 'mod_hvp') {
            $this->install_latest_content_types(new mod_hvp_installer());
        } else if ($lib === 'mdl_h5p') {
            $this->install_latest_content_types(new mdl_h5p_installer());
        } else {
            $this->console->writeln('librairie devrait être mod_hvp ou mdl_h5p');    
        }
    }

    /**
     * @param h5p_installer_base $installer
     */
    protected function install_latest_content_types($installer) {
        $contenttypes = $this->get_latest_content_types();
        foreach ($contenttypes->contentTypes as $type) {
            // Don't fetch content types that require a higher H5P core API version.
            if (!$installer->has_required_core_api($type->coreApiVersionNeeded)) {
                $this->console->write_fix('Core H5P API version in ' . $installer->name() .  ' to old  to install : ' . $type->id, false);
                continue;
            }

            $library = [
                'machineName' => $type->id,
                'majorVersion' => $type->version->major,
                'minorVersion' => $type->version->minor,
                'patchVersion' => $type->version->patch,
                'file' => $type->file,
            ];

            if ($installer->should_install($library)) {
                $success = $installer->install($this->conf->initdir() . '/h5p/' . $library['file']);
                if ($success) {
                    $this->console->write_fix('installed in ' . $installer->name() . ' : ' . $library['machineName']);
                } else {
                    $this->console->write_fix('error while installing in ' . $installer->name() . ' : ' . $library['machineName'], false);
                }
            } else {
                $this->console->write_fix('No need to install in ' . $installer->name() . ' : ' . $library['machineName']);
            }
        }
    }

    /**
     * @param h5p_installer_base $installer
     */
    protected function check_latest_content_types($installer) {
        $contenttypes = $this->get_latest_content_types();
        foreach ($contenttypes->contentTypes as $type) {
            // Don't fetch content types that require a higher H5P core API version.
            if (!$installer->has_required_core_api($type->coreApiVersionNeeded)) {
                $this->console->write_check('Core H5P API version in ' . $installer->name() .  ' to old  to install : ' . $type->id, false);
                continue;
            }

            $library = [
                'machineName' => $type->id,
                'majorVersion' => $type->version->major,
                'minorVersion' => $type->version->minor,
                'patchVersion' => $type->version->patch,
                'file' => $type->file,
            ];

            if ($installer->should_install($library)) {
                $this->console->write_fix('should install in ' . $installer->name() . ' : ' . $library['machineName'], false);
            } else {
                $this->console->write_fix('No need to install in ' . $installer->name() . ' : ' . $library['machineName']);
            }
        }
    }

    protected function get_latest_content_types() {
        if (false === $this->contenttypes) {
            $path = $this->conf->initdir() . '/h5p/registry.json';
            $content = \file_get_contents($path);
            if (empty($content)) throw new \Exception('Fichier registre introuvable : ' . $path);
            $this->contenttypes = json_decode($content);
            $this->contenttypes->error = '';
        }
        return $this->contenttypes;
    }

    protected function start_section($lib) {
        $this->console->start_section('Contenus h5p suppémentaires dans ' . $lib);
    }

}

abstract class h5p_installer_base {
    protected static $lock = false;
    /** @var \H5PFrameworkInterface $framework */
    protected $framework;
    /** @var \H5PCore $core */
    protected $core;
    /** @var \H5PValidator $validator */
    protected $validator;
    /** @var \H5PStorage $storage */
    protected $storage;
    protected $coreApi;
    protected $tempdir;
    public function __construct() {
    }

    public function get_tempdir() {
        if (empty($this->tempdir)) {
            $this->tempdir = \make_request_directory();
        }
        return $this->tempdir;
    }
    /** @see \core_h5p\core (in h5p/classes/core.php) */
    public function has_required_core_api($coreapi): bool {
        if (isset($coreapi) && !empty($coreapi)) {
            if (($coreapi->major > $this->coreApi['majorVersion']) ||
                (($coreapi->major == $this->coreApi['majorVersion']) && ($coreapi->minor > $this->coreApi['minorVersion']))) {
                return false;
            }
        }
        return true;
    }

    public function should_install($library) {
        $shouldinstall = true;
        if ($this->framework->getLibraryId($library['machineName'], $library['majorVersion'], $library['minorVersion'])) {
            if (!$this->framework->isPatchedLibrary($library)) {
                $shouldinstall = false;
            }
        }
        return $shouldinstall;
    }

    public function install($file) {
        $path = $this->get_tempdir() . \uniqid('/hvp-');
        $path = $this->framework->getUploadedH5pFolderPath($path);
        $path .= '.h5p';
        $path = $this->framework->getUploadedH5pPath($path);
        copy($file, $path);

        if(!$this->validator->isValidPackage(true)) {
            return false;
        } else {
            $this->storage->savePackage(null, null, true);
            return true;
        }
    }

    public abstract function name();
}

/**
 * Installeur de librairie pour H5P natif moodle
 */
class mdl_h5p_installer extends h5p_installer_base {
    public function __construct() {
        if (self::$lock) throw new \Exception('h5p et hvp ne sont pas compatibles');
        self::$lock = true;
        parent::__construct();
        $factory = new h5p_factory();
        $this->framework = $factory->get_framework();
        $this->core = $factory->get_core();
        $this->validator = $factory->get_validator();
        $this->storage = $factory->get_storage();
        $this->coreApi = $this->core::$coreApi;
        $this->core->mayUpdateLibraries(true);
    }
    public function name() {
        return "mdl_h5p"        ;
    }
}

/**
 * Installeur de librairie pour H5P du plugin mod_hvp
 */
class mod_hvp_installer extends h5p_installer_base {
    public function __construct() {
        if (self::$lock) throw new \Exception('h5p et hvp ne sont pas compatibles');
        self::$lock = true;
        parent::__construct();
        $this->framework = hvp_framework::instance('interface');
        $this->core = hvp_framework::instance();
        $this->validator = hvp_framework::instance('validator');
        $this->storage = hvp_framework::instance('storage');
        $this->coreApi = $this->core::$coreApi;
        $this->core->mayUpdateLibraries(true);
    }
    public function name() {
        return "mod_hvp"        ;
    }
}