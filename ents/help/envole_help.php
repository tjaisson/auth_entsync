<?php defined('MOODLE_INTERNAL') || die(); ?>
<p>La mise en place du connecteur avec l'extranet Envole ne nécessite pas de paramétrage particulier côté Envole. Il suffit d'activer
le connecteur Scribe/Envole dans Moodle, de renseigner le nom domaine de votre extranet Envole, puis d'importer les utilisateurs dans "Importation des utilisateurs".</p>
<p>Les élèves et les enseignants accèderont ainsi à Moodle avec leurs codes d'accès Scribe.</p>
<p>Vous pouvez diffuser l'URL du connecteur direct&nbsp;:</p>
<p style="text-align: center;"><?php echo $this->get_connector_url(); ?></p>
