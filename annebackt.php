<?php include ('alexalibrary.php'); // basic functions library

include ('nonpublic.php'); // application ids, database connections, etc.

if ($ALEXA->verified($post, $rawpost, $AnneBacktAppId)){



//read slots
$num=$post->request->intent->slots->NUM->value;
$contains=$post->request->intent->slots->CONTAINS->value;
$receiptnumber=$post->request->intent->slots->RECEIPT_NUMBER->value;


include('../../asb/backend/project.library.php');
function getImage($id,$text){
	global $project;
	preg_match('/\<img src=[\"\'](.*?)[\"\'].*?>/is',$text,$bild);
	if (!$bild[1]){
		$bild=array(0);
		$image_folder="../../asb/".$project->image_folder;
		if ($handle=opendir($image_folder)){
			$list=array();
			while (false !== ($file = readdir($handle))) {
				if (is_file($image_folder.$file) && $file!=".htaccess" && $file != "." && $file != ".." && substr($file,0,strpos($file,"_"))==$id) { 
					$bild[]=$image_folder.$file;
				}
			}
		} closedir($handle);
	}
	return ($bild[1]?$bild[1]:"../../asb/design/icon256x256.png");
}





if ($post->request->type=="LaunchRequest"){
	$output='hallo bei anne backt. was kann ich für dich tun?';
	$reprompt=['outputSpeech'=>['type'=>'PlainText', 'text'=>'wenn du nicht weiter weißt frag nach hilfe.']];
}
elseif ($post->request->type=='SessionEndedRequest') {
	$output='dann bis bald. ich hoffe ich konnte helfen';
}
elseif ($post->request->type=="IntentRequest"){
	if ($IntentName=="DEFAULT" || $IntentName=="AMAZON.FallbackIntent"){
		$output='was kann ich für dich tun?';
		$reprompt=$ALEXA->interject('hey').'! wenn du nicht weiter weißt, frag nach hilfe.';
	}
	elseif ($IntentName=="NEW_RECEIPTS" || $IntentName=="NEW_RECEIPT" || $IntentName=="SURPRISE"){
		if ($IntentName=="NEW_RECEIPTS") $orderlimit ='ORDER BY timestamp DESC LIMIT '.($num?:3);
		elseif ($IntentName=="NEW_RECEIPT") $orderlimit ='ORDER BY timestamp DESC LIMIT 1';
		elseif ($IntentName=="SURPRISE") $orderlimit ='ORDER BY RAND() LIMIT 1';
		$list=$mysqli->query("SELECT * FROM content WHERE timestamp<=UNIX_TIMESTAMP()  ".$orderlimit);
		if ($list->num_rows>0) {
			if ($list->num_rows>1) $output='Die neuesten '.$list->num_rows.' Rezepte sind:';
			else {
				if ($IntentName=="NEW_RECEIPT") $output='Das neueste Rezept ist:';
				elseif ($IntentName=="SURPRISE") $output='wie wäre es mit:';
			}
			$t_display['title']=$output;
			while($entry = $list->fetch_assoc()){
				$output.= (++$items<$list->num_rows || $list->num_rows<2 ? ', ' : " und ").($list->num_rows>1?$ALEXA->number($items.'.'):'').' '.utf8_encode($entry['titel']).' vom '.$ALEXA->date(date('d.m',$entry['timestamp']));
				$id.=','.$entry['id'];

				$t_display['items'][]=[
					'token'=>$entry['id'],
					'image'=> ['contentDescription'=>'icon','sources'=>[['url'=>"https://armprothetik.info/assistant/sslmedia.php?".getImage($entry['id'],$entry['text'])]]		],
					'textContent'=>['primaryText'=>['text'=>utf8_encode($entry['titel']),'type'=>'PlainText'],
						//'secondaryText'=>['text'=>"aber immerhin",'type'=>'PlainText'],
						//'tertiaryText'=>['text'=>"aber immerhin",'type'=>'PlainText'],
					]
				];

			}
			if ($list->num_rows>1) {
				$output.='. möchtest du eines der rezepte in deiner alexa-app angezeigt bekommen, sage die nummer.';
				$reprompt='möchtest du eines der rezepte 1 bis '.$items.' angezeigt bekommen?';
			}
			else {
				$output.='. möchtest du das rezept in deiner alexa-app angezeigt bekommen?';
				$reprompt='möchtest du das rezept angezeigt bekommen?';
			}

			$display=[[
				'type'=> "Display.RenderTemplate",
				'template'=> [
					'type'=> 'ListTemplate2',
					'token'=> "neue_rezepte",
					'title'=> $t_display['title'],
					'listItems'=>$t_display['items']
				]
			],
			[
				'type'=>'Hint',
				'hint'=> ['type'=> 'PlainText', 'text'=>'Zeige Rezept Nummer 1.']
			]];
			$sessionAttributes=['SelectableReceipts'=>substr($id,1),'YesIntentConfirms'=>'showreceipt'];
		}
		else $output='leider konnte ich keine neuen rezepte finden';
	}
	elseif ($IntentName=="LOOKUP_RECEIPTS" || $IntentName=="LOOKUP_RECEIPTS_BY_TITLE"){
		if ($contains){
			$column=$IntentName=="LOOKUP_RECEIPTS"?'text':'titel';
			$list=$mysqli->query("SELECT * FROM content WHERE ".$column." LIKE '%".$contains."%' AND timestamp<=UNIX_TIMESTAMP() ORDER BY timestamp DESC");
			if ($list->num_rows) {
				$output=$IntentName=="LOOKUP_RECEIPTS"?
						'Es gibt '.$list->num_rows.' Rezepte mit '.ucfirst($contains).': ':
						'Es gibt '.$list->num_rows.' Rezepte für '.ucfirst($contains).': ';

				$t_display['title']=$output;
				while($entry = $list->fetch_assoc()){
					$output.= (++$items<$list->num_rows || $list->num_rows<2 ? ', ' : " und ").($list->num_rows>1?$ALEXA->number($items.'.'):'').' '.utf8_encode($entry['titel']).' vom '.$ALEXA->date(date('d.m',$entry['timestamp']));
					$id.=','.$entry['id'];

					$t_display['items'][]=[
						'token'=>$entry['id'],
						'image'=> ['contentDescription'=>'icon','sources'=>[['url'=>"https://armprothetik.info/assistant/sslmedia.php?".getImage($entry['id'],$entry['text'])]]		],
						'textContent'=>['primaryText'=>['text'=>utf8_encode($entry['titel']),'type'=>'PlainText'],
							//'secondaryText'=>['text'=>"aber immerhin",'type'=>'PlainText'],
							//'tertiaryText'=>['text'=>"aber immerhin",'type'=>'PlainText'],
						]
					];
	
					}
				if ($list->num_rows>1) {
					$output.='. möchtest du eines der rezepte in deiner alexa-app angezeigt bekommen, sage die nummer.';
					$reprompt='möchtest du eines der rezepte 1 bis '.$items.' angezeigt bekommen?';
				}
				else {
					$output.='. möchtest du das rezept in deiner alexa-app angezeigt bekommen?';
					$reprompt='möchtest du das rezept angezeigt bekommen?';
				}

				$display=[[
					'type'=> "Display.RenderTemplate",
					'template'=> [
						'type'=> 'ListTemplate2',
						'token'=> "zutaten_rezepte",
						'title'=> $t_display['title'],
						'listItems'=>$t_display['items']
					]
				],
				[
					'type'=>'Hint',
					'hint'=> ['type'=> 'PlainText', 'text'=>'Zeige Rezept Nummer 1.']
				]];

				$sessionAttributes=['SelectableReceipts'=>substr($id,1),'YesIntentConfirms'=>'showreceipt'];
			}
			else{
				$output='leider konnte ich keine rezepte mit '.$contains.' finden. frag nach rezepten mit einer anderen zutat oder einfach nach den neuesten rezepten.';
				$reprompt='frag nach rezepten mit einer anderen zutat oder einfach nach den neuesten rezepten.';
			}
		}
		else {
			$output='ich habe dich leider nicht verstanden.';
			$reprompt='ich habe deinen rezeptwunsch nicht verstanden. frag nochmal oder einfach nach den neuesten rezepten.';
		}
	}
	elseif ($IntentName=="SELECT_RECEIPT" || ($IntentName=="AMAZON.YesIntent" && $post->session->attributes->YesIntentConfirms=="showreceipt")){
		$receiptnumber=$receiptnumber?:1;
		if ($receiptnumber && $post->session->attributes->SelectableReceipts){
			$which=explode(",",$post->session->attributes->SelectableReceipts);
			$entry=$mysqli->query("SELECT * FROM content WHERE id=".$which[$receiptnumber-1]." AND timestamp<=UNIX_TIMESTAMP() LIMIT 1")->fetch_assoc();

			$image=getImage($entry['id'],$entry['text']);

			$output='das rezept für '.utf8_encode($entry['titel']).' wird in deiner alexa-app bei den aktivitäten angezeigt. möchtest du den link zu dem rezept per email zugesandt bekommen?';
			$card=['type'=>'Standard',
				'title'=>'Rezept für '.utf8_encode($entry['titel']),
				'image'=> [
					'smallImageUrl'=> "https://armprothetik.info/assistant/sslmedia.php?".$image,
					'largeImageUrl'=> "https://armprothetik.info/assistant/sslmedia.php?".$image
				],
				'text'=>utf8_encode($entry['text'])."\r\n \r\nEin Rezept von annebackt.de"
			];
			$reprompt='kann ich sonst noch etwas für dich tun?';
			$sessionAttributes=['SelectableReceipts'=>$entry['id'],'YesIntentConfirms'=>'sendreceipt'];
		}
		else $output='ich habe dich leider nicht verstanden.' ;
	}
	elseif ($IntentName=="SEND_RECEIPT" || ($IntentName=="AMAZON.YesIntent" && $post->session->attributes->YesIntentConfirms=="sendreceipt")){
		$usermail=$ALEXA->getemail($AccessToken);
		if (!is_string($usermail) || $usermail=='null') {
			$output='um dir das rezept per email zusenden zu können musst du für diesen skill in der alexa-app die freigabe zur verwendung deiner emailadresse erlauben. soll ich dir bis dahin weitere rezepte anzeigen?';
			$card=$ALEXA->askforemailpermission('Möchtest du Rezept-Links per eMail erhalten?');
			$reprompt='möchtest du noch andere rezepte angezeigt bekommen?';
			$sessionAttributes=['UnusedConfirmation'=>true];
		}
		else {
			$receiptnumber=$receiptnumber?:1;
			if ($receiptnumber && $post->session->attributes->SelectableReceipts){
				$which=explode(",",$post->session->attributes->SelectableReceipts);
				$entry=$mysqli->query("SELECT * FROM content WHERE id=".$which[$receiptnumber-1]." AND timestamp<=UNIX_TIMESTAMP() LIMIT 1")->fetch_assoc();
				$raw='Das Rezept f&uuml;r <strong>'.utf8_decode($entry['titel']).'</strong> findest du unter dem Link<br /><a href="http://annebackt.de/?permalink='.base_convert($entry['timestamp'],10,16).'">http://annebackt.de/?permalink='.base_convert($entry['timestamp'],10,16).'</a><br />'
				.'<br /><small>Du hast im Alexa-Skill die Freigabe zur Nutzung Deiner eMail-Adresse und zur Zusendung des Links erteilt.</small>';
				if (send_email('asb@annebackt.de', 'Anne backt via Alexa Skill', $usermail, 'Rezept für '.$entry['titel'], '', $raw, False,'annebackt.de')) $output='die email wurde versandt. kann ich sonst noch etwas für dich tun?';
				else $output='die mail konnte leider nicht versendet werden. versuche es später oder sag mir über annebackt.de bescheid. möchtest du andere rezepte zumindest angezeigt bekommen?';
			}
			else $output='ich weiß nicht welches rezept ich dir zusenden soll. frag mich nochmal!';
			$sessionAttributes=['UnusedConfirmation'=>true];
			$reprompt='möchtest du noch andere rezepte angezeigt oder zugeschickt bekommen?';
		}
	}
	elseif ($IntentName=="SECRET"){
		if ($post->request->intent->slots->TRICK->value){
			$output=$ALEXA->whisper('ich habe gar kein '.$post->request->intent->slots->TRICK->value.'.').' ich backe ein bisschen liebe mit ein und lasse dem teig nur die zeit die er braucht. jetzt bist '.$ALEXA->phoneme('du','\'duu').' dran! frag nach einem rezept und probiere es aus!';
			$reprompt='du kannst das bestimmt auch. frag mich einfach nach meinen rezepten und probier eines aus. also?';
		}
		else {
			$output='ich kann dir nicht folgen. frag nach einem rezept und probiere es aus!';
			$reprompt='frag mich einfach nach meinen rezepten und probier eines aus. also?';

		}
	}
	elseif ($IntentName=="CRITICISE"){
		$output=$ALEXA->interject('ey').'! wenn du vorschläge hast was anne backt noch können soll schreib mir eine email. meine kontaktdaten findest du auf annebackt.de.';
		//$reprompt='meine kontaktdaten findest du auf annebackt.de.';
	}
	elseif ($IntentName=="AMAZON.StopIntent"){
		$output='ich hoffe ich konnte helfen.';
	}
	elseif ($IntentName=="AMAZON.HelpIntent"){
		$output='dies ist ein skill der seite annebackt.de. stelle fragen wie: was sind die neuesten rezepte oder gibt es rezepte mit käse - wobei käse hier eine beliebige zutat ist. mehr optionen werden dir in der alexa-app angezeigt. versuchs mal!';
		$card=['type'=>'Standard',
			'title'=>'Was kann der Annebackt.de-Skill?',
			'image'=> [
				'smallImageUrl'=> "https://armprothetik.info/assistant/sslmedia.php?../../asb/design/icon256x256.png",
				'largeImageUrl'=> "https://armprothetik.info/assistant/sslmedia.php?../../asb/design/icon256x256.png"
			],
			'text'=>"Frag:\r\n\"Was gibt es neues?\"\r\n"
			."\"Was sind die neuesten (z.B. 5) Rezepte?\"\r\n"
			."\"Gibt es ein Rezept mit (Zutat)?\"\r\n"
			."\"Gibt es ein Rezept für (z.B.) Toast?\"\r\n"
			."\"Überrasch mich!\"\r\n"
			."\"Was ist das neueste Rezept?\"\r\n \r\n"
			."Du kannst dir die Rezepte in der App anzeigen und per eMail zusenden lassen. "
			."Wenn es mehr als ein Rezept auf deine Frage hin gibt sage\r\n"
			."\"Zeige mir Rezept Nummer (z.B.) zwei.\" oder \r\n"
			."\"Schicke mir Rezept drei.\"\r\n \r\n"
			."Zur Nutzung der eMail-Funktion musst du dem Skill die Freigabe erteilen."
		];
		$display=[[
			'type'=> "Display.RenderTemplate",
			'template'=> [
				'type'=> 'BodyTemplate3',
				'token'=> "hilfe",
				'backButton'=> "VISIBLE",
				'image'=> [
					'contentDescription'=>'icon',
					'sources'=>[[
						'url'=>"https://armprothetik.info/assistant/sslmedia.php?../../asb/design/icon256x256.png"
					]]
				],
				'title'=> 'Was kann der Annebackt.de-Skill?',
				'textContent'=>[
					'primaryText'=>['text'=>"<font size=\"3\">"
					."Frag:<br/>\"Was gibt es neues?\"<br/>"
					."\"Was sind die neuesten (z.B. 5) Rezepte?\"<br/>"
					."\"Gibt es ein Rezept mit (Zutat)?\"<br/>"
					."\"Gibt es ein Rezept für (z.B.) Toast?\"<br/>"
					."\"Überrasch mich!\"<br/>"
					."\"Was ist das neueste Rezept?\"<br/><br/>"
					."Du kannst dir die Rezepte in der App anzeigen und per eMail zusenden lassen. "
					."Wenn es mehr als ein Rezept auf deine Frage hin gibt sage<br/>"
					."\"Zeige mir Rezept Nummer (z.B.) zwei.\" oder <br/>"
					."\"Schicke mir Rezept drei.\"<br/><br/>"
					."Zur Nutzung der eMail-Funktion musst du dem Skill die Freigabe erteilen."
					."</font>",'type'=>'RichText'],
					//'secondaryText'=>['text'=>"aber immerhin",'type'=>'PlainText'],
					//'tertiaryText'=>['text'=>"aber immerhin",'type'=>'PlainText'],
				]
			]
		],
		[
			'type'=>'Hint',
			'hint'=> ['type'=> 'PlainText', 'text'=>'Hinweis']
		]];
		$reprompt='versuchs mal! frag mich nach dem neuesten rezept!';
	}
	elseif ($IntentName=="AMAZON.CancelIntent" || ($post->session->attributes->UnusedConfirmation && $IntentName=="AMAZON.YesIntent")){
		if ($post->session->attributes->UnusedConfirmation && $IntentName=="AMAZON.YesIntent"){
			$output='dann frag mich! oder nach hilfe.';
			$reprompt='was kann ich für dich tun?';
		}
		elseif ($post->session->attributes->previousCancel){
			$output='dann nicht. ich hoffe ich konnte helfen.';
		}
		else {
			$output='ok. kann ich was anderes für dich tun?';
			$reprompt='was kann ich für dich tun?';
			$sessionAttributes=['previousCancel'=>true];
		}
	}
}



$ALEXA->answer();
} else $ALEXA->verificationfailed();
?>