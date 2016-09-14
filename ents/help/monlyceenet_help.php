<?php defined('MOODLE_INTERNAL') || die; ?>
<p>La mise en place du connecteur avec l'ENT Lilie nécessite la création d'une ressource côté ENT.
Cela se fait dans la <b>console d'administration</b>&nbsp;:</p>
<p><img style = "border: 1px solid;" src='./ents/help/monlyceenet2.jpg' /></p>
<ol>
<li>Déplier "Services" puis "Ressources" et cliquer sur "Ressources numériques",</li>
<li>Renseigner le formulaire "Création d'une ressource"&nbsp;:
<ul>
<li>Nom&nbsp;: Moodle PAM</li>
<li>URL&nbsp;: <?php echo $this->get_connector_url(); ?></li>
<li>Description&nbsp;: Plateforme Moodle de l'établissement</li>
<li>Logo&nbsp;: Il est indispensable d'attribuer un logo pour que la ressource fonctionne.
Il est possible d'utiliser le logo suivant&nbsp;: <img style = "border: 1px solid; max-width: 100px" src='./ents/help/moodle-ln.png' /> (click droit + enregistrer l'image).</li>

</ul> 
</li>
<li>Pensez à cocher la case d’acceptation de la convention,</li>
<li>Cliquer sur "Enregistrer". Confirmer le premier message d'avertissement puis patienter jusqu'au message de confirmation.</li>
</ol>