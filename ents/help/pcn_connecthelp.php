<?php defined('MOODLE_INTERNAL') || die; ?>
<p>La mise en place du connecteur avec l'ENT Paris Classe Numérique ne nécessite pas de paramétrage particulier côté ENT. Il suffit d'activer
le connecteur PCN dans Moodle puis d'importer les utilisateurs.</p>
<p>Vous pouvez créer un signet dans PCN qui pointe vers l'URL du connecteur :</p>
<p style="text-align: center;"><?php echo $this->get_connector_url(); ?></p>

<?php if(!$this->is_enabled()) { ?>
<p><b>Note</b>&nbsp;: le connecteur n'est actuellement pas activé.</p>
<?php } ?>