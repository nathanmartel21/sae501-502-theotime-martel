<?php
session_start(); # Pour démarrer la session
if (!isset($_SESSION['utilisateur'])) {
	#Si l'utilisateur n'est pas connecté, on vers la page de connexion
	header("Location: /Connexion");
	exit();
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>SAE501-502-THEOTIME-MARTEL</title>
	<meta charset="utf-8">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
	<!-- Pour avoir bootstrap version 4.5.2 : https://getbootstrap.com/docs/4.5/getting-started/introduction/-->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</head>
<body class="bg-light">
<?php include('/home/includes/header.php'); ?>
<?php
$nom_utilisateur = $_SESSION['utilisateur']; #Pour récupérer le nom d'utilisateur depuis la session
#echo "nom_utilisateur";
#echo "nom_utilisateur";
#echo "test";
# On fait la connexion à la base de données
include('/home/Pages/configBDD/config.php');
#echo "test";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
	$comparaison1 = $_POST["comparaison1"];
	$comparaison2 = $_POST["comparaison2"];
	#echo "$comparaison1 $comparaison2";
	#echo "test";

	if (!empty($comparaison1) && !empty($comparaison2)) {
	# On récupère l'ID de l'utilisateur avec une Requête SQL
		$query_utilisateur = "SELECT id FROM Utilisateur WHERE nom_utilisateur = ?";
		$stmt = $connexion->prepare($query_utilisateur);
		$stmt->bind_param("s", $nom_utilisateur);
		$stmt->execute();
		$result_utilisateur = $stmt->get_result();
		$ligne_utilisateur_table = $result_utilisateur->fetch_assoc();
		$utilisateur_id = $ligne_utilisateur_table['id']; # Là on a l'ID de l'utilisateur
	} else {
		echo "L'utilisateur n'a pas été trouvé.";
	}
	$stmt->close();
	$sql = "INSERT INTO Historique (utilisateur_id, comparaison1, comparaison2, date) VALUES (?, ?, ?, NOW())";
	$stmt = $connexion->prepare($sql);
	$stmt->bind_param("iss", $utilisateur_id, $comparaison1, $comparaison2);
	$stmt->execute();
	$stmt->close();
	#echo "$comparaison1";
	#echo "$comparaison2";
	#$connexion->close();

	#Requête API MediaWiki pour les deux entités. On récupère les données dans les variables au format json (elle utilise json_decode)
	$data1 = recupWikiData($comparaison1);
	$data2 = recupWikiData($comparaison2);

	$datainfo1 = recupWikiDataInfo($comparaison1);
	$datainfo2 = recupWikiDataInfo($comparaison2);

	#echo $data1;
	#echo $data2;
	#Infobox entière, manque plus qu'à les parser pour avoir les données qu'on souhaite

	$infobox1 = "";
	$infobox2 = "";

	$info1 = "";
	$info2 = "";

	#--------------------------ATTRIBUTS A CRÉER-----------------------------#

	#Données qu'on récupère (variables des infoboxes) : 
	#$nomComplet1 = "";
	#$nomComplet2 = "";
	#$nom1 = "";
	#$nom2 = "";

	#......

	#------------------------------------------------------------------------#

	#Ensuite on extrait les données des infoboxes

	if (isset($data1['query']['pages'])) { #On vérifie d'abord que la requête à abouti et que la réponse contient le mot clef pages dans le tableau query pour la prmeière entité qu'on veut comparer.
		#En gros, MediaWiki répond avec un tableau et query contient les résultats de la requête vers l'API et dans ces résultats il y a "pages". Et si la page n'existe pas (celle de Wikipédia), les variables 
		#sont toujours là mais elles sont juste vides. On a vu ça avec : https://www.mediawiki.org/wiki/API:Main_page/fr et surtout : https://www.mediawiki.org/wiki/API:Query#Response, voir Example 1 : Specifying pages dans Response.
		$temp = reset($data1['query']['pages']); #On met la variable "temp" en index 1 (premier élément) du tableau, une seule page Wikipédia en gros, simplicité
		#echo "$temp";
            	$infobox1 = $temp['revisions'][0]['*']; #Contient donc les informations entières de l'infobox pour la première comparaison
		#echo "$infobox1";
		
	} else {
		echo "Aucune infobox trouvée pour la page $comparaison1";
	}
	# On fait pareil pour la deuxième comparaison/entité
	if (isset($data2['query']['pages'])) {
		$temp = reset($data2['query']['pages']);
		$infobox2 = $temp['revisions'][0]['*'];
		#echo "testtt";
	} else {

		echo "Aucune infobox trouvée pour la page $comparaison2";
	}

	#Pour les infos de la page : 
	

	if (isset($datainfo1['query']['pages'])) {
		$page_info1 = reset($datainfo1['query']['pages']); #Comme d'hab, on prend la première page (il n'y en a qu'une normalement)

		$page_long1 = $page_info1['length'];
		#$page_protection1 = $page_info1['protection'];
		$page_modif1 = $page_info1['touched'];
		$page_watchers1 = $page_info1['watchers'];
		$page_url1 = $page_info1['fullurl'];
	}


	if (isset($datainfo2['query']['pages'])) { #Pareil
		$page_info2 = reset($datainfo2['query']['pages']);

		$page_long2 = $page_info2['length'];
		#$page_protection2 = $page_info2['protection'];
		$page_modif2 = $page_info2['touched'];		
		$page_watchers2 = $page_info2['watchers'];
		$page_url2 = $page_info2['fullurl']; 
	}

       	#echo "Watchers : $page_watchers1";

	?>

	<div class="container mt-5">

	<h2><u><?php echo $comparaison1; ?></u> VS  <u><?php echo $comparaison2; ?></u> :</h2> <!--A changer, trouver un moyen, horrible -->

	<?php

	#------------------------------------PARSEMENT DES INFOBOX------------------------------------#

	#A cet instant, toutes les données des infoboxes sont stockés dans la variable infobox1 et infobox2, on utilise maintenant des REGEX pour les parser et ainsi avoir les attributs qu'on veut
	#--> Regex faites dans un tableau ci-dessous	

	#------------------------------------AFFICHAGE TABLEAU ATTRIBUTS-------------------------------#

	#Affiche l'attributs des deux entités s'ils ont cette information, sinon ça ne l'affiche pas. Affichement sous forme de tableau

	?>
		<br/><br/>
		<table class="table table-bordered table-responsive"><thead><tr><th>Attributs</th><th><?php echo $comparaison1; ?></th><th><?php echo $comparaison2;?></th></tr></thead><tbody>
		<tr><td>URL</td><td><a href='<?php echo $page_url1; ?>'><?php echo $page_url1;?> </a></td><td><a href='<?php echo $page_url2; ?>'><?php echo $page_url2;?></a></td></tr>
		<tr><td>Longueur de la page</td><td><?php echo $page_long1; ?> octets</td><td><?php echo $page_long2; ?> octets</td></tr>
		<tr><td>Dernière modification</td><td><?php echo $page_modif1; ?></td><td><?php echo $page_modif2; ?> </td></tr>
		<tr><td>Nombre de favoris</td><td><?php echo $page_watchers1; ?></td><td><?php echo $page_watchers2; ?></td></tr>
	<?php

	#Tableu qu'on va parcourir pour chaque nom on a une regex
	#Attributs des infobox les plus présentes 

	$nom_attributs = array(
    'nom complet', 'nom', 'surnoms', 'date de fondation', 'équipement sportif', 'status professionnel', 'siège', 'propriétaire', 'président', 'entraineur', 'joueur le plus capé', 'meilleur buteur',
    'championnat actuel', 'site web', 'palmarès national', 'palmarès international', 'description', 'date de naissance', 'date de décès', 'nationalité', 'lieu de naissance', 'lieu de décès',
    'profession', 'parti politique', 'langue', 'religion', 'éducation', 'références', 'biographie', 'genre', 'discographie', 'filmographie', 'prix et distinctions', 'famille', 'taille', 'poids',
    'affiliation militaire', 'date de fondation', 'siège social', 'coordonnées géographiques', 'statut', 'membres fondateurs', 'date de création', 'date de dissolution', 'slogan', 'durée du mandat',
    'titre', 'titre honorifique', 'prédécesseur', 'successeur', 'structure organisationnelle', 'budget annuel', 'principales réalisations', 'position politique', 'affiliation syndicale', 'notation',
    'chaîne YouTube'
);
	$tab = array();

	foreach ($nom_attributs as $nom_attribut) {
    		$pattern = '/\| ' . $nom_attribut . ' \s+=\s+(.*)\n/i';
    		$tab[$nom_attribut] = $pattern;
	}

	foreach ($tab as $temp => $garde_regex){ #temp va être temporaire donc l'attribut qu'on va récupérer pour les deux comparaisons ex : nom, surnoms, date, ...
		if (preg_match($garde_regex, $infobox1, $matches1) && preg_match($garde_regex, $infobox2, $matches2)) { #doc de preg_match, fait une regex (garde_regex dans l'infobox et si ok ca va dans la variable matches1
			$val1 = $matches1[1];
			$val2 = $matches2[1];
			#echo $val1 $val2;
			#echo "test boucle";

 			if (!empty($val1) && !empty($val2) && $val1 !== $val2) {
           			echo "<tr><td>$temp</td><td>$val1</td><td>$val2</td></tr>";
       			} elseif (!empty($val1) && !empty($val2) && $val1 === $val2) { #On fait un colspan s'ils ont la même valeur d'attributs
            			echo "<tr><td>$temp</td><td colspan='2'><center>$val1</td><tr>";
       			}
		}
		#echo "test3";
	}

	echo "</tbody></table>";
	echo "<br/><br/>";
	#On récupère l'ID de la comparaison pour ensuite mettre en place les favoris
	#On ajoute l'id de la comparaison dans la table favoris pour avoir la ligne sur laquelle l'utilisateur a mis le favoris

	$req_id_comparaison = "SELECT id FROM Historique WHERE comparaison1 = '$comparaison1' AND comparaison2 = '$comparaison2'";
	$resultat_id_comparaison = mysqli_query($connexion, $req_id_comparaison);
	$ligne_id_comparaison = mysqli_fetch_assoc($resultat_id_comparaison);
	$id_comparaison = $ligne_id_comparaison['id'];

	#ech
	echo '<div class="text-center mt-3">';
	echo '<form method="post" action="/trait_favoris">';
    	echo '<input type="hidden" name="comparaison_id" value="' . $id_comparaison . '">';
    	echo '<button type="submit" class="btn btn-danger" name="ajouter_favoris">Ajouter aux favoris</button>';
	echo '</form>';
	echo '</div>';
    } else {
        echo "Veuillez entrer des valeurs pour les comparaisons.";
    }
	

$connexion->close();

function recupWikiData($Titre) { #Décupère les données des pages depuis l'API MediaWiki
	$URL = "https://fr.wikipedia.org/w/api.php?action=query&format=json&prop=revisions&titles=" . urlencode($Titre) . "&rvprop=content&origin=*"; #URL pour les données des infobox
	$response = file_get_contents($URL); #Effectue du requête GET à l'URL de l'API
	return json_decode($response, true); #Pour mettre le format json

}

function recupWikiDataInfo($Titre){
	$URL1 = "https://fr.wikipedia.org/w/api.php?action=query&format=json&titles=" . urlencode($Titre) . "&prop=info&inprop=protection|talkid|watched|watchers|visitingwatchers|notificationtimestamp|subjectid|url|readable|preload|displaytitle|normalizedtitle|prefixedtitle|delegated&origin=*";
	$response = file_get_contents($URL1);
	return json_decode($response, true);

}

?>


</div>
<br/><br/>
<!--<p>https://fr.wikipedia.org/w/api.php?action=query&format=json&prop=revisions&titles=Porsche&rvprop=content&origin=*</p>-->
</body>
</html>
