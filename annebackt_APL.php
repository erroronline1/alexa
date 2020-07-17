<?php include ('APL.php'); // basic functions library

include ('nonpublic.php'); // application ids, database connections, etc.

if ($ALEXA->verified($AnneBacktAppId)){

//read slots
$num = $ALEXA->post->request->intent->slots->NUM->value;
$contains = $ALEXA->post->request->intent->slots->CONTAINS->value;
$receiptnumber = $ALEXA->post->request->intent->slots->RECEIPT_NUMBER->value;


include('../../asb/backend/project.library.php');
function getImage($id,$text){
	global $project;
	preg_match('/\<img src=[\"\'](.*?)[\"\'].*?>/is', $text, $bild);
	if (!$bild[1]){
		$bild = array(0);
		$image_folder = "../../asb/".$project->image_folder;
		if ($handle = opendir($image_folder)){
			$list = array();
			while (false !== ($file = readdir($handle))) {
				if (is_file($image_folder.$file) && $file != ".htaccess" && $file != "." && $file != ".." && substr($file, 0, strpos($file, "_")) == $id) { 
					$bild[]=$image_folder.$file;
				}
			}
		} closedir($handle);
	}
	return ($bild[1] ? $bild[1] : "../../asb/design/icon192x192.png");
}

function getArticle($id){
	global $mysqli;
	$entry = $mysqli->query("SELECT * FROM content WHERE id=" . $id . " AND timestamp<=UNIX_TIMESTAMP() LIMIT 1")->fetch_assoc();
	$image = getImage($entry['id'], $entry['text']);
	return ['title' => utf8_encode($entry['titel']),
			'image' => 'https://erroronline.one/column4/sslmedia.php?' . $image,
			'text' => utf8_encode($entry['text']) . "\r\n \r\nEin Rezept von annebackt.de",
			'timestamp' => $entry['timestamp']];
}

$stardardText='Frag:<br />"Was gibt es neues?"<br />'
	.'"Was sind die neuesten (z.B. 5) Rezepte?"<br />'
	.'"Gibt es ein Rezept mit (Zutat)?"<br />'
	.'"Gibt es ein Rezept für (z.B.) Toast?"<br />'
	.'"Überrasch mich!"<br />'
	.'"Was ist das neueste Rezept?"<br /><br />'
	.'Du kannst auch nach <em>Hilfe</em> fragen';


//sort of translate event from touch to invocation
//currently for touch selection of list element only
if ($ALEXA->post->request->source->type =='TouchWrapper'){

	$ALEXA->post->request->type = "IntentRequest";
	$ALEXA->IntentName = 'SELECT_RECEIPT';
	$receiptnumber = $ALEXA->post->request->arguments[0];
	$ALEXA->post->session->attributes->SelectableReceipts = $ALEXA->post->request->arguments[1];
}


if ($ALEXA->post->request->type == "LaunchRequest"){
	$OUTPUT->speak = 'willkommen bei anne backt. was kann ich für dich tun?';
	$OUTPUT->reprompt = 'wenn du nicht weiter weißt frag nach hilfe.';
	$OUTPUT->display->title = 'Willkommen bei Anne backt';
	$OUTPUT->display->text = $stardardText;
	$OUTPUT->display->hint = 'versuche "was gibt es neues?"';
}
elseif ($ALEXA->post->request->type == 'SessionEndedRequest') {
	$OUTPUT->speak = 'dann bis bald. ich hoffe ich konnte helfen.';
}
elseif ($ALEXA->post->request->type == "IntentRequest"){
	if ($ALEXA->IntentName == "DEFAULT" || $ALEXA->IntentName == "AMAZON.FallbackIntent"){
		$OUTPUT->speak = 'was kann ich für dich tun?';
		$OUTPUT->reprompt = $ALEXA->interject('hey').'! wenn du nicht weiter weißt, frag nach hilfe.';
	}
	elseif ($ALEXA->IntentName == "NEW_RECEIPTS" || $ALEXA->IntentName == "NEW_RECEIPT" || $ALEXA->IntentName == "SURPRISE"){
		if ($ALEXA->IntentName == "NEW_RECEIPTS") $orderlimit = 'ORDER BY timestamp DESC LIMIT ' . ($num?:3);
		elseif ($ALEXA->IntentName == "NEW_RECEIPT") $orderlimit = 'ORDER BY timestamp DESC LIMIT 1';
		elseif ($ALEXA->IntentName == "SURPRISE") $orderlimit = 'ORDER BY RAND() LIMIT 1';
		$list=$mysqli->query("SELECT * FROM content WHERE timestamp<=UNIX_TIMESTAMP() " . $orderlimit);
		if ($list->num_rows > 0) {
			if ($list->num_rows > 1) $OUTPUT->speak = 'Die neuesten '.$list->num_rows.' Rezepte sind:';
			else {
				if ($ALEXA->IntentName == "NEW_RECEIPT") $OUTPUT->speak = 'Das neueste Rezept ist:';
				elseif ($ALEXA->IntentName == "SURPRISE") $OUTPUT->speak = 'wie wäre es mit:';
			}
			$t_display['title'] = $OUTPUT->speak;
			while($entry = $list->fetch_assoc()){
				$OUTPUT->speak .= (++$items < $list->num_rows || $list->num_rows < 2 ? ', ' : " und ").($list->num_rows > 1 ? $ALEXA->number($items.'.') : '') . ' ' . utf8_encode($entry['titel']) . ' vom ' . $ALEXA->date(date('d.m.y', $entry['timestamp']));
				$id.=','.$entry['id'];

				$t_display['items'][]=[
					'token' => $entry['id'],
					'listItemIdentifier' => $entry['id'],
					'ordinalNumber' => $items,
					'image' => ['contentDescription' => 'icon','sources' => [['url' => 'https://erroronline.one/column4/sslmedia.php?' . getImage($entry['id'], $entry['text'])]]		],
					'textContent' => ['primaryText' => ['text' => utf8_encode(preg_replace('/\//msi', '$0 ', $entry['titel'])), 'type' => 'PlainText'],
									'secondaryText' =>['text' =>date('d.m.Y', $entry['timestamp']),'type' =>'PlainText'],
					]
				];

			}
			if ($list->num_rows > 1) {
				$OUTPUT->speak .= '. möchtest du eines der rezepte angezeigt bekommen, sage die nummer.';
				$OUTPUT->reprompt = 'möchtest du eines der rezepte 1 bis '.$items.' angezeigt bekommen?';
			}
			else {
				$OUTPUT->speak .= '. möchtest du das rezept angezeigt bekommen?';
				$OUTPUT->reprompt = 'möchtest du das rezept in deiner alexa-app angezeigt bekommen?';
			}

			$OUTPUT->display->displaytemplate = 'ListTemplate2';
			$OUTPUT->display->token = 'neue_rezepte';
			$OUTPUT->display->title = $t_display['title'];
			$OUTPUT->display->items = $t_display['items'];
			$OUTPUT->display->hint = 'sage zum Beispiel "zeige Rezept Nummer ' . random_int(1, count($t_display['items'])) . '"';

			$OUTPUT->sessionAttributes = ['SelectableReceipts' => substr($id, 1), 'YesIntentConfirms' => 'showreceipt'];
		}
		else $OUTPUT->speak = 'leider konnte ich keine neuen rezepte finden';
	}
	elseif ($ALEXA->IntentName == "LOOKUP_RECEIPTS" || $ALEXA->IntentName == "LOOKUP_RECEIPTS_BY_TITLE"){
		if ($contains){
			$column = $ALEXA->IntentName == 'LOOKUP_RECEIPTS' ? 'text' : 'titel';
			$list=$mysqli->query("SELECT * FROM content WHERE " . $column . " LIKE '%" . $contains . "%' AND timestamp<=UNIX_TIMESTAMP() ORDER BY timestamp DESC");
			if ($list->num_rows) {
				$OUTPUT->speak = $ALEXA->IntentName == 'LOOKUP_RECEIPTS'?
						'Es gibt ' . $list->num_rows . ' Rezepte mit ' . ucfirst($contains) . ': ':
						'Es gibt ' . $list->num_rows . ' Rezepte für ' . ucfirst($contains) . ': ';

				$t_display['title'] = $OUTPUT->speak;
				while($entry = $list->fetch_assoc()){
					$OUTPUT->speak .= (++$items < $list->num_rows || $list->num_rows < 2 ? ', ' : " und ") . ($list->num_rows > 1 ? $ALEXA->number($items . '.') : '') . ' ' . utf8_encode($entry['titel']) . ' vom ' . $ALEXA->date(date('d.m,Y', $entry['timestamp']));
					$id .= ',' . $entry['id'];

					$t_display['items'][] = [
						'token' => $entry['id'],
						'listItemIdentifier' => $entry['id'],
						'ordinalNumber' => $items,
						'image' => ['contentDescription' => 'icon', 'sources' => [['url' => 'https://erroronline.one/column4/sslmedia.php?' . getImage($entry['id'], $entry['text'])]]],
						'textContent' => ['primaryText' => ['text' => utf8_encode(preg_replace('/\//msi', '$0 ', $entry['titel'])), 'type' => 'PlainText'],
										'secondaryText' =>['text' =>date('d.m.Y', $entry['timestamp']),'type' =>'PlainText'],
						]
					];
	
					}
				if ($list->num_rows > 1) {
					$OUTPUT->speak .= '. möchtest du eines der rezepte angezeigt bekommen, sage die nummer.';
					$OUTPUT->reprompt = 'möchtest du eines der rezepte 1 bis ' . $items . ' angezeigt bekommen?';
				}
				else {
					$OUTPUT->speak .= '. möchtest du das rezept angezeigt bekommen?';
					$OUTPUT->reprompt = 'möchtest du das rezept in deiner alexa-app angezeigt bekommen?';
				}

				$OUTPUT->display->displaytemplate = 'ListTemplate2';
				$OUTPUT->display->token = 'zutaten_rezepte';
				$OUTPUT->display->title = $t_display['title'];
				$OUTPUT->display->items = $t_display['items'];
				$OUTPUT->display->hint = 'sage zum Beispiel "zeige Rezept Nummer ' . random_int(1, count($t_display['items'])) . '"';

				$OUTPUT->sessionAttributes = ['SelectableReceipts' => substr($id, 1), 'YesIntentConfirms' => 'showreceipt'];
			}
			else{
				$OUTPUT->speak = 'leider konnte ich keine rezepte mit ' . $contains . ' finden. frag nach rezepten mit einer anderen zutat oder einfach nach den neuesten rezepten.';
				$OUTPUT->reprompt = 'frag nach rezepten mit einer anderen zutat oder einfach nach den neuesten rezepten.';
			}
		}
		else {
			$OUTPUT->speak = 'ich habe dich leider nicht verstanden.';
			$OUTPUT->reprompt = 'ich habe deinen rezeptwunsch nicht verstanden. frag nochmal oder einfach nach den neuesten rezepten.';
		}
	}
	elseif ($ALEXA->IntentName == "SELECT_RECEIPT" || ($ALEXA->IntentName == "AMAZON.YesIntent" && $ALEXA->post->session->attributes->YesIntentConfirms == "showreceipt")){
		$receiptnumber = $receiptnumber ? : 1;
		if ($receiptnumber && $ALEXA->post->session->attributes->SelectableReceipts){
			$which = explode(",", $ALEXA->post->session->attributes->SelectableReceipts);
			if ($receiptnumber > 0 && $receiptnumber - 1 <= count($which)) {
				$article = getArticle($which[$receiptnumber-1]);

				$OUTPUT->speak = 'das rezept für ' . $article['title'] . ' wird auch in deiner alexa-app bei den aktivitäten angezeigt. möchtest du den link zu dem rezept per email zugesandt bekommen?';
				$OUTPUT->card->title = $OUTPUT->display->title = 'Rezept für ' . $article['title'];
				$OUTPUT->card->image = $OUTPUT->display->image = $article['image'];
				$OUTPUT->card->text = $OUTPUT->display->text = $article['text'];
				$OUTPUT->display->hint = 'sag "ja" um den Link per eMail zu bekommen.';
				$OUTPUT->sessionAttributes = ['SelectableReceipts' => $which[$receiptnumber-1], 'YesIntentConfirms' => 'sendreceipt'];

			}
			else {
				$OUTPUT->card->title = $OUTPUT->display->title = 'Na hör mal! ';
				$OUTPUT->speak = 'das funktioniert so nicht. du kannst dir ' . (count($which) > 1 ? 'rezepte 1 bis ' . count($which) . ' anzeigen lassen.' : ' rezept nummer 1 anzeigen lassen oder die auswahl mit ' . $ALEXA->emphase('jaa') . ' bestätigen.');
				$OUTPUT->display->hint = 'sage zum Beispiel "zeige Rezept Nummer ' . random_int(1, count($which)) . '"';
				$OUTPUT->sessionAttributes = ['SelectableReceipts' => $ALEXA->post->session->attributes->SelectableReceipts, 'YesIntentConfirms' => 'showreceipt'];
			}
			$OUTPUT->reprompt = 'kann ich sonst noch etwas für dich tun?';
		}
		else $OUTPUT->speak = 'ich habe dich leider nicht verstanden.' ;
	}
	elseif ($ALEXA->IntentName == "SEND_RECEIPT" || ($ALEXA->IntentName == "AMAZON.YesIntent" && $ALEXA->post->session->attributes->YesIntentConfirms == "sendreceipt")){
		$receiptnumber = $receiptnumber ? : 1;
		if ($receiptnumber && $ALEXA->post->session->attributes->SelectableReceipts){
			$which = explode(",", $ALEXA->post->session->attributes->SelectableReceipts);
			$article = getArticle($which[$receiptnumber-1]);
			$OUTPUT->card->title = $OUTPUT->display->title = 'Rezept für ' . $article['title'];
			$OUTPUT->card->image = $OUTPUT->display->image = $article['image'];
			$OUTPUT->card->text = $OUTPUT->display->text = $article['text'];
			$receiptfound=true;
		}
		else {
			$OUTPUT->speak = 'ich weiß nicht welches rezept ich dir zusenden soll. frag mich nochmal!';
			$receiptfound=false;
		}

		$usermail = $ALEXA->getemail();
		if (!is_string($usermail) || $usermail == 'null') {
			$OUTPUT->speak = 'um dir das rezept per email zusenden zu können musst du für diesen skill in der alexa-app die freigabe zur verwendung deiner emailadresse erlauben. soll ich dir bis dahin weitere rezepte anzeigen?';
			$OUTPUT->permission = $ALEXA->askforemailpermission('Möchtest du Rezept-Links per eMail erhalten?');
			$OUTPUT->reprompt = 'möchtest du noch andere rezepte angezeigt bekommen?';
			$OUTPUT->sessionAttributes = ['UnusedConfirmation' => true];
		}
		else {
			if ($receiptfound){
				$raw = 'Das Rezept f&uuml;r <strong>' . $article['title'] . '</strong> findest du unter dem Link<br /><a href="http://annebackt.de/?permalink=' . base_convert($article['timestamp'], 10, 16) . '">http://annebackt.de/?permalink=' . base_convert($article['timestamp'], 10, 16) . '</a><br />'
				.'<br /><small>Du hast im Alexa-Skill die Freigabe zur Nutzung Deiner eMail-Adresse und zur Zusendung des Links erteilt.</small>';
				if (send_email('asb@annebackt.de', 'Anne backt via Alexa Skill', $usermail, 'Rezept für '.$article['title'], '', $raw, False,'annebackt.de')) $OUTPUT->speak = 'die email wurde versandt. kann ich sonst noch etwas für dich tun?';
				else $OUTPUT->speak = 'die mail konnte leider nicht versendet werden. versuche es später oder sag mir über annebackt.de bescheid. möchtest du andere rezepte zumindest angezeigt bekommen?';
				$OUTPUT->display->hint = 'du hast eine eMail an <strong>' . $usermail . '</strong> erhalten.';
			}
			$OUTPUT->sessionAttributes = ['UnusedConfirmation' => true];
			$OUTPUT->reprompt = 'möchtest du noch andere rezepte angezeigt oder zugeschickt bekommen?';
		}
	}
	elseif ($ALEXA->IntentName == "SECRET"){
		if ($ALEXA->post->request->intent->slots->TRICK->value){
			$OUTPUT->speak = $ALEXA->whisper('ich habe gar kein ' . $ALEXA->post->request->intent->slots->TRICK->value . '.') . ' ich backe ein bisschen liebe mit ein und lasse dem teig nur die zeit die er braucht. jetzt bist ' . $ALEXA->phoneme('du','\'duu') . ' dran! frag nach einem rezept und probiere es aus!';
			$OUTPUT->reprompt = 'du kannst das bestimmt auch. frag mich einfach nach meinen rezepten und probier eines aus. also?';
		}
		else {
			$OUTPUT->speak = 'ich kann dir nicht folgen. frag nach einem rezept und probiere es aus!';
			$OUTPUT->reprompt = 'frag mich einfach nach meinen rezepten und probier eines aus. also?';

		}
	}
	elseif ($ALEXA->IntentName == "CRITICISE"){
		$OUTPUT->speak = $ALEXA->interject('ey') . '! wenn du vorschläge hast was anne backt noch können soll schreib mir eine email. meine kontaktdaten findest du auf annebackt.de.';
		//$OUTPUT->reprompt='meine kontaktdaten findest du auf annebackt.de.';
	}
	elseif ($ALEXA->IntentName == "AMAZON.HelpIntent"){
		$OUTPUT->speak = 'dies ist ein skill der seite annebackt.de. stelle fragen wie: was sind die neuesten rezepte oder gibt es rezepte mit hefewasser - wobei hefewasser hier eine beliebige zutat ist. mehr optionen werden dir in der alexa-app angezeigt. versuchs mal!';
        $OUTPUT->card->title = 'Was kann der Annebackt.de-Skill?';
        $OUTPUT->card->image = "https://erroronline.one/column4/sslmedia.php?../../asb/design/icon192x192.png";
		$OUTPUT->card->text = str_replace(["<br />", "<em>", "</em>"], ["\r\n", "", ""], $stardardText)
			."Du kannst dir die Rezepte in der App anzeigen und per eMail zusenden lassen. "
			."Wenn es mehr als ein Rezept auf deine Frage hin gibt sage\r\n"
			."\"Zeige mir Rezept Nummer (z.B.) zwei.\" oder \r\n"
			."\"Schicke mir Rezept drei.\"\r\n \r\n"
			."Zur Nutzung der eMail-Funktion musst du dem Skill die Freigabe erteilen.";

		$OUTPUT->display->displaytemplate = 'BodyTemplate1';
		$OUTPUT->display->token = 'hilfe';
		$OUTPUT->display->title = 'Was kann der Annebackt.de-Skill?';
		$OUTPUT->display->text = '<font size="1">'
			.$stardardText
			.'Du kannst dir die Rezepte in der App anzeigen und per eMail zusenden lassen. '
			.'Wenn es mehr als ein Rezept auf deine Frage hin gibt sage<br />'
			.'"Zeige mir Rezept Nummer (z.B.) zwei." oder <br />'
			.'"Schicke mir Rezept drei."<br /><br />'
			.'Zur Nutzung der eMail-Funktion musst du dem Skill die Freigabe erteilen.'
			.'</font>';
		$OUTPUT->reprompt = 'versuchs mal! frag mich nach dem neuesten rezept!';
	}
	elseif ($ALEXA->IntentName == "AMAZON.StopIntent"){
		$OUTPUT->speak = 'ich hoffe ich konnte helfen.';
		$OUTPUT->display->hint = 'bis bald...';
	}
	elseif ($ALEXA->IntentName == "AMAZON.CancelIntent" || $ALEXA->post->session->attributes->previousCancel) {
		$OUTPUT->speak = 'ok! viel spaß beim backen!';
		$OUTPUT->display->hint = 'bis bald...';
	}
	elseif ($ALEXA->IntentName == "NO_INTENT" || ($ALEXA->post->session->attributes->UnusedConfirmation && $ALEXA->IntentName == "AMAZON.YesIntent")){
		if ($ALEXA->post->session->attributes->UnusedConfirmation && $ALEXA->IntentName == "AMAZON.YesIntent"){
			$OUTPUT->speak = 'dann frag mich! oder nach hilfe.';
			$OUTPUT->display->text = $stardardText;
			$OUTPUT->reprompt = 'was kann ich für dich tun?';
		}
		else {
			$OUTPUT->speak = 'ok. kann ich was anderes für dich tun?';
			$OUTPUT->display->text = $stardardText;
			$OUTPUT->reprompt = 'was kann ich für dich tun?';
			$OUTPUT->sessionAttributes = ['previousCancel' => true];
		}
	}
	elseif ($ALEXA->IntentName == "DEVELOPER"){
		$OUTPUT->speak = 'das hier ist der entwicklerbereich der zeitweise informationen bereitstellt. hier ist aber gerade nichts los.';
		$OUTPUT->reprompt = 'kann ich etwas anderes für dich tun?';
		$OUTPUT->sessionAttributes = ['previousCancel' => true];
		$OUTPUT->card->title = $OUTPUT->display->title = "Gerade nichts los im Entwicklerbereich";

		$opts = [ 'http' => [ 'method' => 'GET', 'header' => "Accept: application/json\r\nAuthorization: " . $pexelsAPIKey . "\r\n"] ];
		$context = stream_context_create($opts);
		$catpic= json_decode(file_get_contents('https://api.pexels.com/v1/search?query=kitten&per_page=100', false, $context));
		$randcat=$catpic->photos[random_int(0,count($catpic->photos)-1)];
						
		$OUTPUT->card->image = $OUTPUT->display->image = $randcat->src->medium; //"https://erroronline.one/column4/sslmedia.php?../../asb/design/icon192x192.png";
		$OUTPUT->card->text = $OUTPUT->display->text = "Hier, ein Bild von einer Katze...";
		$OUTPUT->card->subtext = $OUTPUT->display->subtext = "(von " . $randcat->photographer . ")";
	}
}

//	$OUTPUT->display->token='Anne backt';
//	$OUTPUT->display->title='Anne backt';
	$OUTPUT->display->bgimage = 'https://erroronline.one/column4/sslmedia.php?../../asb/design/bg.jpg';//.getImage('','');
	$OUTPUT->display->skillogo = 'https://erroronline.one/column4/sslmedia.php?../../asb/design/icon192x192w.png';
    $OUTPUT->display->styles = [
        'textStylePrimary' => [
            'values' => [
                'color' => '#000000',
                'fontSize' => 25,
                'fontWeight' => 100
            ]
        ],
        'textStyleSecondary' => [
            'values' => [
                'color' => '#000000',
                'fontSize' => 20,
                'fontWeight' => 100
            ]
        ],
        'customHeader' => [
            'values' =>[
                'color' => '#000000',
                'fontSize' => 27,
                'backgroundColor' => '#EBB1D7',
                'textAlignVertical' => 'center'
                ]
        ],
        'customHint' => [
            'values' =>[
                'color' => '#000000aa',
                'fontFamily' => 'Bookerly',
                'fontStyle' => 'italic',
                'fontSize' => 22,
                'textAlignVertical' => 'bottom'
            ]
        ]
    ];
    $OUTPUT->display->resources = [
        [
            'dimensions' => [
                'headerHeight' => '15vh',
                'bodyHeight' => '70vh',
                'bodyPaddingTopBottom' => 16,
                'bodyPaddingLeftRight' => 32
        ]
        ]
    ];


$OUTPUT->answer();
} else $ALEXA->verificationfailed();
?>