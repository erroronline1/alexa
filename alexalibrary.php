<?php
//decode amazon query
$rawpost=$post = file_get_contents('php://input');
$post = json_decode($post);

class BasicFunctions{
// i decided to implement this as an singelton because i consider the function-request more readable
	
	public function verified($post, $rawpost, $applicationId){
	//verification as expected by amazon
		$head=getallheaders();
		$signature = openssl_x509_parse(file_get_contents($head['Signaturecertchainurl']));
		openssl_public_decrypt(base64_decode($head['Signature']), $decryptedSignature, openssl_pkey_get_public(file_get_contents($head['Signaturecertchainurl'])));
		return (
			( //verify Signaturecertchainurl
				preg_match("/https:\/\/s3.amazonaws.com(?:\:443){0,1}\/echo.api\/(?:..\/){0,1}echo-api-cert(?:.*?).pem/", $head['Signaturecertchainurl'])
			) &&
			( //verify Signature
				$signature['validFrom_time_t']<time() && time <$signature['validTo_time_t'] && stristr($signature['subject']['CN'],'echo-api.amazon.com') && substr(bin2hex($decryptedSignature), 30) === sha1($rawpost)
			) &&
			( // verify timestamp of request
				strtotime($post->request->timestamp)>=time()-150
			) &&   
			( // verify application id - can be string or array (for example submission id and developer id)
				in_array($post->session->application->applicationId,$applicationId)
				|| $post->session->application->applicationId == $applicationId
			)
		);
	} 

	public function verificationfailed(){
	// insert code to run if the verification fails as expected by amazon
		header("HTTP/1.1 400 (Bad Request)");
	}

	// email handling
	public function askforemailpermission($title='Für deine Anfrage ist deine eMail-adresse erforderlich.'){
		return ['type'=>'AskForPermissionsConsent',
		'title'=>$title,
		'permissions'=> [ "alexa::profile:email:read" ]
		];
	}
	public function getemail($token){
		// Create a stream
		$opts = [ "http" => [ "method" => "GET", "header" => "Accept: application/json\r\nAuthorization: Bearer ".$token."\r\n"] ];
		$context = stream_context_create($opts);
		// Open the file using the HTTP headers set above
		///////////// CAUTION! INSANITY AHEAD: ENDPOINT api.eu.amazonalexa.com WITH .eu ///////////////////////////
		return json_decode(file_get_contents('https://api.eu.amazonalexa.com/v2/accounts/~current/settings/Profile.email', false, $context));
	}

	// ssml effects
	public function whisper($text){ return '<amazon:effect name="whispered">'.$text.'</amazon:effect>'; }
	public function emphase($text){ return '<emphasis level="moderate">'.$text.'</emphasis>'; }
	public function date($text){ return '<say-as interpret-as="date" format="dm">'.$text.'</say-as>'; }
	public function spell($text){ return '<say-as interpret-as="characters">'.$text.'</say-as>'; }
	public function number($text){ return '<say-as interpret-as="number">'.$text.'</say-as>'; }
	public function interject($interjection){ return'<say-as interpret-as="interjection">'.$interjection.'</say-as>';}

	// final processed output
	public function answer(){
		global $reprompt;
		global $card;
		global $output;
		global $sessionAttributes;
		global $AccessToken;
		
		$responseArray = [
			'version' => '1.0',
			'response' => [
				'outputSpeech' => ['type' => 'SSML', 'ssml' => '<speak>'.$output.'</speak>'],
				'card'=>$card,
				'reprompt'=>($reprompt?['outputSpeech'=>['type'=>'SSML', 'ssml'=>'<speak>'.$reprompt.'</speak>']]:false),
				'shouldEndSession' => $reprompt?false:true //default setting because the certification staff nags all the time
			],
			'sessionAttributes'=>$sessionAttributes
		];
//global $post;
//		$ChefsekretaerinMysqli->query("INSERT INTO json_log VALUES ('',CURRENT_TIMESTAMP,'".$post."','".$responseArray."')");


		header('Content-Type: application/json; Content-Length:'.strlen(json_encode($responseArray)).'; Authorisation: Bearer '.$AccessToken);
		echo json_encode($responseArray);
	}
}

function debug($str, $where="json"){
// might come in handy once in a while
		if ($where=="card") {
			global $card;
			$card=['type'=>'Simple', 'title'=>'skill debugging', 'content'=>$str ];
		}
		else {
			global $sessionAttributes;
			$sessionAttributes=['DebugInfo'=>$str];
		}
	}
	
$ALEXA = new BasicFunctions(); //initialize basic functions - you don´t say!
$output = "hallo welt"; // in case i forgot to initialize the variable

//given parameters simplyfied (usual suspects)
$AccessToken = $post->context->System->apiAccessToken;
$IntentName = $post->request->intent->name;

?>