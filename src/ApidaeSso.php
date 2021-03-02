<?php

	nameSpace PierreGranger ;

/**
 * 
 * @author	Pierre Granger	<pierre@pierre-granger.fr>
 * 
 * @todo	Use $this->persist['sso']['refresh_token'] to extend the session
 * @todo	Use $this->persist['sso']['expires_in'] at login (getSsoToken) to detected when you need to use refresh_token
 * @todo	Implement http://dev.apidae-tourisme.com/fr/documentation-technique/v2/oauth/services-associes-au-sso/v002ssoutilisateurautorisationobjet-touristiquemodification
 */
class ApidaeSso extends ApidaeCore {
  
    protected $ssoClientId ;
    protected $ssoSecret ;

	protected $defaultSsoRedirectUrl ;

	/** */
	protected $persist ;

    public function __construct(array $params,&$persist) {

		parent::__construct($params) ;

		if ( ! isset($params['ssoClientId']) ) throw new ApidaeException('missing ssoClientId') ;
		if ( ! preg_match('#^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$#',$params['ssoClientId']) ) throw new \Exception('invalid ssoClientId') ;
		$this->ssoClientId = $params['ssoClientId'] ;
			
		if ( ! isset($params['ssoSecret']) ) throw new ApidaeException(ApidaeException::MISSING_PARAMETER) ;
		if ( ! preg_match('#^[a-zA-Z0-9]{1,20}$#',$params['ssoSecret']) ) throw new ApidaeException(ApidaeException::INVALID_PARAMETER) ;
		$this->ssoSecret = $params['ssoSecret'] ;

		//if ( ! isset($params['defaultSsoRedirectUrl']) ) throw new \Exception('missing defaultSsoRedirectUrl') ;
		if (   isset($params['defaultSsoRedirectUrl']) ) $this->defaultSsoRedirectUrl = $params['defaultSsoRedirectUrl'] ;

        if ( isset($params['timeout']) && preg_match('#^[0-9]+$#',$params['timeout']) ) $this->timeout = $params['timeout'] ;

		$this->_config = $params ;

		$this->persist = &$persist ;

	}
	
	/**
	 * Generate URL for link to auth form
	 * 
	 * @param	$ssoRedirectUrl	URL to be redirected after auth. Can be null : URL will be generated from current url (see genRedirectUrl()).
	 */
	public function getSsoUrl($ssoRedirectUrl=null) {
		return $this->url_base().'/oauth/authorize/?response_type=code&client_id='.$this->ssoClientId.'&scope=sso&redirect_uri='.$this->genRedirectUrl($ssoRedirectUrl) ;
	}

	private function genRedirectUrl($ssoRedirectUrl=null) {

		if ( $ssoRedirectUrl == null )
		{
			if ( isset($_SERVER) && isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI']) )
				$ssoRedirectUrl = 'http'.(isset($_SERVER['HTTPS'])?'s':'').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ;
			else
				$ssoRedirectUrl = $this->defaultSsoRedirectUrl ;
		}
		
		if ( $ssoRedirectUrl == null ) throw new \Exception(__METHOD__.' : Unable to generate ssoRedirectUrl :(') ;

		$query = null ;
		$url = parse_url($ssoRedirectUrl) ;

		if ( isset($url['query']) )
		{
			parse_str($url['query'],$query) ;
			unset($query['code']) ; // Removing ?code=XYZ from current URL
			unset($query['logout']) ; // Removing ?logout=1 from current URL
		}
		$ssoRedirectUrl = $url['scheme'].'://'.$url['host'].$url['path'] . ( ( is_array($query) && sizeof($query) > 0 )  ? '?' . http_build_query($query) : '' ) ;

		return $ssoRedirectUrl ;
	}

	/**
	 * After authentification user is redirected with an additional ?code=XZY Get parameter.
	 * We need to use it to get a token from SSO API
	 * @link	http://dev.apidae-tourisme.com/fr/documentation-technique/v2/oauth/single-sign-on
	 * @param	$code	code given in $_GET['code'] after the user login. User is redirected to $ssoRedirectUrl with this code.
	 */
	public function getSsoToken($code,$ssoRedirectUrl=null) {
		
		$result = $this->request('/oauth/token',Array(
			'USERPWD' => $this->ssoClientId.":".$this->ssoSecret,
			'POSTFIELDS' => "grant_type=authorization_code&code=".$code.'&redirect_uri='.urlencode($this->genRedirectUrl($ssoRedirectUrl)),
			'format' => 'json'
		)) ;
		
		$token_sso = $result['array'] ;
		
		if ( ! isset($token_sso['scope']) )
		{
			throw new ApidaeException('no scope',ApidaeException::NO_SCOPE,Array(
				'debug' => $this->debug,
				'token_sso' => $token_sso
			)) ;
		}

		$this->persist['sso'] = $token_sso ;

		return $token_sso ;
	}

	/**
	 * After authentification user is redirected with an additional ?code=XZY Get parameter.
	 * We need to use it to get a token from SSO API
	 * @link	http://dev.apidae-tourisme.com/fr/documentation-technique/v2/oauth/single-sign-on
	 * @param	$code	code given in $_GET['code'] after the user login. User is redirected to $ssoRedirectUrl with this code.
	 */
	public function refreshSsoToken($ssoRedirectUrl=null) {

		if ( ! $this->connected() )
			throw new ApidaeException(curl_error($ch),ApidaeException::NOT_CONNECTED) ;

		$result = $this->request('/oauth/token',Array(
			'USERPWD' => $this->ssoClientId.":".$this->ssoSecret,
			'POSTFIELDS' => "grant_type=refresh_token&refresh_token=".$this->persist['sso']['refresh_token'].'&redirect_uri='.urlencode($this->genRedirectUrl($ssoRedirectUrl)),
			'format' => 'json'
		)) ;
		
		$token_sso = $result['array'] ;

		if ( ! isset($token_sso['scope']) )
		{
			throw new ApidaeException('no scope',ApidaeException::NO_SCOPE,Array(
				'debug' => $this->debug,
				'token_sso' => $token_sso
			)) ;
		}

		$this->persist['sso'] = $token_sso ;

		return $token_sso ;
	}

	/**
	 * get connected user profil as an array.
	 * @link	http://dev.apidae-tourisme.com/fr/documentation-technique/v2/oauth/services-associes-au-sso/v002ssoutilisateurprofil
	 * @param	boolean	$force	Force refresh, default false. 
	 */
	public function getUserProfile($force=false) {
		
		if ( ! $this->connected() ) return false ;

		$userprofile = false ;

		if ( isset($this->persist['user']) && ! $force )
			$userprofile = $this->persist['user'] ;
		else
		{
			$result = $this->request('/api/v002/sso/utilisateur/profil',Array(
				'token' => $this->persist['sso']['access_token'],
				'format' => 'json'
			)) ;
			
			if ( $result['code'] == 404 )
				throw new \Exception('profile not found',404) ;
			else
			{
				$userprofile = $result['array'] ;
				$this->persist['user'] = $userprofile ;
			}
		}
		return $userprofile ;
	}

	/**
	 * Fun fact : c'est un des seuls services de l'API qui répond en texte brut et pas en json.
	 * Fun fact 2 : la réponse est contenue entre guillements, donc "MODIFICATION_POSSIBLE" et non MODIFICATION_POSSIBLE
	 * @param	int	$id	Identifiant d'un objet Apidae
	 * @return	mixed	MODIFICATION_POSSIBLE|VERROUILLE|NON_DISPONIBLE si l'object existe
	 * 					false si l'objet n'existe pas
	 */
	public function getUserPermissionOnObject($id) {

		if ( ! $this->connected() ) return false ;

		$response = false ;

		$result = $this->request('/api/v002/sso/utilisateur/autorisation/objet-touristique/modification/'.$id,Array(
			'token' => $this->persist['sso']['access_token']
		)) ;

		if ( $result['code'] == 200 )
		{
			return preg_replace('#"#','',$result['body']) ;
		}
		elseif ( $result['code'] == 404 )
		{
			return false ;
		}
		else
			throw new ApidaeException('unexpected http code '.$result['code'].' returned :(') ;
	}

	/**
	 * 
	 */
	public function logout() {
		foreach ( $this->persist as $k => $v )
			unset($this->persist[$k]) ;
	}

	/**
	 * Is the current user connected ?
	 * @return	bool	clear enough
	 */
	public function connected() {
		return isset($this->persist['sso']) ;
	}

	public function form($title='Authentification') {

		$html = null ;
		$html .= '
		<!doctype html>
		<html lang="en">
		<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title>'.htmlentities($title).'</title>
			<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-BmbxuPwQa2lc/FVzBcNJ7UAyJxM6wuqIj61tLrc4wSX0szH/Ev+nYRRuWlolflfl" crossorigin="anonymous">
			<link rel="icon" href="https://raw.githubusercontent.com/PierreGranger/ApidaeCore/master/assets/cropped-favicon-50x50.png" sizes="32x32" />
			<link rel="icon" href="https://raw.githubusercontent.com/PierreGranger/ApidaeCore/master/assets/cropped-favicon-300x300.png" sizes="192x192" />
			<link rel="apple-touch-icon-precomposed" href="https://raw.githubusercontent.com/PierreGranger/ApidaeCore/master/assets/cropped-favicon-300x300.png" />
			<meta name="msapplication-TileImage" content="https://raw.githubusercontent.com/PierreGranger/ApidaeCore/master/assets/cropped-favicon-300x300.png" />
			<link rel="preconnect" href="https://fonts.gstatic.com">
			<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300&display=swap" rel="stylesheet">

			<style>
				
				html,
				body {
					height: 100%;
					font-family:"Open Sans" ;
				}
				
				body {
					display: flex;
					align-items: center;
					padding-top: 40px;
					padding-bottom: 40px;
					background-color: #eeeeee;
				}
				
				.form-signin {
					width: 100%;
					max-width: 530px;
					padding: 25px;
					margin: auto;
					background:#FFF ;
					box-shadow: 0 0 10px 3px #ddd !important;
				}

				.btn-sso {
					background: #5794E4 ;
					color:white ;
					font-size:.9em ;
					padding-left:30px ;
					padding-right:30px ;
				}
				.btn-sso>img {
					margin-right:15px ;
				}

			</style>

		</head>
		<body class="text-center">
			
		<main class="form-signin">
		<form>
			<img class="mb-4" src="https://raw.githubusercontent.com/PierreGranger/ApidaeCore/master/assets/apidae_logotype_rvb.png" alt="" width="50%" />
			<h1 class="h3 mb-3 fw-normal">'.htmlentities($title).'</h1>
			<a href="'.$this->getSsoUrl().'" class="btn btn-sso" type="submit"><img src="https://raw.githubusercontent.com/PierreGranger/ApidaeCore/master/assets/cropped-favicon-50x50.png" width="20" alt="" /> Se connecter avec mon compte utilisateur Apidae</a>
		</form>
		</main>


			
		</body>
		</html>' ;
		return $html ;
	}

}
