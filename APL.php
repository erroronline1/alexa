<?php
class BasicFunctions{
/*
 ___  ___  _ _     _ _  ___  ___  ___
| -_||   || | |   | | || .'||  _||_ -|
|___||_|_| \_/     \_/ |__,||_|  |___|

*/

	public $rawpost;
	public $post;
	public $AccessToken;
	public $IntentName;
	public $apiEndpoint;
	public $lang;

	function __construct($rawpost){
		$this->rawpost = $rawpost;
		//decode amazon query
		$this->post = json_decode($rawpost);
		//shortcut to typical session parameters
		$this->AccessToken = $this->post->context->System->apiAccessToken;
		$this->IntentName = $this->post->request->intent->name;
		$this->apiEndpoint = $this->post->context->System->apiEndpoint;
	}

/*
                _  ___  _            _    _
 _ _  ___  ___ |_||  _||_| ___  ___ | |_ |_| ___  ___
| | || -_||  _|| ||  _|| ||  _|| .'||  _|| || . ||   |
 \_/ |___||_|  |_||_|  |_||___||__,||_|  |_||___||_|_|

*/	function verified($applicationId){
	//verification as expected by amazon
		$head = getallheaders();
		$signature = openssl_x509_parse(file_get_contents($head['Signaturecertchainurl']));
		openssl_public_decrypt(base64_decode($head['Signature']), $decryptedSignature, openssl_pkey_get_public(file_get_contents($head['Signaturecertchainurl'])));
		return (
			( //verify Signaturecertchainurl
				preg_match("/https:\/\/s3.amazonaws.com(?:\:443){0,1}\/echo.api\/(?:..\/){0,1}echo-api-cert(?:.*?).pem/", $head['Signaturecertchainurl'])
			) &&
			( //verify Signature
				$signature['validFrom_time_t'] < time() && time() < $signature['validTo_time_t'] && stristr($signature['subject']['CN'], 'echo-api.amazon.com') && substr(bin2hex($decryptedSignature), 30) === sha1($this->rawpost)
			) &&
			( // verify timestamp of request
				strtotime($this->post->request->timestamp) >= time()-150
			) &&   
			( // verify application id - can be string or array (for example submission id and developer id)
				$this->post->session->application->applicationId == $applicationId
				|| in_array($this->post->session->application->applicationId, $applicationId)			
			)
		);
	} 

	function verificationfailed(){
	// insert code to run if the verification fails as expected by amazon
		header("HTTP/1.1 400 (Bad Request)");
	}

/*
                  _  _     _                _  _  _
 ___  _____  ___ |_|| |   | |_  ___  ___  _| || ||_| ___  ___
| -_||     || .'|| || |   |   || .'||   || . || || ||   || . |
|___||_|_|_||__,||_||_|   |_|_||__,||_|_||___||_||_||_|_||_  |
                                                         |___|
*/	// email handling
	function askforemailpermission($title='Für deine Anfrage ist deine eMail-Adresse erforderlich.'){
		return ['type' => 'AskForPermissionsConsent',
		'title' =>$title,
		'permissions' => [ "alexa::profile:email:read" ]
		];
	}
	function getemail(){
		// Create a stream
		$opts = [ 'http' => [	'method' => 'GET',
								'header' => "Accept: application/json\r\nAuthorization: Bearer " . $this->AccessToken . "\r\n"] ];
		$context = stream_context_create($opts);
		// Open the file using the HTTP headers set above
		return json_decode(file_get_contents($this->apiEndpoint . '/v2/accounts/~current/settings/Profile.email', false, $context));
	}
	function send_email($sendermail, $sendername, $mailto, $subject, $text, $replymail=False, $style=''){
		$host = $_SERVER['HTTP_HOST'];
		$header = "MIME-Version: 1.0\n"; 
		$header .= "Content-type: text/html; charset=iso-8859-1\n"; 
		$header .= "From:" . $sendername . (!$sendermail ? : "<" . $sendermail . ">") . "\n";
		if ($replymail) $header .= "Reply-To: ".$replymail."\n";
	 
		$content='<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\">
		<HTML><HEAD>
		<META http-equiv=Content-Type content=\"text/html; charset=utf-8\">
		<META content=\"MSHTML 6.00.2800.1505\" name=GENERATOR>
		<style>'.$style.'
		</style>
		</HEAD>
		<BODY>';
		$content .= "<h2>".$subject."</h2>";
		$content .= $text;
		return mail($mailto, "=?iso-8859-1?b?" . base64_encode(utf8_decode(html_entity_decode($subject))) . "?=", utf8_encode($content), $header);
	}
	
/*
                  _         _               _                _  _  _
 ___  ___  _____ |_| ___  _| | ___  ___    | |_  ___  ___  _| || ||_| ___  ___
|  _|| -_||     || ||   || . || -_||  _|   |   || .'||   || . || || ||   || . |
|_|  |___||_|_|_||_||_|_||___||___||_|     |_|_||__,||_|_||___||_||_||_|_||_  |
                                                                          |___|
*/	function askforreminderpermission($title='Für deine Anfrage ist deine Freigabe erforderlich.'){
		return [
			'type' => 'AskForPermissionsConsent',
			'title' => $title,
			'permissions' => [ "alexa::alerts:reminders:skill:readwrite" ]
		];
	}
	function askforreminderpermissionvoice(){
		return [
			'type' => 'Connections.SendRequest',
			'name' => 'AskFor',
			'payload' => [
				'@type' => 'AskForPermissionsConsentRequest',
				'@version' => '1',
				'permissionScope' => 'alexa::alerts:reminders:skill:readwrite'
			],
			'token' => ''
		];
	}
	function reminderconsent(){
		return $this->post->context->System->user->permissions->consentToken || false;
	}
	function getactivereminders(){
		// Create a stream
		$opts = [ 'http' => [	'method' => 'GET',
								'header' => "Accept: application/json\r\nAuthorization: Bearer " . $this->AccessToken . "\r\n"] ];
		$context = stream_context_create($opts);
		$reminders = json_decode(file_get_contents($this->apiEndpoint . '/v1/alerts/reminders', false, $context));
			
		if (!strstr($http_response_header[0], '1.1 200')) return false;
		$return = [0];
		foreach($reminders->alerts as $item => $reminder){
			if ($reminder->status == "ON") {
				$return[$reminder->alertInfo->spokenInfo->content[0]->text] = [
					'id' => $reminder->alertToken
				];
			}
		}
		return $return;
	}
	function deletereminder($id){
		// Create a stream
		$opts = [ 'http' => [	'method' => 'DELETE',
								'header' => "Accept: application/json\r\nAuthorization: Bearer " . $this->AccessToken . "\r\n"] ];
		$context = stream_context_create($opts);
		$return = json_decode(file_get_contents($this->apiEndpoint . '/v1/alerts/reminders/' . $id, false, $context));
		return $http_response_header;
	}
	function setrecurringreminder($setting, $requesttime){
		if ($setting && $requesttime){
			// note that amazon currently does not allow intervals less than 4 hours
			$recurrenceRules = [];
			$requesttime = strtotime($requesttime);
			$rulesOutput = [];
			$initial = new DateTime(strftime("%H%M%S", $requesttime));
			$back = new DateTime(strftime("%H%M%S", $requesttime));
			$forth = new DateTime(strftime("%H%M%S", $requesttime));
			
			// i consider this somehow ugly but it works and i can not think of a nicer solution. while the day number is the same
			// timespans will be subtracted and added, ends up in a dispensable item in both directions that has so be sanitized.
			// two dimensional array at first to determine the next reminder that the skill should provide according to certification
			function recurcontent($date){return 'FREQ=DAILY;BYHOUR=' . intval($date->format('H')) . ';BYMINUTE=' . intval($date->format('i')) . ';BYSECOND=' . intval($date->format('s')) . ';INTERVAL=1;';}
			//substract interval from requesttime
			while ($initial->format('D') == $back->format('D')){
				array_unshift($recurrenceRules, [recurcontent($back->sub(new DateInterval('PT' . $setting['interval'] . 'S'))), $back->getTimestamp()]);
			}
			//add current time
			array_push($recurrenceRules, [recurcontent($initial), $initial->getTimestamp()]);
			$nextreminder = false;
			//add interval from requesttime
			while ($initial->format('D') == $forth->format('D')){
				array_push($recurrenceRules, [recurcontent($forth->add(new DateInterval('PT' . $setting['interval'] . 'S'))), $forth->getTimestamp()]);
				if (!$nextreminder) $nextreminder = count($recurrenceRules);
			}
			//sanitize
			$recurrenceRules=array_slice($recurrenceRules, 1, -1);
			//determine next occurence
			if ($nextreminder > count($recurrenceRules)) $nextreminder = $recurrenceRules[0][1];
			else $nextreminder = $recurrenceRules[$nextreminder - 2][1];

			foreach($recurrenceRules as $item){
				array_push($rulesOutput, $item[0]);
			}

			$reminder = [
				'requestTime' => strftime("%Y-%m-%dT%H:%M:%S.000", $requesttime),
				'trigger' => [
						'type' => 'SCHEDULED_ABSOLUTE',
						'scheduledTime' => strftime('%Y-%m-%dT%H:%M:%S.000', $requesttime),
						//'timeZoneId' => 'America/Los_Angeles',
						'recurrence' => [
						  'startDateTime' => strftime('%Y-%m-%dT%H:%M:%S.000', $requesttime),
						  'endDateTime' => strftime('%Y-%m-%dT%H:%M:%S.000', $requesttime + $setting['duration']),
						  'recurrenceRules' => $rulesOutput
						]
					],
					'alertInfo' => [
						'spokenInfo' => [
							'content' => [[
								'locale' => $this->post->request->locale, 
								'text' => $setting['text'],
								'ssml' => $setting['ssml']
							]]
						]
					],
					'pushNotification'  => [                            
						'status'  => 'ENABLED'         
					]
			];
			// Create a stream
			$json = stripslashes(json_encode($reminder));
			$opts = [ 'http' => [	'method' => 'POST',
									'header' => "Content-length: " . strlen($json) . "\r\nAuthorization: Bearer " . $this->post->context->System->apiAccessToken . "\r\nContent-Type: application/json\r\n",
									'content' => $json] ];
			$context = stream_context_create($opts);
			$response = json_decode(file_get_contents($this->apiEndpoint . '/v1/alerts/reminders', false, $context));
			// ran from the developer console results in an error 403, only via reminder-enabled devices have a 201 status //
			return [$http_response_header[0], $nextreminder, $json];
		}
	}

	//some kind of <say-as time> for unix-timestamps
	function tellTime($unixtime, $lang='de'){
		$hour = date("G", $unixtime);
		$minute = intval(date("i", $unixtime));
		$format = [
			'de' => $hour . " Uhr " . $minute,
			'en' => $hour . ($minute < 10 ? ' o '. $minute : $minute)
		];
		return $format[$lang];
	}

	// returns ISO-8601 interval format (PnYnMnDTnHnMnS) resolved for speech and in seconds
	function resolveInterval($interval, $lang = 'de'){
		$t = [
			'fullmatch' => [],
			'PY' => ['de' => ['Jahr', 'Jahre'], 'en' => ['year', 'years'], 'sec' => 3600*24*365],
			'PM' => ['de' => ['Monat', 'Monate'], 'en' => ['month', 'months'], 'sec' => 3600*24*30],
			'PD' => ['de' => ['Tag', 'Tage'], 'en' => ['day', 'days'], 'sec' => 3600*24],
			'TH' => ['de' => ['Stunde', 'Stunden'], 'en' => ['hour', 'hours'], 'sec' => 3600],
			'TM' => ['de' => ['Minute', 'Minuten'], 'en' => ['minute', 'minutes'], 'sec' => 60],
			'TS' => ['de' => ['Sekunde', 'Sekunden'], 'en' => ['second', 'seconds'], 'sec' => 1],
		];
		preg_match_all('/P(?>(\d+)Y)?(?>(\d+)M)?(?>(\d+)D)?T(?>(\d+)H)?(?>(\d+)M)?(?>(\d+)S)?/is', $interval, $matches);
		foreach ($matches as $index => $value){
			if ($index < 4 ) continue; //ignore years, months and days for now
			$out['speech'] .= $value[0] ? $value[0] . ' ' . $t[array_keys($t)[$index]][$lang][$value[0] > 1 ? 1 : 0] . ' ' : '';
			//returns just an approximation given months and years!!!
			$out['seconds'] += $value[0] * $t[array_keys($t)[$index]]['sec'];
		}
		return $out;
	}

/*
                  _
 ___  ___  _____ | |
|_ -||_ -||     || |
|___||___||_|_|_||_|

*/	// ssml effects
	function whisper($text){ return '<amazon:effect name="whispered">' . $text . '</amazon:effect>'; }
	function emphase($text){ return '<emphasis level="strong">' . $text . '</emphasis>'; }
	function date($text){ return '<say-as interpret-as="date" format="dmy">' . $text . '</say-as>'; }
	function spell($text){ return '<say-as interpret-as="characters">' . $text . '</say-as>'; }
	function number($text){ return '<say-as interpret-as="number">' . $text . '</say-as>'; }
	function interject($interjection){ return'<say-as interpret-as="interjection">' . $interjection . '</say-as>';}
	function phoneme($text, $phonetic){ return'<phoneme alphabet="ipa" ph="' . $phonetic . '">' . $text . '</phoneme>';}
}

class OutputFunctions{

	var $display;
	var $card;

	function __construct($post, $AccessToken){
		$this->post = $post;
		$this->AccessToken = $AccessToken;
		$this->display = (object)[
			'bgimage' => '',
			'title' => '',
			'text' => '',
			'hint' => '',
			'displaytemplate' => '',
			'token' => '',
			'items' => '',
			'image' => '',
			'skilllogo' => '',
			'styles' => [],
			'resources' => []
		];
		$this->card = (object)[
			'title' => '',
			'text' => '',
			'image' => '',
			'subtext' => '',
		];
	}

	function debug($str, $where = "json"){
		// might come in handy once in a while, shows debugging info in developer console
			$this->sessionAttributes['DebugInfo'] = $str;
	}
		
	function answer(){
		global $debugger;

/*
                          _
 ___  ___  ___  ___  ___ | |_
|_ -|| . || -_|| -_||  _||   |
|___||  _||___||___||___||_|_|
     |_|

*/		$responseArray = [
			'version' => '1.0',
			'response' => [
				'outputSpeech' => ['type' => 'SSML', 'ssml' => '<speak>' . $this->speak . '</speak>'],
				'shouldEndSession' => $this->reprompt ? false : true // default setting because the certification staff nags all the time
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

/*
                  _
 ___  ___  ___  _| |
|  _|| .'||  _|| . |
|___||__,||_|  |___|

*/		if ($this->card->image) $responseArray['response']['card'] = 
			[
				'type' => 'Standard',
				'title' => $this->card->title,
				'image' => [
					'smallImageUrl' => $this->card->image,
					'largeImageUrl' => $this->card->image
				],
				'text' => $this->card->text . "\n\n" . $this->card->subtext
			];

		if ($this->permission) $responseArray['response']['card']=$this->permission;

/*
                               _                                   _
 ___  ___  ___  ___  ___  ___ | |    ___  ___  ___  ___  ___  ___ | |
|   || . ||   ||___|| .'|| . || |   | . || -_||   || -_||  _|| .'|| |
|_|_||___||_|_|     |__,||  _||_|   |_  ||___||_|_||___||_|  |__,||_|
                         |_|        |___|

*/		// support for non APL enabled deviced
		if ($this->post->context->System->device->supportedInterfaces->Display && !$this->post->context->System->device->supportedInterfaces->{'Alexa.Presentation.APL'} && $this->display) $responseArray['response']['directives']=[
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
					'textContent' => [
						'primaryText' => ['type' => 'RichText', 'text' => $this->display->text],
						'secondaryText' => ['text' =>$this->display->subtext],'type' => 'PlainText'],
						/*'tertiaryText' => ['text' =>"whatever",'type' => 'PlainText'],*/
					]
			],[
				'type' => 'Hint',
				'hint' => ['type' => 'PlainText', 'text' => $this->display->hint]
			]
		];

/*
           _                                   _
 ___  ___ | |    ___  ___  ___  ___  ___  ___ | |
| .'|| . || |   | . || -_||   || -_||  _|| .'|| |
|__,||  _||_|   |_  ||___||_|_||___||_|  |__,||_|
     |_|        |___|

*/		//support for APL enabled devices
		if ($this->post->context->System->device->supportedInterfaces->Display && $this->post->context->System->device->supportedInterfaces->{'Alexa.Presentation.APL'} && $this->display) {
			
			$styles = $this->display->styles != null ? $this->display->styles : [
				'textStylePrimary' => [
					'values' => [
						'color' => '#ffffff',
						'fontSize' => 25,
						'fontWeight' => 100
					]
				],
				'textStyleSecondary' => [
					'values' => [
						'color' => '#ffffff',
						'fontSize' => 20,
						'fontWeight' => 100
					]
				],
				'customHeader' => [
					'values' => [
						'color' => '#ffffff',
						'fontSize' => 27,
						'backgroundColor' => '',
						'textAlignVertical' => 'center'
						]
				],
				'customHint' => [
					'values' => [
						'color' => '#ffffff',
						'fontFamily' => 'Bookerly',
						'fontStyle' => 'italic',
						'fontSize' => 22,
						'textAlignVertical' => 'bottom'
					]
				]
			];
			$resources = $this->display->resources != null ? $this->display->resources : [
				[
					'dimensions' => [
						'headerHeight' => '10vh',
						'bodyHeight' => '80vh',
						'bodyPaddingTopBottom' => 16,
						'bodyPaddingLeftRight' => 32
					]
				]
			];
			
			
			$responseArray['response']['directives'] = [
				[
					'type' => "Alexa.Presentation.APL.RenderDocument",
					'token' => $this->display->token,
					'document' => [
						'type' => 'APL',
						'version' => '1.3',
						'theme' => 'dark',
						'import' => [
							[
								'name' => 'alexa-layouts',
								'version' => '1.0.0'
							]
						],
						'styles' => $styles,
						'resources' => $resources,
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

/*
 _              _                               _       _
| |_  ___  ___ | |_  ___  ___  ___  _ _  ___  _| | ___ |_| _____  ___  ___  ___ 
| . || .'||  _|| '_|| . ||  _|| . || | ||   || . ||___|| ||     || .'|| . || -_|
|___||__,||___||_,_||_  ||_|  |___||___||_|_||___|     |_||_|_|_||__,||_  ||___|
                    |___|                                             |___|
*/			if ($this->display->bgimage){
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

/*
                _                   _                _
 ___  _ _  ___ | |_  ___  _____    | |_  ___  ___  _| | ___  ___
|  _|| | ||_ -||  _|| . ||     |   |   || -_|| .'|| . || -_||  _|
|___||___||___||_|  |___||_|_|_|   |_|_||___||__,||___||___||_|

*/			if ($this->display->title || $this->display->skillogo){
				array_push($responseArray['response']['directives'][0]['document']['mainTemplate']['items'][0]['items'],
					[
					'type' => 'Frame',
					'height' => '@headerHeight',
					'width' => '100vw',
					'style' => 'customHeader',
					'items' => [
						[
							'type' => 'Container',
							'height' => '@headerHeight',
							'width' => '100vw',
							'direction' => 'row',
							'style' => 'customHeader',
							'items' => [
									[
										'when' => '${viewport.shape != \'round\'}',
										'type' => 'Text',
										'text' => $this->display->title,
										'style' => 'customHeader',
										'maxLines' => 1,
										'height' => '100%',
										'width' => '95vw',
										'paddingLeft' => 16
									],
									[
										'when' => '${viewport.shape != \'round\'}',
										'type' => 'Image',
										'height' => '100%',
										'width' => '5vw',
										'paddingRight' => 16,
										'paddingTop'=> 8,
										'scale' => 'best-fit',
										'align' => 'center',
										'source' => $this->display->skillogo
									],
									[
										'when' => '${viewport.shape == \'round\'}',
										'type' => 'Image',
										'height' => '100%',
										'width' => '100vw',
										'paddingTop'=> 4,
										'scale' => 'best-fit',
										'align' => 'center',
										'source' => $this->display->skillogo
									]
								]
							]]
						]);
			}

/*			
                _                   _    _       _
 ___  _ _  ___ | |_  ___  _____    | |_ |_| ___ | |_
|  _|| | ||_ -||  _|| . ||     |   |   || ||   ||  _|
|___||___||___||_|  |___||_|_|_|   |_|_||_||_|_||_|

*/			// placed before content to have it positioned below a possible touch layer
			if ($this->display->hint){
				array_push($responseArray['response']['directives'][0]['document']['mainTemplate']['items'][0]['items'],
				[
					'when' => '${viewport.shape != \'round\'}',
					'height' => '100vh',
					'width' => '100vw',
					'position' => 'absolute',
					'style' => 'customHint',
					'paddingLeft' => 16,
					'paddingBottom' => 16,
					'type' => 'Text',
					'text' => $this->display->hint,
					'maxLines' => 1
				]);
			}

/*
 _              _              _  _    _       _
| |_  ___  _ _ | |_     _ _ _ |_|| |_ | |_    |_| _____  ___  ___  ___
|  _|| -_||_'_||  _|   | | | || ||  _||   |   | ||     || .'|| . || -_|
|_|  |___||_,_||_|     |_____||_||_|  |_|_|   |_||_|_|_||__,||_  ||___|
                                                             |___|
*/			if ($this->display->text){

				if ($this->display->image){
					$image = [
							'type' => 'Image',
							'source' => $this->display->image,
							'scale' => 'best-fit',
							'width' => '35vw',
							'height' => '@bodyHeight',
							'align' => 'center',
						];
				} else $image = false;
	
				if ($this->display->subtext){
					$subtext= [
						'type' => 'Text',
						'text' => preg_replace('/\r\n/ms', '<br />', $this->display->subtext),
						'style' => 'textStyleSecondary',
					];
				} else $subtext = false;

				array_push($responseArray['response']['directives'][0]['document']['mainTemplate']['items'][0]['items'],
					[
						'when' => '${viewport.shape != \'round\'}',
						'type' => 'Container',
						'direction' => 'row',
						'paddingLeft' => '@bodyPaddingLeftRight',
						'paddingRight' => '@bodyPaddingLeftRight',
						'paddingTop' => '@bodyPaddingTopBottom',
						'paddingBottom' => '@bodyPaddingTopBottom',
						'height' => '@bodyHeight',
						'width' => '100vw',
						'grow' => 1,
						'items' => [
							$image,
							[
								'type' => 'ScrollView',
								'width' => ($image ? '60vw' : '100vw'),
								'height' => '@bodyHeight',
								'shrink' => 1,
								'item' => [ 
									[
										'type' => 'Container',
										'paddingLeft' => $image ? '@bodyPaddingLeftRight' : 0,
										'items' => [
											[
												'type' => 'Text',
												'text' => preg_replace('/\r\n/ms', '<br />', $this->display->text),
												'style' => 'textStylePrimary',
											],
											$subtext
										]
									]
								]
							]
						]
					]);

					if ($image) { $image['width'] = '75vw';}
					if ($this->display->title){
						$title = [
								'type' => 'Text',
								'text' => $this->display->title,
								'style' => 'customHeader',
								'width' => '90vw',
							];
					} else $title = false;

					array_push($responseArray['response']['directives'][0]['document']['mainTemplate']['items'][0]['items'],
					[
						'when' => '${viewport.shape == \'round\'}',
						'type' => 'Container',
						'direction' => 'column',
						'paddingTop' => '@bodyPaddingTopBottom',
						'paddingLeft' => '@bodyPaddingLeftRight',
						'paddingRight' => '@bodyPaddingLeftRight',
						'grow' => 1,
						'items' => [
							[
								'type' => 'ScrollView',
								'width' => '90vw',
								'height' => '85vh',
								'shrink' => 1,
								'item' => [
									[
										'type' => 'Container',
										'paddingLeft' => '@bodyPaddingLeftRight',
										'paddingRight' => '@bodyPaddingLeftRight',
										'items' => [
											$title,
											$image,
											[
												'type' => 'Text',
												'text' => preg_replace('/\r\n/ms', '<br />', $this->display->text),
												'style' => 'textStylePrimary',
												'paddingBottom' => '20vh',
											]
										]
									]
								]
							]
						]
					]);
			}

/*
 _  _       _
| ||_| ___ | |_
| || ||_ -||  _|
|_||_||___||_|

*/			if ($this->display->items){
				array_push($responseArray['response']['directives'][0]['document']['mainTemplate']['items'][0]['items'],
					[
						'when' => '${viewport.shape != \'round\'}',
						'type' => 'Container',
						'height' => '@bodyHeight',
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
										'width' => '30vw',
										'paddingLeft' => 0,
										'paddingRight' => 20,
										'height' => '@bodyHeight',
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
						'width' => '90vw',
						'items' => [
							[
								'type' => 'Sequence',
								'scrollDirection' => 'vertical',
								'paddingTop' => '@bodyPaddingTopBottom',
								'paddingLeft' => '@bodyPaddingLeftRight',
								'paddingRight' => '@bodyPaddingLeftRight',
								'paddingBottom' => '20vh',
								'data' => $this->display->items,
								'height' => '85vh',
								'width' => '90vw',
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
											'width' => '75vw',
											'paddingTop' => '@bodyPaddingTopBottom',
											'paddingLeft' => '@bodyPaddingLeftRight',
											'paddingRight' => '@bodyPaddingLeftRight',
											'items' => [
												[
													'type' => 'Image',
													'source' => '${data.image.sources[0].url}',
													'width' => '75vw',
													'height' => '65vh',
													'scale' => 'best-fit',
													'align' => 'center',
												],
												[
													'type' => 'Text',
													'text' => '<b>${data.ordinalNumber}.</b> ${data.textContent.primaryText.text}',
													'style' => 'textStylePrimary',
													'maxLines' => 1,
													'spacing' => 12,
												],
												[
													'type' => 'Text',
													'text' => '${data.textContent.secondaryText.text}',
													'style' => 'textStyleSecondary',
													'maxLines' => 1,
													'spacing' => 12,
												],
											]
										]
									]
								]
							]
						]
					]);
			}
			if ($this->voicepermission) {
				array_push($responseArray['response']['directives'], $this->voicepermission);
			}
		}

		if ($this->sessionAttributes) $responseArray['sessionAttributes']=$this->sessionAttributes;
		
		if ($debugger){ // dev-mode mysqli_connect-object for logging in- and output
			$debugger->query("INSERT INTO json_log VALUES ('',CURRENT_TIMESTAMP,'" . json_encode($this->post) . "','" . json_encode($responseArray) . "')");
		}

		header('Content-Type: application/json; Content-Length:' . strlen(json_encode($responseArray)) . '; Authorisation: Bearer ' . $this->AccessToken . '; Access-Control-Allow-Origin: *; Access-Control-Allow-Methods: GET');
		echo json_encode($responseArray);
	}
}

$ALEXA = new BasicFunctions(file_get_contents('php://input')); //initialize basic functions
$OUTPUT = new OutputFunctions($ALEXA->post, $ALEXA->AccessToken); //initialize output functions

$OUTPUT->speak = "was kann ich für dich tun?"; // in case i forgot to initialize the variable
?>