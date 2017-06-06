<!DOCTYPE html>
<html lang="fr">
<head>
<title>Cas Test</title>
<meta charset="utf-8">

<style>
</style>
</head>

<?php
//Utéf-8
 $uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
 $MyUrl = "http://$_SERVER[HTTP_HOST]$uri_parts[0]";
 
 $seveurs = [
 		'lilie' => 'https://ent.iledefrance.fr/connexion/',
 		'educ' => 'https://educhorus.enteduc.fr/0750677D/cas/',
 		'pcn' => 'https://www.parisclassenumerique.fr/connexion/',
 		'ng' => 'https://ent-ng.paris.fr/cas/',
 		'ngcrif' => 'https://prodng.ent.iledefrance.fr/cas/',
        'forngcrif' => 'https://formation.ent.iledefrance.fr/cas/',
 		'envole' => 'https://extranet.lyc-elisa-lemonnier.ac-paris.fr:8443/'
 ];
 
 
 function curl_download($Url){
 
 	// is cURL installed yet?
 	if (!function_exists('curl_init')){
 		die('Sorry cURL is not installed!');
 	}
 
 	// OK cool - then let's create a new cURL resource handle
 	$ch = curl_init();
 
 	// Now set some options (most are optional)
 
 	// Set URL to download
 	curl_setopt($ch, CURLOPT_URL, $Url);
 
 	// Set a referer
 	curl_setopt($ch, CURLOPT_REFERER, "http://www.example.org/yay.htm");
 
 	// User agent
 	curl_setopt($ch, CURLOPT_USERAGENT, "MozillaXYZ/1.0");
 
 	// Include header in result? (0 = yes, 1 = no)
 	curl_setopt($ch, CURLOPT_HEADER, 0);
 
 	// Should cURL return or print out the data? (true = return, false = print)
 	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 
 	// Timeout in seconds
 	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
 	
 	
 	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
 	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
 
 	// Download the given URL, and return output
 	$output = curl_exec($ch);
 
 	// Close the cURL resource, and free system resources
 	curl_close($ch);
 
 	return $output;
 }
 
?>


<body>
<h1>Test Cas</h1>

<?php foreach ($seveurs as $key => $value) { ?>
<p>
<a href="<?php echo $value . 'login?service=' . urlencode($MyUrl .  '?srv=' .$key); ?>">Authentification <?php echo $key; ?></a> -
<a href="<?php echo $value . 'login?service=' . urlencode($MyUrl .  '?srv=' .$key .'&gateway=true').'&gateway=true'; ?>">Gateway</a>
</p>
<?php } ?>

<p><a href="<?php echo $MyUrl; ?>">Reset</a></p>
<?php

if( isset($_GET['gateway']) && ($_GET['gateway'] == 'true'))
{
	$gw = true;
} else {
	$gw = false;
}

if( isset($_GET['ticket']) && ($_GET['ticket'] != '') ) {
	$ticket = $_GET['ticket'];
	if( isset($_GET['srv']) ) {
		$key = $_GET['srv'];
		if($gw) {
			$getUrl = $seveurs[$key] . 'serviceValidate?service=' .   urlencode($MyUrl .  '?srv=' . $key .'&gateway=true') . '&ticket=' . $ticket;
			echo "<hr /><h1>Cas gateway - Résultat :</h1>";
		} else {
			$getUrl = $seveurs[$key] . 'serviceValidate?service=' .   urlencode($MyUrl .  '?srv=' . $key ) . '&ticket=' . $ticket;
			echo "<hr /><h1>Résultat cas :</h1>";
		}
		$response = curl_download($getUrl);
		echo "<h2>Ticket :</h2>";
		echo "<pre>" . $ticket . "</pre>";
		echo "<h2>Réponse :</h2>";
		echo "<pre>" . htmlspecialchars($response) .  "</pre>";
	} else {
		echo "<hr /><h1>Problème</h1>";
	}
} else {
	$ticket = null;
	if($gw) {
		echo "<hr /><h1>Cas gateway - Résultat :</h1>";
		echo "<h2>Pas de ticket</h2>";
	}
}

?>


</body>
</html>