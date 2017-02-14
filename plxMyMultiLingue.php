<?php
/**
 * Plugin plxMyMultiLingue
 *
 * @author	Stephane F
 *
 **/

class plxMyMultiLingue extends plxPlugin {

	public $aLangs = array(); # tableau des langues
	public $lang = ''; # langue courante

	/**
	 * Constructeur de la classe
	 *
	 * @param	default_lang	langue par d�faut
	 * @return	stdio
	 * @author	Stephane F
	 **/
	public function __construct($default_lang) {

		$this->lang = "";

		# recherche de la langue par d�faut de PluXml
		if(!isset($_SESSION['default_lang'])) {
			$file = file_get_contents(path('XMLFILE_PARAMETERS'));
			preg_match('#name="racine_themes"><!\[CDATA\[([^\]]+)#',$file,$lang);
			$_SESSION['default_lang'] = empty($path[1]) ? $default_lang : $path[1];
			if(isset($_COOKIE["plxMyMultiLingue"]) and !empty($_COOKIE["plxMyMultiLingue"])) {
				$this->lang = $_COOKIE["plxMyMultiLingue"];
			}
		}

		# recherche de la langue dans l'url si acc�s � partir du sitemap
		if(preg_match("/sitemap\.php\??([a-zA-Z]+)?/", $_SERVER["REQUEST_URI"], $capture)) {
			$this->lang = $capture[1];
		}

		# recherche de la langue dans l'url
		if($this->lang=="") {
			if($_SERVER['QUERY_STRING']=='' AND !preg_match('/core\/admin/', dirname($_SERVER['SCRIPT_NAME']))) {
					$this->lang = $_SESSION['default_lang'];
			} else {
				$get = plxUtils::getGets();
				if(isset($_GET["lang"]) AND !empty($_GET["lang"]) AND defined('PLX_ADMIN')) # changement de lang � partir de l'admin
					$this->lang = $_GET["lang"];
				elseif(preg_match('/^([a-zA-Z]{2})\/(.*)/', $get, $capture))
					$this->lang = $capture[1];
				elseif(isset($_COOKIE["plxMyMultiLingue"]))
					$this->lang = $_COOKIE["plxMyMultiLingue"];
				else
					$this->lang = $_SESSION['default_lang'];
			}
		}

		# appel du constructeur de la classe plxPlugin (obligatoire)
		parent::__construct($this->lang);

		# validation de la langue courante
		$this->validateLang();
		# m�morisation de la langue et chargement du fichier de traduction core.php
		$_SESSION['lang'] = $this->lang;
		loadLang(PLX_CORE.'lang/'.$this->lang.'/core.php');
		# stockage du cookie avec la langue courante - expiration 30 jours
		setcookie("plxMyMultiLingue", $this->lang, time()+3600*24*30, dirname($_SERVER['SCRIPT_NAME']));

		# droits pour acc�der � la page config.php du plugin
		$this->setConfigProfil(PROFIL_ADMIN);

		# d�claration des hooks partie publique
		$this->addHook('ThemeEndHead', 'ThemeEndHead');
		$this->addHook('IndexEnd', 'IndexEnd');
		$this->addHook('FeedEnd', 'FeedEnd');
		$this->addHook('SitemapBegin', 'SitemapBegin');
		$this->addHook('SitemapEnd', 'SitemapEnd');

		# d�claration des hooks plxMotor
		$this->addHook('plxMotorPreChauffageBegin', 'PreChauffageBegin');
		$this->addHook('plxMotorDemarrageEnd', 'plxMotorDemarrageEnd');
		$this->addHook('plxMotorConstructLoadPlugins', 'ConstructLoadPlugins');
		$this->addHook('plxMotorGetStatiques', 'plxMotorGetStatiques');
		$this->addHook('plxMotorDemarrageNewCommentaire', 'plxMotorDemarrageNewCommentaire');

		# d�claration des hooks plxAdmin
		$this->addHook('plxAdminEditConfiguration', 'plxAdminEditConfiguration');
		$this->addHook('plxAdminEditStatiquesUpdate', 'plxAdminEditStatiquesUpdate');
		$this->addHook('plxAdminEditStatiquesXml', 'plxAdminEditStatiquesXml');

		# d�pendances des articles
		$this->addHook('AdminArticleContent', 'AdminArticleContent');
		$this->addHook('plxAdminEditArticleXml', 'plxAdminEditArticleXml');
		$this->addHook('plxMotorParseArticle', 'plxMotorParseArticle');
		$this->addHook('AdminArticlePostData', 'AdminArticlePostData');
		$this->addHook('AdminArticleParseData', 'AdminArticleParseData');
		$this->addHook('AdminArticleInitData', 'AdminArticleInitData');
		$this->addHook('AdminArticlePreview', 'AdminArticlePreview');

		# d�pendances des pages statiques
		$this->addHook('AdminStatic', 'AdminStatic');
		$this->addHook('plxAdminEditStatique', 'plxAdminEditStatique');

		# d�claration des hooks plxShow
		$this->addHook('plxShowStaticListEnd', 'plxShowStaticListEnd');

		# d�claration des hooks plxFeed
		$this->addHook('plxFeedConstructLoadPlugins', 'ConstructLoadPlugins');
		$this->addHook('plxFeedPreChauffageBegin', 'PreChauffageBegin');

		# d�claration des hooks partie administration
		$this->addHook('AdminTopEndHead', 'AdminTopEndHead');
		$this->addHook('AdminFootEndBody', 'AdminFootEndBody');
		$this->addHook('AdminTopBottom', 'AdminTopBottom');
		$this->addHook('AdminSettingsAdvancedTop', 'AdminSettingsAdvancedTop');
		$this->addHook('AdminSettingsBaseTop', 'AdminSettingsBaseTop');

		# d�claration hook utilisateur � mettre dans le th�me
		$this->addHook('MyMultiLingue', 'MyMultiLingue');

		# PLX_MYMULTILINGUE contient la liste des langues - pour �tre utilis� par d'autres plugins
		define('PLX_MYMULTILINGUE', $this->getParam('flags'));

	}

	/**
	 * M�thode appel�e par la classe plxPlugins et execut�e si un fichier "upadate" est pr�sent dans le dossier du plugin
	 * On demande une mise � jour du cache css
	 * Nouvelles r�gles css pour le plugin avec PluXml 5.6 et PluCSS 1.2 pour afficher les drapeaux dans l'action bar
	 *
	 * @author	Stephane F
	 **/
	public function onUpdate() {
		# demande de mise � jour du cache css
		return array('cssCache' => true);
	}

	/**
	 * M�thode ex�cut�e � l'activation du plugin
	 *
	 * @author	Stephane F
	 **/
	public function onActivate() {
		# Mise en cache du css partie administration
		$src_cssfile = PLX_PLUGINS.'plxMyMultiLingue/css/admin.css';
		$dst_cssfile = PLX_ROOT.PLX_CONFIG_PATH.'plugins/plxMyMultiLingue.admin.css';
		plxUtils::write(file_get_contents($src_cssfile), $dst_cssfile);
		# Mise en cache du ccs partie visiteurs
		$src_cssfile = PLX_PLUGINS.'plxMyMultiLingue/css/site.css';
		$dst_cssfile = PLX_ROOT.PLX_CONFIG_PATH.'plugins/plxMyMultiLingue.site.css';
		plxUtils::write(file_get_contents($src_cssfile), $dst_cssfile);
		# R�g�n�ration des caches css
		$plxAdmin = plxAdmin::getInstance();
		$plxAdmin->plxPlugins->cssCache('admin');
		$plxAdmin->plxPlugins->cssCache('site');
	}

	/**
	 * M�thode ex�cut�e � la d�sactivation du plugin
	 *
	 * @author	Stephane F
	 **/
	public function onDeactivate() {
		unset($_SESSION['default_lang']);
		unset($_SESSION['lang']);
		unset($_SESSION['medias']);
		unset($_SESSION['folder']);
		unset($_SESSION['currentfolder']);
		unset($_COOKIE['plxMyMultiLingue']);
		setcookie('plxMyMultiLingue', '', time() - 3600);
	}

	/**
	 * M�thode qui cr��e les r�pertoires des langues (�cran de config du plugin)
	 *
	 * @author	Stephane F
	 **/
	public function mkDirs() {

		$plxAdmin = plxAdmin::getInstance();

		# on nettoie les chemins
		$racine_articles = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_articles']);
		$racine_statiques = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_statiques']);
		$racine_commentaires =  str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_commentaires']);
		$racine_medias = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['medias']);

		if(isset($_POST['flags'])) {
			foreach($_POST['flags'] as $lang) {
				if(!is_dir(PLX_ROOT.$racine_articles.$lang))
					mkdir(PLX_ROOT.$racine_articles.$lang, 0755, true);
				if(!is_dir(PLX_ROOT.$racine_statiques.$lang))
					mkdir(PLX_ROOT.$racine_statiques.$lang, 0755, true);
				if(!is_dir(PLX_ROOT.$racine_commentaires.$lang))
					mkdir(PLX_ROOT.$racine_commentaires.$lang, 0755, true);
				if(!is_dir(PLX_ROOT.$racine_medias.$lang))
					mkdir(PLX_ROOT.$racine_medias.$lang, 0755, true);
				if(!is_dir(PLX_ROOT.PLX_CONFIG_PATH.$lang))
					mkdir(PLX_ROOT.PLX_CONFIG_PATH.$lang, 0755, true);
				plxUtils::write('',PLX_ROOT.PLX_CONFIG_PATH.$lang.'/index.html');
				plxUtils::write("<Files *>\n\tOrder allow,deny\n\tDeny from all\n</Files>",PLX_ROOT.PLX_CONFIG_PATH.$lang.'/.htaccess');
			}
		}

	}

	/**
	 * M�thode qui v�rifie que la langue courante du site est valide
	 *
	 * @author	Stephane F
	 **/
	public function validateLang() {

		# r�cup�ration des langues enregistr�es dans le fichier de configuration du plugin
		if($this->getParam('flags')!='')
			$this->aLangs = explode(',', $this->getParam('flags'));

		# validation de la langue coutante du site
		$this->lang = in_array($this->lang, $this->aLangs) ? $this->lang : $_SESSION['default_lang'];
	}

	/********************************/
	/* core/lib/class.plx.motor.php	*/
	/********************************/

	/**
	 * M�thode qui r�dirige vers la bonne url apr�s soumission d'un commentaire
	 *
	 * @author	Stephane F
	 **/
	public function plxMotorDemarrageNewCommentaire() {

		if($_SESSION['default_lang']!==$this->lang) {
			echo '<?php
				$url = $this->urlRewrite("?'.$this->lang.'/article".intval($this->plxRecord_arts->f("numero"))."/".$this->plxRecord_arts->f("url"));
			?>';
		}
	}

	/**
	 * M�thode qui v�rifie que la langue est bien pr�sente dans l'url
	 *
	 * @author	Stephane F
	 **/
	public function PreChauffageBegin() {

		echo '<?php
			# utilisation de preg_replace pour �tre sur que la chaine commence bien par la langue
			$this->get = preg_replace("/^'.$this->lang.'\/(.*)/", "$1", $this->get);
		?>';

	}

	/**
	 * M�thode qui modifie les chemins de PluXml en tenant compte de la langue
	 *
	 * @author	Stephane F
	 **/
	public function ConstructLoadPlugins() {

		echo '<?php
			# initialisation n� page statique comme page d accueil (recup�r�e dans plxMotorGetStatiques)
			$this->aConf["homestatic"] = "";
		?>';

		# modification des chemins d'acc�s
		echo '<?php
			$this->aConf["default_lang"] ="'.$this->lang.'";
			$this->aConf["racine_articles"] = $this->aConf["racine_articles"]."'.$this->lang.'/";
			$this->aConf["racine_statiques"] = $this->aConf["racine_statiques"]."'.$this->lang.'/";
			$this->aConf["racine_commentaires"] = $this->aConf["racine_commentaires"]."'.$this->lang.'/";
			path("XMLFILE_CATEGORIES", PLX_ROOT.PLX_CONFIG_PATH."'.$this->lang.'/categories.xml");
			path("XMLFILE_STATICS", PLX_ROOT.PLX_CONFIG_PATH."'.$this->lang.'/statiques.xml");
			path("XMLFILE_TAGS", PLX_ROOT.PLX_CONFIG_PATH."'.$this->lang.'/tags.xml");
		?>';

		# modification des infos du site en fonction de la langue
		echo '<?php
			if(file_exists(PLX_ROOT.PLX_CONFIG_PATH."plugins/plxMyMultiLingue.xml")) {
				$this->aConf["title"] = "'.$this->getParam("title_".$this->lang).'";
				$this->aConf["description"] = "'.$this->getParam("description_".$this->lang).'";
				$this->aConf["meta_description"] = "'.$this->getParam("meta_description_".$this->lang).'";
				$this->aConf["meta_keywords"] = "'.$this->getParam("meta_keywords_".$this->lang).'";
			}
		?>';

		# s'il faut un dossier medias diff�rent pour chaque langue
		if($this->getParam('lang_medias_folder')) {
			echo '<?php $this->aConf["medias"] = $this->aConf["medias"]."'.$this->lang.'/"; ?>';
		}

	}

	/**
	 * M�thode qui r�cup�re les d�pendances des pages statiques et la page statique comme page d'accueil
	 *
	 * @author	Stephane F
	 **/
	public function plxMotorGetStatiques() {

		echo '<?php
			# Recuperation du num�ro la page statique d\'accueil
			if(isset($iTags["homeStatic"])) {
				$homeStatic = plxUtils::getValue($iTags["homeStatic"][$i]);
				$this->aStats[$number]["homeStatic"] = plxUtils::getValue($values[$homeStatic]["value"]);
				if($this->aStats[$number]["homeStatic"]) {
					# n� de la page statique comme page d accueil
					$this->aConf["homestatic"] = $number;
				}
			} else {
				$this->aStats[$number]["homeStatic"] = 0;
			}
			# Recuperation des d�pendances des pages statiques
			if(isset($iTags["deplng"])) {
				$deplng = plxUtils::getValue($iTags["deplng"][$i]);
				$this->aStats[$number]["deplng"] = plxUtils::getValue($values[$deplng]["value"]);
			} else {
				$this->aStats[$number]["deplng"] = array();
			}
		?>';
	}

	/********************************/
	/* core/lib/class.plx.show.php 	*/
	/********************************/

	/**
	 * M�thode qui modifie l'url des pages statiques en rajoutant la langue courante dans le lien du menu de la page
	 *
	 * @author	Stephane F
	 **/
	public function plxShowStaticListEnd() {

		if($_SESSION['default_lang']==$this->lang) return;

		echo '<?php
		foreach($menus as $idx => $menu) {
			if($this->plxMotor->aConf["urlrewriting"]) {
				$menus[$idx] = str_replace($this->plxMotor->racine, $this->plxMotor->racine."'.$this->lang.'/", $menu);
			}
		}
		?>';
	}

	/********************************/
	/* core/lib/class.plx.admin.php	*/
	/********************************/

	/**
	 * M�thode qui modifie les chemins de PluXml en supprimant la langue
	 *
	 * @author	Stephane F
	 **/
	public function plxAdminEditConfiguration() {

		# sauvegarde des param�tres pris en compte en fonction de la langue
		echo '<?php
		if (preg_match("/parametres_base/",basename($_SERVER["SCRIPT_NAME"]))) {
			$lang = $this->aConf["default_lang"];
			$plugin = $this->plxPlugins->aPlugins["plxMyMultiLingue"];
			$plugin->setParam("title_".$lang, $_POST["title"], "cdata");
			$plugin->setParam("description_".$lang, $_POST["description"], "cdata");
			$plugin->setParam("meta_description_".$lang, $_POST["meta_description"], "cdata");
			$plugin->setParam("meta_keywords_".$lang, $_POST["meta_keywords"], "cdata");
			$plugin->saveParams();
		}
		?>';

		# pour ne pas �craser les chemins racine_articles, racine_statiques et racine_commentaires
		echo '<?php
			$global["racine_articles"] = str_replace("/'.$this->lang.'/", "/", $global["racine_articles"]);
			$global["racine_statiques"] = str_replace("/'.$this->lang.'/", "/", $global["racine_statiques"]);
			$global["racine_commentaires"] =  str_replace("/'.$this->lang.'/", "/", $global["racine_commentaires"]);
		?>';

		# pour ne pas �craser le chemin du dossier des medias
		if($this->getParam('lang_medias_folder')) {
			echo '<?php $global["medias"] = str_replace("/'.$this->lang.'/", "/", $global["medias"]); ?>';
		}

		# pour tenir compte des changements de param�trage de la langue par d�faut du site
		echo '<?php
			$_SESSION["plxMyMultiLingue"]["default_lang"] = $_POST["default_lang"];
		?>';

	}

	/**
	 * M�thode qui ajoute une nouvelle cl� dans le fichier xml des pages statiques pour savoir
	 * si une page statique est configur�e comme page d'accueil (valeur boolean 0/1)
	 *
	 * @author	Stephane F
	 **/
	public function plxAdminEditStatiquesUpdate() {
		echo '<?php
			if(!isset($content["homeStatic"]))
				$this->aStats[$static_id]["homeStatic"] = 0;
			else
				$this->aStats[$static_id]["homeStatic"] = $content["homeStatic"][0]==$static_id;
		?>';
	}

	/**
	 * M�thode qui enregistre une nouvelle cl� dans le fichier xml des pages statiques pour stocker
	 * le n� de la page statique d'accueil
	 *
	 * @author	Stephane F
	 **/
	public function plxAdminEditStatiquesXml() {
		echo '<?php
			if(!isset($static["homeStatic"])) $static["homeStatic"] = 0;
			$xml .= "<homeStatic><![CDATA[".plxUtils::cdataCheck($static["homeStatic"])."]]></homeStatic>";
			# d�pendances des pages statiques
			if(!isset($static["deplng"])) $static["deplng"]="";
			$xml .= "<deplng><![CDATA[".plxUtils::cdataCheck($static["deplng"])."]]></deplng>";
		?>';
	}

	/*************************************/
	/* core/admin/parametres_avances.php */
	/*************************************/

	/**
	 * M�thode qui modifie les chemins de PluXml en supprimant la langue
	 *
	 * @author	Stephane F
	 **/
	public function AdminSettingsAdvancedTop() {

		# pour ne pas �craser les chemins racine_articles, racine_statiques et racine_commentaires
		echo '<?php
			$plxAdmin->aConf["racine_articles"] = str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["racine_articles"]);
			$plxAdmin->aConf["racine_statiques"] = str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["racine_statiques"]);
			$plxAdmin->aConf["racine_commentaires"] =  str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["racine_commentaires"]);
		?>';

		# pour ne pas �craser le chemin du dossier des medias
		if($this->getParam('lang_medias_folder')) {
			echo '<?php $plxAdmin->aConf["medias"] =  str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["medias"]); ?>';
		}

	}

	/************************************/
	/* core/admin/parametres_base.php 	*/
	/************************************/

	/**
	 * M�thode qui remet la vraie langue par d�faut de PluXml du fichier parametres.xml, sans tenir compte du multilangue
	 *
	 * @author	Stephane F
	 **/
	public function AdminSettingsBaseTop() {

		echo '<?php
			$plxAdmin->aConf["default_lang"] = $_SESSION["plxMyMultiLingue"]["default_lang"];
		?>';

	}

	/********************************/
	/* core/admin/top.php 			*/
	/********************************/

	/**
	 * M�thode qui affiche les drapeaux ou le nom des langues dans l'administration
	 *
	 * return	stdio
	 * @author	Stephane F
	 **/
	public function AdminTopBottom() {

		$aLabels = unserialize($this->getParam('labels'));

		# affichage des drapeaux
		if($this->aLangs) {
			echo '<div id="langs">';
			if($this->getParam('display')=='listbox') {
				echo "<select onchange=\"self.location='?lang='+this.options[this.selectedIndex].value\">";
				foreach($this->aLangs as $idx=>$lang) {
					$sel = $this->lang==$lang ? ' selected="selected"':'';
					echo '<option value="'.$lang.'"'.$sel.'>'. $aLabels[$lang].'</option>';
				}
				echo '</select>';
			} else {
				foreach($this->aLangs as $lang) {
					$sel = $this->lang==$lang ? " active" : "";
					if($this->getParam('display')=='flag') {
						$img = '<img class="lang'.$sel.'" src="'.PLX_PLUGINS.'plxMyMultiLingue/img/'.$lang.'.png" alt="'.$lang.'" />';
						echo '<a href="?lang='.$lang.'">'.$img.'</a>';
					} else {
						echo '<a class="lang'.$sel.'" href="?lang='.$lang.'">'.$aLabels[$lang].'</a>';
					}
				}
			}
			echo '</div>';
		}

	}

	/**
	 * M�thode qui d�marre la buff�risation de sortie
	 *
	 * @author	Stephane F
	 **/
	public function AdminTopEndHead() {

		echo '<?php ob_start(); ?>';
	}

	/**
	 * M�thode qui rajoute la langue courante dans les liens des articles et des pages statiques
	 *
	 * @author	Stephane F
	 **/
	public function AdminFootEndBody() {

		echo '<?php
			$output = ob_get_clean();
			if (!preg_match("/parametres/",basename($_SERVER["SCRIPT_NAME"]))) {
				$output = preg_replace("/(article[a-z0-9-]+\/)/", "'.$this->lang.'/$1", $output);
				$output = preg_replace("/(static[a-z0-9-]+\/)/", "'.$this->lang.'/$1", $output);
			}
			echo $output;
		?>';

	}

	/********************************/
	/* index.php 					*/
	/********************************/

	/**
	 * M�thode qui modifie les liens en tenant compte de la langue courante et de la r��criture d'urls
	 *
	 * @author	Stephane F
	 **/
	public function IndexEnd() {

		$lang = $_SESSION['default_lang']==$this->lang ? "" : $this->lang."/";

		echo '<?php
			$output = str_replace($plxMotor->racine."article", $plxMotor->racine."'.$lang.'article", $output);
			$output = str_replace($plxMotor->racine."static", $plxMotor->racine."'.$lang.'static", $output);
			$output = str_replace($plxMotor->racine."categorie", $plxMotor->racine."'.$lang.'categorie", $output);
			$output = str_replace($plxMotor->racine."tag", $plxMotor->racine."'.$lang.'tag", $output);
			$output = str_replace($plxMotor->racine."archives", $plxMotor->racine."'.$lang.'archives", $output);
			$output = str_replace($plxMotor->racine."feed", $plxMotor->racine."feed/'.$lang.'", $output);
			$output = str_replace($plxMotor->racine."page", $plxMotor->racine."'.$lang.'page", $output);
			$output = str_replace($plxMotor->racine."blog", $plxMotor->racine."'.$lang.'blog", $output);
			$output = str_replace($plxMotor->aConf["medias"], $plxMotor->racine.$plxMotor->aConf["medias"], $output);
			$output = str_replace(PLX_PLUGINS, $plxMotor->aConf["racine_plugins"], $output);
		?>';
	}

	/********************************/
	/* feed.php 					*/
	/********************************/

	/**
	 * M�thode qui modifie les liens en tenant compte de la langue courante et de la r��criture d'urls
	 *
	 * @author	Stephane F
	 **/
	public function FeedEnd() {

		$lang = $_SESSION['default_lang']==$this->lang ? "" : $this->lang."/";

		echo '<?php
			$output = str_replace($plxFeed->racine."article", $plxFeed->racine."'.$lang.'article", $output);
			$output = str_replace($plxFeed->racine."static", $plxFeed->racine."'.$lang.'static", $output);
			$output = str_replace($plxFeed->racine."categorie", $plxFeed->racine."'.$lang.'categorie", $output);
			$output = str_replace($plxFeed->racine."tag", $plxFeed->racine."'.$lang.'tag", $output);
			$output = str_replace($plxFeed->racine."archives", $plxFeed->racine."'.$lang.'archives", $output);
			$output = str_replace($plxFeed->racine."feed", $plxFeed->racine."feed/'.$lang.'", $output);
			$output = str_replace($plxFeed->racine."page", $plxFeed->racine."'.$lang.'page", $output);
			$output = str_replace($plxFeed->racine."blog", $plxFeed->racine."'.$lang.'blog", $output);
		}
		?>';

	}

	/********************************/
	/* sitemap.php 					*/
	/********************************/

	/**
	 * M�thode qui g�n�re un sitemap en fonction d'une langue
	 *
	 * @author	Stephane F
	 **/
	public function SitemapBegin() {

		if($_SESSION['default_lang']==$this->lang) return;

		# affichage du sitemapindex ou du sitemap de la langue
		if(empty($_SERVER['QUERY_STRING'])) {
			# cr�ation d'un sitemapindex
			echo '<?php echo "<?xml version=\"1.0\" encoding=\"".strtolower(PLX_CHARSET)."\"?>\n<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">" ?>';
			foreach($this->aLangs as $lang) {
				echo '<?php echo "\n\t<sitemap>"; ?>';
				echo '<?php echo "\n\t\t<loc>".$plxMotor->racine."sitemap.php?'.$lang.'</loc>"; ?>';
				echo '<?php echo "\n\t</sitemap>"; ?>';
			}
			echo '<?php echo "\n</sitemapindex>"; ?>';
			echo '<?php return true; ?>';
		}
	}

	public function SitemapEnd() {

		$this->IndexEnd();

	}

	/********************************/
	/* th�me: affichage du drapeaux */
	/********************************/

	/**
	 * M�thode qui r�cup�re les d�pendances sur les articles et les pages statiques
	 *
	 * @author	Stephane F
	 **/

	public function plxMotorDemarrageEnd() {
		echo '<?php
		$this->infos_arts = null;
		$this->infos_statics = null;

		if($this->mode=="article") {
			if(isset($this->plxRecord_arts)) {
				if($deplng = $this->plxRecord_arts->f("deplng")) {
					foreach($deplng as $lang => $ident) {
						# r�cup�ration du titre de l article correspondant � la langue
						$root = PLX_ROOT.$this->aConf["racine_articles"];
						$root = str_replace("/'.$this->lang.'/", "/".$lang."/", $root);
						$folder = opendir($root);
						while($file = readdir($folder)) {
							if(preg_match("/^".$ident."(.*).xml$/", $file)) {
								$uniqart = $this->parseArticle($root.$file);
								if($uniqart["date"] <= date("YmdHi")) {
									$url = "/article".intval($ident)."/".$uniqart["url"];
									if($lang!=$_SESSION["default_lang"]) $url = $lang.$url;
									$this->infos_arts[$lang]["img"] = "<img class=\"lang\" src=\"".$this->urlRewrite(PLX_PLUGINS."plxMyMultiLingue/img/".$lang.".png")."\" alt=\"".$lang."\" />";
									$this->infos_arts[$lang]["link"] = "<a href=\"".$url."\">".plxUtils::strCheck($uniqart["title"])."</a>";
									$this->infos_arts[$lang]["url"] = $url;
								}
								break;
							}
						}
						closedir($folder);
					}
				}
			}
		}
		elseif($this->mode=="static") {
			$deplng = null;
			if(isset($this->aStats[$this->cible]["deplng"]) AND !empty($this->aStats[$this->cible]["deplng"])) {
				$values = explode("|", $this->aStats[$this->cible]["deplng"]);
				foreach($values as $k => $v) {
					$tmp = explode(",", $v);
					$deplng[$tmp[0]] = $tmp[1];
				}
			}
			if($deplng) {
				foreach($deplng as $lang => $id) {
					if($lang!="'.$this->lang.'") {
						# r�cup�ration du titre de la page statique correspondant � la langue
						$root = PLX_ROOT.PLX_CONFIG_PATH;
						$root = str_replace("/'.$this->lang.'/", $lang, $root);
						$filename=$root.$lang."/statiques.xml";
						if(is_file($filename)) {
							# Mise en place du parseur XML
							$data = implode("",file($filename));
							$parser = xml_parser_create(PLX_CHARSET);
							xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0);
							xml_parser_set_option($parser,XML_OPTION_SKIP_WHITE,0);
							xml_parse_into_struct($parser,$data,$values,$iTags);
							xml_parser_free($parser);
							if(isset($iTags["statique"]) AND isset($iTags["name"])) {
								$nb = sizeof($iTags["name"]);
								$size=ceil(sizeof($iTags["statique"])/$nb);
								for($i=0;$i<$nb;$i++) {
									$attributes = $values[$iTags["statique"][$i*$size]]["attributes"];
									$number = $attributes["number"];
									if($number==$id) {
										$active = intval($attributes["active"]);
										if($active) {
											$url = "/static".intval($id)."/".strtolower($attributes["url"]);
											if($lang!=$_SESSION["default_lang"]) $url = $lang.$url;
											$title = plxUtils::getValue($values[$iTags["name"][$i]]["value"]);
											$this->infos_statics[$lang]["img"] = "<img class=\"lang\" src=\"".$this->urlRewrite(PLX_PLUGINS."plxMyMultiLingue/img/".$lang.".png")."\" alt=\"".$lang."\" />";
											$this->infos_statics[$lang]["link"] = "<a href=\"".$url."\">".plxUtils::strCheck($title)."</a>";
											$this->infos_statics[$lang]["url"] = $url;
										}
										break;
									}
								}
							}
						}
					}
				}
			}
		}
		?>';
	}

	/**
	 * M�thode qui affiche les drapeaux, le nom des langues ou une list d�roulante pour la partie visiteur du site
	 * ou les liens d�pendants de l'article r�dig� dans d'autres langues
	 *
	 * param	param	si valeur = 'artlinks' on affiche les liens d�pendants de l'article
	 * return	stdio
	 * @author	Stephane F
	 **/
	public function MyMultiLingue($param) {

		# Affichage des drapeaux
		if($param=="") {
			$aLabels = unserialize($this->getParam('labels'));
			if($this->aLangs) {
				echo '<div id="langs">';
				if($this->getParam('display')=='listbox') {
					echo '<select onchange="self.location=\'<?php echo $plxShow->plxMotor->urlRewrite() ?>\'+this.options[this.selectedIndex].value+\'/\'">';
					foreach($this->aLangs as $idx=>$lang) {
						$sel = $this->lang==$lang ? ' selected="selected"':'';
						echo '<option value="'.$lang.'"'.$sel.'>'. $aLabels[$lang].'</option>';
					}
					echo '</select>';
				} else {
					echo '<ul>';
					foreach($this->aLangs as $idx=>$lang) {

						$url_lang = $lang.'/';
						if($_SESSION['default_lang']==$lang) $url_lang="";

						$sel = $this->lang==$lang ? ' active':'';
						if($this->getParam('display')=='flag') {
							echo '<?php
								$img = "<img class=\"lang'.$sel.'\" src=\"".PLX_PLUGINS."plxMyMultiLingue/img/'.$lang.'.png"."\" alt=\"'.$lang.'\" />";
								echo "<li><a href=\"'.$url_lang.'\">".$img."</a></li>";
							?>';
						} else {
							echo '<li><?php echo "<a class=\"lang'.$sel.'\" href=\"'.$url_lang.'\">'. $aLabels[$lang].'</a></li>"; ?>';
						}
					}
					echo '</ul>';
				}
				echo '</div>';
			}
		}
		# Affichage des d�pendances entre articles
		elseif($param=="artlinks") {
			echo '<?php
				if($plxMotor->infos_arts) {
					$output = "";
					foreach($plxMotor->infos_arts as $lang => $data) {
						$output .= "<li>".$data["img"]." ".$data["link"]."</li>";
					}
					if($output!="") {
						echo "<ul class=\"unstyled-list\">".$output."</ul>";
					}
				}
			?>';
		}
		# Affichage des d�pendances entre articles
		elseif($param=="staticlinks") {
			echo '<?php
				if($plxMotor->infos_statics) {
					$output = "";
					foreach($plxMotor->infos_statics as $lang => $data) {
						$output .= "<li>".$data["img"]." ".$data["link"]."</li>";
					}
					if($output!="") {
						echo "<ul class=\"unstyled-list\">".$output."</ul>";
					}
				}
			?>';
		}
	}

	/**
	 * M�thode qui affiche les balises <link rel="alternate"> de tous les articles d�pendants par langue
	 *
	 * @author	Stephane F
	 **/
	public function ThemeEndHead() {
		echo '<?php
		if($plxMotor->mode=="article" AND $plxMotor->infos_arts) {
			foreach($plxMotor->infos_arts as $lang => $data) {
				echo "\t<link rel=\"alternate\" hreflang=\"".$lang."\" href=\"".$data["url"]."\" />\n";
			}
		}
		if($plxMotor->mode=="static" AND $plxMotor->infos_statics) {
			foreach($plxMotor->infos_statics as $lang => $data) {
				echo "\t<link rel=\"alternate\" hreflang=\"".$lang."\" href=\"".$data["url"]."\" />\n";
			}
		}
		?>';
	}

	/******************************************/
	/* Gestion des d�pendances entre articles */
	/******************************************/

	/**
	 * M�thode qui affiche les d�pendances d'articles entre les langues
	 *
	 * @author	Stephane F
	 **/
	public function AdminArticleContent() {

		# affichage des drapeaux
		if($this->aLangs) {
			echo '<p>'.$this->getLang('L_IDENT_ARTICLE').'</p>';
			echo '<ul class="unstyled-list">';
			foreach($this->aLangs as $lang) {
				if($this->lang!=$lang) {
					echo '<?php
					$img = "<img src=\"'.PLX_PLUGINS.'plxMyMultiLingue/img/'.$lang.'.png\" alt=\"'.$lang.'\" />";
					$id = $titre = "";
					if(isset($art["deplng"]["'.$lang.'"])) {
						$id = $art["deplng"]["'.$lang.'"];
						$id = intval($id)>0 ? str_pad($id,4,"0",STR_PAD_LEFT) : "";
						# r�cup�ration du titre de l article correspondant � la langue
						$root = PLX_ROOT.$plxAdmin->aConf["racine_articles"];
						$root = str_replace("/'.$this->lang.'/", "/'.$lang.'/", $root);
						$folder = opendir($root);
						while($file = readdir($folder)) {
							if(preg_match("/^".$id."(.*).xml$/", $file)) {
								$uniqart = $plxAdmin->parseArticle($root.$file);
								$titre = $uniqart["title"];
								$titre = "<a href=\"?lang='.$lang.'&amp;a=".$id."\">".plxUtils::strCheck($titre)."</a>";
								break;
							}
						}
						closedir($folder);
					}
					# affichage
					$fld = "<input value=\"".$id."\" type=\"text\" name=\"deplng['.$lang.']\" maxlength=\"4\" size=\"2\" />";
					echo "<li>".$img." ".$fld." ".$titre."</li>";
					?>';
				}
			}
			echo '</ul>';
		}
	}

	/**
	 * M�thode qui enregistre dans les articles les d�pendances (identifiants par langue)
	 *
	 * @author	Stephane F
	 **/
	public function plxAdminEditArticleXml() {

		if(isset($_POST['deplng'])) {
			foreach($_POST['deplng'] as $lang => $ident) {
				$id = intval($ident);
				if($id>0) {
					echo '<?php
						$xml .= "\t<deplng><![CDATA['.$lang.",".str_pad($id,4,"0",STR_PAD_LEFT).']]></deplng>\n";
					?>';
				}

			}
		}
	}

	public function AdminArticlePostData() {
		echo '<?php $art["deplng"] = $_POST["deplng"]; ?>';
	}

	public function AdminArticlePreview() {
		echo '<?php $art["deplng"] = $_POST["deplng"]; ?>';
	}

	public function AdminArticleParseData() {
		echo '<?php $art["deplng"] = $result["deplng"]; ?>';
	}

	public function AdminArticleInitData() {
		echo '<?php $art["deplng"] = null; ?>';
	}

	public function plxMotorParseArticle() {
		echo '<?php
			if(isset($iTags["deplng"])) {
				foreach($iTags["deplng"] as $k => $v) {
					$key = $values[$v]["value"];
					$val = explode(",", $key);
					$art["deplng"][$val[0]] = $val[1];
				}
			} else {
				$art["deplng"] = null;
			}
			?>';
	}

	/************************************************/
	/* Gestion des d�pendances entre page statiques */
	/************************************************/

	/**
	 * M�thode qui enregistre les d�pendances dans le fichiers statiques.xml de la langue courante
	 *
	 * @author	Stephane F
	 **/
	public function plxAdminEditStatique() {
		echo '<?php
			if(isset($content["deplng"])) {
				$values = array();
				foreach($content["deplng"] as $lang => $ident) {
					$id = intval($ident);
					if($id>0) {
						$values[] = $lang.",".str_pad($id,3,"0",STR_PAD_LEFT);
					}
				}
				$this->aStats[$content["id"]]["deplng"] = implode("|", $values);
			}
		?>';
	}


	/**
	 * M�thode qui affiche les d�pendances des pages statiques entre les langues
	 *
	 * @author	Stephane F
	 **/
	public function AdminStatic() {

		echo '<?php
		# r�cup�ration des d�pendances des pages et stockage dans un tableau pour manipulation + facile
		$deplng = array();

		if(isset($plxAdmin->aStats[$id]["deplng"]) AND !empty($plxAdmin->aStats[$id]["deplng"])) {
			$values = explode("|", $plxAdmin->aStats[$id]["deplng"]);
			foreach($values as $k => $v) {
				$tmp = explode(",", $v);
				$deplng[$tmp[0]] = $tmp[1];
			}
		}
		?>';

		# affichage des drapeaux
		if($this->aLangs) {
			echo '<p>'.$this->getLang('L_IDENT_STATIC').'</p>';
			echo '<ul class="unstyled-list">';
			foreach($this->aLangs as $lang) {
				if($this->lang!=$lang) {
					echo '<?php
					# recherche du titre de la page statique
					$img = "<img src=\"'.PLX_PLUGINS.'plxMyMultiLingue/img/'.$lang.'.png\" alt=\"'.$lang.'\" />";
					$id = $titre = "";
					if(isset($deplng["'.$lang.'"])) {
						$id = $deplng["'.$lang.'"];
						$id = intval($id)>0 ? str_pad($id,3,"0",STR_PAD_LEFT) : "";
						# r�cup�ration du titre de la page statique correspondant � la langue
						$root = PLX_ROOT.PLX_CONFIG_PATH;
						$root = str_replace("/'.$this->lang.'/", "/'.$lang.'/", $root);
						$filename=$root."'.$lang.'/statiques.xml";
						if(is_file($filename)) {
							# Mise en place du parseur XML
							$data = implode("",file($filename));
							$parser = xml_parser_create(PLX_CHARSET);
							xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0);
							xml_parser_set_option($parser,XML_OPTION_SKIP_WHITE,0);
							xml_parse_into_struct($parser,$data,$values,$iTags);
							xml_parser_free($parser);
							if(isset($iTags["statique"]) AND isset($iTags["name"])) {
								$nb = sizeof($iTags["name"]);
								$size=ceil(sizeof($iTags["statique"])/$nb);
								for($i=0;$i<$nb;$i++) {
									$attributes = $values[$iTags["statique"][$i*$size]]["attributes"];
									$number = $attributes["number"];
									if($number==$id) {
										# R�cup�ration du nom de la page statique
										$titre = plxUtils::getValue($values[$iTags["name"][$i]]["value"]);
										$titre = "<a href=\"?lang='.$lang.'&amp;p=".$id."\">".plxUtils::strCheck($titre)."</a>";
										break;
									}
								}
							}
						}
					}
					# affichage
					$fld = "<input value=\"".$id."\" type=\"text\" name=\"deplng['.$lang.']\" maxlength=\"3\" size=\"2\" />";
					echo "<li>".$img." ".$fld." ".$titre."</li>";
					?>';
				}
			}
			echo '</ul>';
		}

	}

}
?>