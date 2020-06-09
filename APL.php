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
				$signature['validFrom_time_t'] < time() && time() < $signature['validTo_time_t'] && stristr($signature['subject']['CN'], 'echo-api.amazon.com') && substr(bin2hex($decryptedSignature), 30) === sha1($rawpost)
			) &&
			( // verify timestamp of request
				strtotime($post->request->timestamp) >= time()-150
			) &&   
			( // verify application id - can be string or array (for example submission id and developer id)
				$post->session->application->applicationId == $applicationId
				|| in_array($post->session->application->applicationId, $applicationId)			
			)
		);
	} 

	function verificationfailed(){
	// insert code to run if the verification fails as expected by amazon
		header("HTTP/1.1 400 (Bad Request)");
	}

	// email handling
	function askforemailpermission($title='Für deine Anfrage ist deine eMail-Adresse erforderlich.'){
		return ['type' => 'AskForPermissionsConsent',
		'title' =>$title,
		'permissions' => [ "alexa::profile:email:read" ]
		];
	}
	function getemail($token){
		// Create a stream
		$opts = [ 'http' => [ 'method' => 'GET', 'header' => 'Accept: application/json\r\nAuthorization: Bearer ' . $token . '\r\n'] ];
		$context = stream_context_create($opts);
		// Open the file using the HTTP headers set above
		///////////// CAUTION! INSANITY AHEAD: ENDPOINT api.eu.amazonalexa.com WITH .eu ///////////////////////////
		return json_decode(file_get_contents('https://api.eu.amazonalexa.com/v2/accounts/~current/settings/Profile.email', false, $context));
	}

	// ssml effects
	function whisper($text){ return '<amazon:effect name="whispered">' . $text . '</amazon:effect>'; }
	function emphase($text){ return '<emphasis level="strong">' . $text . '</emphasis>'; }
	function date($text){ return '<say-as interpret-as="date" format="dmy">' . $text . '</say-as>'; }
	function spell($text){ return '<say-as interpret-as="characters">' . $text . '</say-as>'; }
	function number($text){ return '<say-as interpret-as="number">' . $text . '</say-as>'; }
	function interject($interjection){ return'<say-as interpret-as="interjection">' . $interjection . '</say-as>';}
	function phoneme($text, $phonetic){ return'<phoneme alphabet="ipa" ph="' . $phonetic . '">' . $text . '</phoneme>';}

	// returns ISO-8601 duration format (PnYnMnDTnHnMnS) resolved for speech and in seconds
	function resolveDuration($duration, $lang='de'){
		$t=[
			[],
			['de' => ['Jahr', 'Jahre'], 'en' => ['year', 'years'], 'sec' => 3600*24*365],
			['de' => ['Monat', 'Monate'], 'en' => ['month', 'months'], 'sec' => 3600*24*30],
			['de' => ['Tag', 'Tage'], 'en' => ['day', 'days'], 'sec' => 3600*24],
			['de' => ['Stunde', 'Stunden'], 'en' => ['hour', 'hours'], 'sec' => 3600],
			['de' => ['Minute', 'Minuten'], 'en' => ['minute', 'minutes'], 'sec' => 60],
			['de' => ['Sekunde', 'Sekunden'], 'en' => ['second', 'seconds'], 'sec' => 1],
		];
		preg_match_all('/P(?>(\d+)Y)?(?>(\d+)M)?(?>(\d+)D)?T(?>(\d+)H)?(?>(\d+)M)?(?>(\d+)S)?/is', $duration, $matches);
		foreach ($matches as $index => $value){
			if ($index == 0) continue;
			$out['speech'] .= $value[0] ? $value[0] . ' ' . $t[$index][$lang][$value[0] > 1 ? 1 : 0] . ' ' : '';
			//returns just an approximation given months and years!!!
			$out['seconds'] += $value[0] * $t[$index]['sec'];
		}
		return $out;
	}
}

class OutputFunctions{
	// create final processed output

	function debug($str, $where = "json"){
		// might come in handy once in a while, shows debugging info in developer console
			$this->sessionAttributes['DebugInfo'] = $str;
	}
		
	function answer(){
		global $AccessToken;
		global $debugger;
		global $post;

		$responseArray = [
			'version' => '1.0',
			'response' => [
				'outputSpeech' => ['type' => 'SSML', 'ssml' => '<speak>' . $this->speak . '</speak>'],
				'shouldEndSession' => $this->reprompt ? false : true //default setting because the certification staff nags all the time
			]
		];
		// tidy addition of properties in case they exist
		if ($this->reprompt) $responseArray['response']['reprompt'] =
			[
				'outputSpeech' => [
					'type' => 'SSML',
					'ssml' => '<speak>' . $this->reprompt . '</speak>'
				]
			];

		if ($this->card) $responseArray['response']['card'] = 
			[
				'type' => 'Standard',
				'title' =>$this->card->title,
				'image' => [
					'smallImageUrl' => $this->card->image,
					'largeImageUrl' => $this->card->image
				],
				'text' =>$this->card->text
			];

		if ($this->permission) $responseArray['response']['card']=$this->permission;

		//support for non APL enabled deviced
		if ($post->context->System->device->supportedInterfaces->Display && !$post->context->System->device->supportedInterfaces->Alexa.Presentation.APL && $this->display) $responseArray['response']['directives']=[
			[
				'type' => "Display.RenderTemplate",
				'template' => [
					'type' => $this->display->displaytemplate,
					'token' => $this->display->token,
					'title' => $this->display->title,
					/*'backgroundImage' => [  //works, but font-color ist not supported so it is of high risk to use light backgrounds
						'contentDescription' => 'Textured grey background',
						'sources' => [[
						'url' =>$this->display->bgimage
						]]
					],*/
					'image' => [
						'contentDescription' => 'icon',
						'sources' => [[
							'url' => $this->display->image
						]]
					],
					'listItems' => $this->display->items,
					'textContent' => ['primaryText' => ['type' => 'RichText', 'text' => $this->display->text]]
/*									'secondaryText' => ['text' =>"aber immerhin",'type' => 'PlainText'],
									'tertiaryText' => ['text' =>"aber immerhin",'type' => 'PlainText'],*/
					]
			],[
				'type' => 'Hint',
				'hint' => ['type' => 'PlainText', 'text' => $this->display->hint]
			]
		];

		//support for APL enabled deviced
		if ($post->context->System->device->supportedInterfaces->Display && $post->context->System->device->supportedInterfaces->Alexa.Presentation.APL && $this->display) {
			$responseArray['response']['directives']=[
				[
					'type' => "Alexa.Presentation.APL.RenderDocument",
					'token' => $this->display->token,
					'document' => [
						'type' => 'APL',
						'version' => '1.3',
						'theme' => 'light',
						'import' => [
							[
								'name' => 'alexa-layouts',
								'version' => '1.0.0'
							]
						],
						'styles' => [
							'textStyleBase' => [
								'description' => 'Base font description; set color',
								'values' => [
									[
										'color' => '#000000'
									]
								]
							],
							'textStyleBase0' => [
								'description' => 'Thin version of basic font',
								'extend' => 'textStyleBase',
								'values' => [
									'fontWeight' => '100'
								]
							],
							'textStyleBase1' => [
								'description' => 'Light version of basic font',
								'extend' => 'textStyleBase',
								'values' => [
									'fontWeight' => '300'
								]
							],
							'mixinBody' => [
								'values' => [
									'fontSize' => 27
								]
							],
							'mixinPrimary' => [
								'values' => [
									'fontSize' => 27
								]
							],
							'mixinSecondary' => [
								'values' => [
									'fontSize' => 23
								]
							],
							'textStylePrimary' => [
								'extend' => [
									'textStyleBase1',
									'mixinPrimary'
								]
							],
							'textStyleSecondary' => [
								'extend' => [
									'textStyleBase0',
									'mixinSecondary'
								]
							],
							'textStyleBody' => [
								'extend' => [
									'textStyleBase1',
									'mixinBody'
								]
							],
							'textStyleSecondaryHint' => [
								'values' => [
									'fontFamily' => 'Bookerly',
									'fontStyle' => 'italic',
									'fontSize' => 22
								]
							],
						],
						'onMount' => [],
						'graphics' => [],
						'commands' => [],
						'layouts' => [],
						'mainTemplate' => [
							'description' => $this->display->title != null ? $this->display->title : '',
							'parameters' => [
								'payload'
							],
							'items' => [
								[
									'type' => 'Container',
									'height' => '100vh',
									'width' => '100vw',
									'items' => []				
								]
							],
						],
					],
				]
			];

			if ($this->display->bgimage){
				array_push($responseArray['response']['directives'][0]['document']['mainTemplate']['items'][0]['items'],
					[
						'type' => 'Image',
						'scale' => 'best-fill',
						'width' => '100vw',
						'height' => '100vh',
						'position' => 'absolute',
						'source' => $this->display->bgimage,
					]);
			}
			if ($this->display->title || $this->display->skillogo){
				array_push($responseArray['response']['directives'][0]['document']['mainTemplate']['items'][0]['items'],
					[
						'when' => '${viewport.shape != \'round\'}',
						'type' => 'AlexaHeader',
						'headerTitle' => $this->display->title,
						'headerAttributionImage' => $this->display->skillogo,
					]);
			}


			if ($this->display->text){
				if ($this->display->image){
					$image =	[
							'when' => '${viewport.shape != \'round\'}',
							'type' => 'Image',
							'source' => $this->display->image,
							'scale' => 'best-fit',
							'width' => '35vw',
							'height' => '70vh',
							'align' => 'center',
							//'overlayColor' => 'rgba(0, 0, 0, 0.6)'
						];
				} else $image = false;
	
				array_push($responseArray['response']['directives'][0]['document']['mainTemplate']['items'][0]['items'],
					[
						'when' => '${viewport.shape != \'round\'}',
						'type' => 'Container',
						'direction' => 'row',
						'paddingLeft' => 40,
						'paddingRight' => 40,
						'grow' => 1,
						'items' => [
							$image,
							[
								'type' => 'ScrollView',
								'width' => ($image ? '60vw' : '100vw'),
								'height' => '70vh',
								'shrink' => 1,
								'item' => [ 
									[
										'type' => 'Container',
										'paddingLeft' => $image ? 40 : 0,
										'items' => [
											[
												'type' => 'Text',
												'text' => preg_replace('/\r\n/ms', '<br />', $this->display->text),
												'style' => 'textStylePrimary',
											]
										]
									]
								]
							]
						]
					]);
					array_push($responseArray['response']['directives'][0]['document']['mainTemplate']['items'][0]['items'],
					[
						'when' => '${viewport.shape == \'round\'}',
						'type' => 'Container',
						'direction' => 'column',
						'paddingTop' => 65,
						'paddingLeft' => 30,
						'paddingRight' => 30,
						'grow' => 1,
						'items' => [
							[
								'type' => 'ScrollView',
								'width' => '90vw',
								'height' => '70vh',
								'shrink' => 1,
								'item' => [ 
									[
										'type' => 'Container',
										'paddingLeft' => 30,
										'items' => [
											[
												'type' => 'Text',
												'text' => preg_replace('/\r\n/ms', '<br />', $this->display->text),
												'style' => 'textStylePrimary',
											]
										]
									]
								]
							]
						]
					]);
			}

			//display a list
			if ($this->display->items){
				array_push($responseArray['response']['directives'][0]['document']['mainTemplate']['items'][0]['items'],
					[
						'when' => '${viewport.shape != \'round\'}',
						'type' => 'Container',
						'height' => '70vh',
						'width' => '100vw',
						'items' => [
							[
								'type' => 'Sequence',
								'scrollDirection' => 'horizontal',
								'paddingLeft' => 60,
								'paddingRight' => 60,
								'data' => $this->display->items,
								'height' => '100%',
								'width' => '100%',
								'numbered' => true,
								'item' => [
									'type' => 'TouchWrapper',
									'onPress' => [
										'type' => 'SendEvent',
										'height' => '100%',
										'width' => '30%',
										'arguments' => [
											'${ordinal}',
											$this->sessionAttributes['SelectableReceipts']
										]
									],
									'item' => [
										'type' => 'Container',
										'maxWidth' => '30vw',
										'minWidth' => '30vw',
										'paddingLeft' => 0,
										'paddingRight' => 20,
										'height' => '100%',
										'items' => [
											[
												'type' => 'Image',
												'source' => '${data.image.sources[0].url}',
												'height' => '50vh',
												'width' => '100%'
											],
											[
												'type' => 'Text',
												'text' => '<b>${data.ordinalNumber}.</b> ${data.textContent.primaryText.text}',
												'style' => 'textStylePrimary',
												'maxLines' => 1,
												'spacing' => 12
											],
											[
												'type' => 'Text',
												'text' => '${data.textContent.secondaryText.text}',
												'style' => 'textStyleSecondary',
												'maxLines' => 1,
												'spacing' => 12
											],
										]
									]
								]
							]
						]
					]);
					array_push($responseArray['response']['directives'][0]['document']['mainTemplate']['items'][0]['items'],
					[
						'when' => '${viewport.shape == \'round\'}',
						'type' => 'Container',
						'height' => '100vh',
						'width' => '100vw',
						'items' => [
							[
								'type' => 'Sequence',
								'scrollDirection' => 'vertical',
								'paddingTop' => 30,
								'paddingLeft' => 60,
								'paddingRight' => 60,
								'data' => $this->display->items,
								'height' => '100vh',
								'width' => '100vw',
								'numbered' => true,
								'item' => [
									'type' => 'TouchWrapper',
									'onPress' => [
										'type' => 'SendEvent',
										'height' => '100%',
										'width' => '30%',
										'arguments' => [
											'${ordinal}',
											$this->sessionAttributes['SelectableReceipts']
										]
									],
									'item' => [
										[
											'type' => 'Container',
											'maxWidth' => '100%',
											'minWidth' => '100%',
											'paddingLeft' => 0,
											'paddingRight' => 0,
											'height' => '100%',
											'items' => [
												[
													'type' => 'Image',
													'source' => '${data.image.sources[0].url}',
													'height' => '50vh',
													'width' => '100%'
												],
												[
													'type' => 'Text',
													'text' => '<b>${data.ordinalNumber}.</b> ${data.textContent.primaryText.text}',
													'style' => 'textStylePrimary',
													'maxLines' => 1,
													'spacing' => 12,
													'style' => [
														'values' => [
															'fontSize' => 5
														]
													]
												],
												[
													'type' => 'Text',
													'text' => '${data.textContent.secondaryText.text}',
													'style' => 'textStyleSecondary',
													'maxLines' => 1,
													'spacing' => 12,
													'style' => [
														'values' => [
															'fontSize' => 5
														]
													]
												],
											]
										]
									]
								]
							]
						]
					]);
			}

			if ($this->display->hint){
				array_push($responseArray['response']['directives'][0]['document']['mainTemplate']['items'][0]['items'],
					[
						'when' => '${viewport.shape != \'round\'}',
						'type' => 'AlexaFooter',
						'style' => 'textStyleSecondaryHint',
						'hintText' => $this->display->hint
					]);
			}
		
		}

		if ($this->sessionAttributes) $responseArray['sessionAttributes']=$this->sessionAttributes;
		
		if ($debugger){ // dev-mode mysqli_connect-object for logging in- and output
			global $post;
			$debugger->query("INSERT INTO json_log VALUES ('',CURRENT_TIMESTAMP,'" . json_encode($post) . "','" . json_encode($responseArray) . "')");
		}

		header('Content-Type: application/json; Content-Length:' . strlen(json_encode($responseArray)) . '; Authorisation: Bearer ' . $AccessToken . '; Access-Control-Allow-Origin: *; Access-Control-Allow-Methods: GET');
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