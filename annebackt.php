<?php include ('alexabasics.php'); // basic functions library

include ('nonpublic.php'); // application ids, database connections, etc.

if ($ALEXA->verified($post, $rawpost, $AnneBacktAppId)){



//read slots
$num=$post->request->intent->slots->NUM->value;
$contains=$post->request->intent->slots->CONTAINS->value;
$receiptnumber=$post->request->intent->slots->RECEIPT_NUMBER->value;

include('../../asb/backend/project.library.php');

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
	elseif ($IntentName=="NEW_RECEIPTS" || $IntentName=="NEW_RECEIPT"){
		if ($IntentName=="NEW_RECEIPTS") $num=$num?:3;
		elseif ($IntentName=="NEW_RECEIPT") $num=1;
		$list=$mysqli->query("SELECT * FROM content WHERE timestamp<=UNIX_TIMESTAMP() ORDER BY timestamp DESC LIMIT ".$num);
		if ($list->num_rows>0) {
			if ($list->num_rows>1) $output='die neuesten '.$list->num_rows.' rezepte sind:';
			else $output='das neueste rezept ist:';
			while($entry = $list->fetch_assoc()){
				$output.= (++$items<$list->num_rows || $list->num_rows<2 ? ', ' : " und ").($list->num_rows>1?$ALEXA->number($items.'.'):'').' '.utf8_encode($entry['titel']).' vom '.$ALEXA->date(date('d.m',$entry['timestamp']));
				$id.=','.$entry['id'];
			}
			if ($list->num_rows>1) {
				$output.='. möchtest du eines der rezepte in deiner alexa-app angezeigt bekommen, sage die nummer.';
				$reprompt='möchtest du eines der rezepte 1 bis '.$items.' angezeigt bekommen?';
			}
			else {
				$output.='. möchtest du das rezept in deiner alexa-app angezeigt bekommen?';
				$reprompt='möchtest du das rezept angezeigt bekommen?';
			}
			$sessionAttributes=['SelectableReceipts'=>substr($id,1),'YesIntentConfirms'=>'showreceipt'];
		}
		else $output='leider konnte ich keine neuen rezepte finden';
	}
	elseif ($IntentName=="LOOKUP_RECEIPTS"){
		if ($contains){
			$list=$mysqli->query("SELECT * FROM content WHERE text LIKE '%".$contains."%' AND timestamp<=UNIX_TIMESTAMP() ORDER BY timestamp DESC");
			if ($list->num_rows) {
				$output='es gibt '.$list->num_rows.' rezepte mit '.$contains.': ';
				while($entry = $list->fetch_assoc()){
					$output.= (++$items<$list->num_rows || $list->num_rows<2 ? ', ' : " und ").($list->num_rows>1?$ALEXA->number($items.'.'):'').' '.utf8_encode($entry['titel']).' vom '.$ALEXA->date(date('d.m',$entry['timestamp']));
					$id.=','.$entry['id'];
				}
				if ($list->num_rows>1) {
					$output.='. möchtest du eines der rezepte in deiner alexa-app angezeigt bekommen, sage die nummer.';
					$reprompt='möchtest du eines der rezepte 1 bis '.$items.' angezeigt bekommen?';
				}
				else {
					$output.='. möchtest du das rezept in deiner alexa-app angezeigt bekommen?';
					$reprompt='möchtest du das rezept angezeigt bekommen?';
				}
				$sessionAttributes=['SelectableReceipts'=>substr($id,1),'YesIntentConfirms'=>'showreceipt'];
			}
			else{
				$output='leider konnte ich keine rezepte mit '.$contains.' finden';
				$reprompt='frag nach rezepten mit einer anderen zutat oder einfach nach den neuesten rezepten.';
			}
		}
		else {
			$output='ich habe dich leider nicht verstanden.';
			$reprompt='ich habe deine zutat nicht verstanden. frag nochmal oder einfach nach den neuesten rezepten.';
		}
	}
	elseif ($IntentName=="SELECT_RECEIPT" || ($IntentName=="AMAZON.YesIntent" && $post->session->attributes->YesIntentConfirms=="showreceipt")){
		$receiptnumber=$receiptnumber?:1;
		if ($receiptnumber && $post->session->attributes->SelectableReceipts){
			$which=explode(",",$post->session->attributes->SelectableReceipts);
			$entry=$mysqli->query("SELECT * FROM content WHERE id=".$which[$receiptnumber-1]." AND timestamp<=UNIX_TIMESTAMP() LIMIT 1")->fetch_assoc();

			preg_match('/\<img src=[\"\'](.*?)[\"\'].*?>/is',$entry['text'],$bild);
			if (!$bild[1]){
				$bild=array(0);
				$image_folder="../../asb/".$project->image_folder;
				if ($handle=opendir($image_folder)){
					$list=array();
					while (false !== ($file = readdir($handle))) {
						if (is_file($image_folder.$file) && $file!=".htaccess" && $file != "." && $file != ".." && substr($file,0,strpos($file,"_"))==$entry['id']) { 
							$bild[]=$image_folder.$file;
						}
					}
				} closedir($handle);
			}
			$image=($bild[1]?$bild[1]:"../../asb/design/icon256x256.png");
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
			$output='um dir das rezept per email zusenden zu können musst du für diesen skill in der alexa-app die freigabe zur verwendung deiner emailadresse erlauben.';
			$card=$ALEXA->askforemailpermission('Möchtest du Rezept-Links per eMail erhalten?');
			$reprompt='möchtest du noch andere rezepte angezeigt bekommen?';
		}
		else {
			$receiptnumber=$receiptnumber?:1;
			if ($receiptnumber && $post->session->attributes->SelectableReceipts){
				$which=explode(",",$post->session->attributes->SelectableReceipts);
				$entry=$mysqli->query("SELECT * FROM content WHERE id=".$which[$receiptnumber-1]." AND timestamp<=UNIX_TIMESTAMP() LIMIT 1")->fetch_assoc();
				$raw='Das Rezept f&uuml;r <strong>'.utf8_decode($entry['titel']).'</strong> findest du unter dem Link<br /><a href="http://annebackt.de/?permalink='.$entry['timestamp'].'">http://annebackt.de/?permalink='.$entry['timestamp'].'</a><br />'
				.'<br /><small>Du hast im Alexa-Skill die Freigabe zur Nutzung Deiner eMail-Adresse und zur Zusendung des Links erteilt.</small>';
				if (send_email('asb@annebackt.de', 'Anne backt via Alexa Skill', $usermail, 'Rezept für '.$entry['titel'], '', $raw, False,'annebackt.de')) $output='die email wurde versandt. kann ich sonst noch etwas für dich tun?';
				else $output='die mail konnte leider nicht versendet werden. versuche es später oder sag mir über annebackt.de bescheid. möchtest du andere rezepte zumindest angezeigt bekommen?';
			}
			else $output='ich weiß nicht welches rezept ich dir zusenden soll. frag mich nochmal!';
			$sessionAttributes=[];
			$reprompt='möchtest du noch andere rezepte angezeigt oder zugeschickt bekommen?';
		}
	}
	elseif ($IntentName=="SECRET"){
		$output=$ALEXA->whisper('ich habe gar kein geheimnis.').' ich backe ein bisschen liebe mit ein und lasse dem teig nur die zeit die er braucht. jetzt bist du dran!';
		$reprompt='du kannst das bestimmt auch. frag mich einfach nach meinen rezepten und probier eines aus. also?';
	}
	elseif ($IntentName=="CRITICISE"){
		$output=$ALEXA->interject('ey').'! wenn du vorschläge hast was anne backt noch können soll schreib mir eine email.';
		$reprompt='meine kontaktdaten findest du auf annebackt.de.';
	}
	elseif ($IntentName=="AMAZON.StopIntent"){
		$output='ich hoffe ich konnte helfen';
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
			."\"Was ist das neueste Rezept?\"\r\n \r\n"
			."Du kannst dir die Rezepte in der App anzeigen und per eMail zusenden lassen. "
			."Wenn es mehr als ein Rezept auf deine Frage hin gibt sage\r\n"
			."\"Zeige mir Rezept Nummer (z.B.) zwei.\" oder \r\n"
			."\"Schicke mir Rezept drei.\"\r\n \r\n"
			."Zur Nutzung der eMail-Funktion musst du dem Skill die Freigabe erteilen."
		];
		$reprompt='versuchs mal! frag mich nach dem neuesten rezept!';
	}
	elseif ($IntentName=="AMAZON.CancelIntent"){
		$output='ok. kann ich was anderes für dich tun?';
		$reprompt='was kann ich für dich tun?';
	}
}



$ALEXA->answer();
} else $ALEXA->verificationfailed();
?>