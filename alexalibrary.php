<?php
//decode amazon query
$rawpost=$post = file_get_contents('php://input');
$post = json_decode($post);

class BasicFunctions{
// i decided to implement this as an singelton because i consider the function-request more readable
	
	function verified($post, $rawpost, $applicationId){
	//verification as expected by amazon
		$head=getallheaders();
		$signature = openssl_x509_parse(file_get_contents($head['Signaturecertchainurl']));
		openssl_public_decrypt(base64_decode($head['Signature']), $decryptedSignature, openssl_pkey_get_public(file_get_contents($head['Signaturecertchainurl'])));
		return (
			( //verify Signaturecertchainurl
				preg_match("/https:\/\/s3.amazonaws.com(?:\:443){0,1}\/echo.api\/(?:..\/){0,1}echo-api-cert(?:.*?).pem/", $head['Signaturecertchainurl'])
			) &&
			( //verify Signature
				$signature['validFrom_time_t']<time() && time()<$signature['validTo_time_t'] && stristr($signature['subject']['CN'],'echo-api.amazon.com') && substr(bin2hex($decryptedSignature), 30) === sha1($rawpost)
			) &&
			( // verify timestamp of request
				strtotime($post->request->timestamp)>=time()-150
			) &&   
			( // verify application id - can be string or array (for example submission id and developer id)
				$post->session->application->applicationId == $applicationId
				|| in_array($post->session->application->applicationId,$applicationId)			
			)
		);
	} 

	function verificationfailed(){
	// insert code to run if the verification fails as expected by amazon
		header("HTTP/1.1 400 (Bad Request)");
	}

	// email handling
	function askforemailpermission($title='Für deine Anfrage ist deine eMail-Adresse erforderlich.'){
		return ['type'=>'AskForPermissionsConsent',
		'title'=>$title,
		'permissions'=> [ "alexa::profile:email:read" ]
		];
	}
	function getemail($token){
		// Create a stream
		$opts = [ "http" => [ "method" => "GET", "header" => "Accept: application/json\r\nAuthorization: Bearer ".$token."\r\n"] ];
		$context = stream_context_create($opts);
		// Open the file using the HTTP headers set above
		///////////// CAUTION! INSANITY AHEAD: ENDPOINT api.eu.amazonalexa.com WITH .eu ///////////////////////////
		return json_decode(file_get_contents('https://api.eu.amazonalexa.com/v2/accounts/~current/settings/Profile.email', false, $context));
	}

	// ssml effects
	function whisper($text){ return '<amazon:effect name="whispered">'.$text.'</amazon:effect>'; }
	function emphase($text){ return '<emphasis level="strong">'.$text.'</emphasis>'; }
	function date($text){ return '<say-as interpret-as="date" format="dm">'.$text.'</say-as>'; }
	function spell($text){ return '<say-as interpret-as="characters">'.$text.'</say-as>'; }
	function number($text){ return '<say-as interpret-as="number">'.$text.'</say-as>'; }
	function interject($interjection){ return'<say-as interpret-as="interjection">'.$interjection.'</say-as>';}
	function phoneme($text, $phonetic){ return'<phoneme alphabet="ipa" ph="'.$phonetic.'">'.$text.'</phoneme>';}

}

class OutputFunctions{
	// create final processed output

	function debug($str, $where="json"){
		// might come in handy once in a while, shows debugging info in developer console
			$this->sessionAttributes['DebugInfo']=$str;
	}
		
	function answer(){
		global $AccessToken;
		global $debugger;
		global $post;

		$responseArray = [
			'version' => '1.0',
			'response' => [
				'outputSpeech' => ['type' => 'SSML', 'ssml' => '<speak>'.$this->speak.'</speak>'],
				'shouldEndSession' => $this->reprompt?false:true //default setting because the certification staff nags all the time
			]
		];
		// tidy addition of properties in case they exist
		if ($this->reprompt) $responseArray['response']['reprompt']=[
			'outputSpeech'=>[
				'type'=>'SSML',
				'ssml'=>'<speak>'.$this->reprompt.'</speak>'
			]
		];

		if ($this->card) $responseArray['response']['card']=[
			'type'=>'Standard',
			'title'=>$this->card->title,
			'image'=> [
				'smallImageUrl'=> $this->card->image,
				'largeImageUrl'=> $this->card->image
			],
			'text'=>$this->card->text
		];

		if ($this->permission) $responseArray['response']['card']=$this->permission;

		if ($post->context->System->device->supportedInterfaces->Display && $this->display) $responseArray['response']['directives']=[
			[
				'type'=> "Display.RenderTemplate",
				'template'=> [
					'type'=> $this->display->displaytemplate,
					'token'=> $this->display->token,
					'title'=> $this->display->title,
					/*'backgroundImage'=> [  //works, but font-color ist not supported so it is of high risk to use light backgrounds
						'contentDescription'=>'Textured grey background',
						'sources'=> [[
						'url'=>$this->display->bgimage
						]]
					],*/
					'image'=> [
						'contentDescription'=>'icon',
						'sources'=>[[
							'url'=> $this->display->image
						]]
					],
					'listItems'=> $this->display->items,
					'textContent'=>['primaryText'=>['type'=>'RichText', 'text'=> $this->display->text]]
/*									'secondaryText'=>['text'=>"aber immerhin",'type'=>'PlainText'],
									'tertiaryText'=>['text'=>"aber immerhin",'type'=>'PlainText'],*/
					]
			],[
				'type'=>'Hint',
				'hint'=> ['type'=> 'PlainText', 'text'=> $this->display->hint]
			]
		];

		if ($this->sessionAttributes) $responseArray['sessionAttributes']=$this->sessionAttributes;
		
		if ($debugger){ // dev-mode mysqli_connect-object for logging in- and output
			global $post;
			$debugger->query("INSERT INTO json_log VALUES ('',CURRENT_TIMESTAMP,'".json_encode($post)."','".json_encode($responseArray)."')");
		}

		header('Content-Type: application/json; Content-Length:'.strlen(json_encode($responseArray)).'; Authorisation: Bearer '.$AccessToken);
		echo json_encode($responseArray);
	}
}

$ALEXA = new BasicFunctions(); //initialize basic functions - you don´t say!
$OUTPUT = new OutputFunctions(); //initialize output functions - you don´t say!

$OUTPUT->speak = "was kann ich für dich tun?"; // in case i forgot to initialize the variable

//given parameters simplyfied (usual suspects)
$AccessToken = $post->context->System->apiAccessToken;
$IntentName = $post->request->intent->name;

?>