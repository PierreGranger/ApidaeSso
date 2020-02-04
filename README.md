# ApidaeSso

Classe d'utilisation de l'API SSO D'Apidae

_____
## Tests

L'installation nécessite d'utiliser Composer.

Placez-vous dans votre dossier de travail :
```
composer require pierre-granger/apidae-sso
```

Copiez ou renommez le fichier `config.sample.php` : `config.inc.php`

Après avoir créé votre projet SSO sur Apidae vous aurez récupéré les codes suivants à rentrer dans `config.inc.php` :

```
	$configApidaeSso['ssoClientId'] = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee' ;
	$configApidaeSso['ssoSecret'] = 'a1b2c3...' ;
```

Créez votre fichier de test (`test.php`...)

Vous pouvez reprendre le contenu de `tests/sso.php` en adaptant les chemins :

```
<?php

    ini_set('display_errors',1) ;
    error_reporting(E_ALL) ;
    session_start() ;

    require(realpath(dirname(__FILE__)).'/vendor/autoload.php') ;
    require(realpath(dirname(__FILE__)).'/config.inc.php') ;

    use PierreGranger\ApidaeSso ;

    $configApidaeSso['debug'] = true ;
    $ApidaeSso = new ApidaeSso($configApidaeSso,$_SESSION['ApidaeSso']) ;

    if ( isset($_GET['logout']) )
    {
        $ApidaeSso->logout() ;
    }

    /* After an authentification, user is redirected to this page with a additional ?code=1234 in URL. We will use this code to get the token from SSO API */
    if ( isset($_GET['code']) && ! $ApidaeSso->connected() )
    {
        $ApidaeSso->getSsoToken($_GET['code']) ;
    }
    
    if ( $ApidaeSso->connected() ) echo '<a href="?logout">Logout</a>' ;
    else
    {
        echo '<a href="'.$ApidaeSso->getSsoUrl().'">Link to SSO Auth</a>' ;
        die() ;
    }

    // Here we know we are connected.

    echo '<pre>'.print_r($ApidaeSso->getUserProfile(),true).'</pre>' ;
```

Si tout va bien... c'est tout : vous n'avez plus qu'à vous rendre via votre navigateur web sur le fichier .../test.php et vous devriez avoir un bouton "Link to SSO Auth" (à renommer en prod bien sûr : "Me connecter à Apidae"...)

Le suivi de la session est assuré par `$_SESSION['ApidaeSso']` : une fois en production, veillez a bien avoir démarré une session avant toute utilisation du SSO : `session_start()`.

Une fois l'utilisateur identifié, vous pourrez avoir des informations le concernant grâce à `getUserProfile()`.

Pour plus de détails sur les droits de l'utilisateur, vous devrez utiliser l'[API membres d'Apidae](http://dev.apidae-tourisme.com/fr/documentation-technique/v2/api-de-diffusion/liste-des-services-2#membre). Une autre classe permet de s'en servir : https://github.com/PGranger/ApidaeMembres

Pour plus d'informations : http://dev.apidae-tourisme.com/fr/documentation-technique/v2/oauth/single-sign-on
