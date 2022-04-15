<?php include ('APL.php'); // basic functions library

include ('nonpublic.php'); // application ids, database connections, etc.

if ($ALEXA->verified($AnneBacktAppId)){

//read slots
$num = $ALEXA->post->request->intent->slots->NUM->value;
$contains = $ALEXA->post->request->intent->slots->CONTAINS->value;
$receiptnumber = $ALEXA->post->request->intent->slots->RECEIPT_NUMBER->value;

function getImage($id, $post){
	global $AnneBacktMysqli;
	$attachment=$AnneBacktMysqli->query("SELECT meta_value FROM wp_postmeta WHERE post_id IN (SELECT meta_value FROM wp_postmeta WHERE post_id='15884') AND meta_key='_wp_attached_file'")->fetch_assoc();
	if ($attachment->num_rows || $attachment['wp_postmeta']) {
		return $attachment['wp_postmeta'];
	} else {
		preg_match('/\<img src=[\"\'](.*?)[\"\'].*?>/is', $post, $embeddedimage);
		return $embeddedimage[1] ? $embeddedimage[1] : 'https://annebackt.de/wp-content/uploads/2021/12/ablogo.png';
	}
}

function stripWPMarkup($post_content){
	 return preg_replace(['/<!-- \/*wp:paragraph -->/', '/<!-- wp:gallery(?:.|\n|\r)*?\/wp:gallery -->/'], ['<br />', ''], $post_content);
}

function stripMarkup($post_content){
	return preg_replace(['/<br.{0,2}>/'], ["\n\r"], $post_content);
}

function getArticle($id){
	global $AnneBacktMysqli;
	$post = $AnneBacktMysqli->query("SELECT * FROM wp_posts WHERE id=" . $id . " LIMIT 1")->fetch_assoc();
	return ['title' => utf8_encode($post['post_title']),
			'image' => getImage($post['ID'], $post['post_content']),
			'text' => utf8_encode(stripWPMarkup($post['post_content'])) . "\r\n \r\nEin Rezept von annebackt.de",
			'permalink' => date("Y/m/d/", strtotime($post['post_date'])) . $post['post_name'] . '/'];
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
		if ($ALEXA->IntentName == "NEW_RECEIPTS") $orderlimit = 'ORDER BY ID DESC LIMIT ' . ($num?:3);
		elseif ($ALEXA->IntentName == "NEW_RECEIPT") $orderlimit = 'ORDER BY ID DESC LIMIT 1';
		elseif ($ALEXA->IntentName == "SURPRISE") $orderlimit = 'ORDER BY RAND() LIMIT 1';
		$list=$AnneBacktMysqli->query("SELECT * FROM wp_posts WHERE post_status='publish' AND post_type='post' " . $orderlimit);
		if ($list->num_rows > 0) {
			if ($list->num_rows > 1) $OUTPUT->speak = 'Die neuesten '.$list->num_rows.' Rezepte sind:';
			else {
				if ($ALEXA->IntentName == "NEW_RECEIPT") $OUTPUT->speak = 'Das neueste Rezept ist:';
				elseif ($ALEXA->IntentName == "SURPRISE") $OUTPUT->speak = 'wie wäre es mit:';
			}
			$t_display['title'] = $OUTPUT->speak;
			while($post = $list->fetch_assoc()){
				$OUTPUT->speak .= (++$items < $list->num_rows || $list->num_rows < 2 ? ', ' : " und ").($list->num_rows > 1 ? $ALEXA->number($items.'.') : '') . ' ' . utf8_encode($post['post_title']) . ' vom ' . $ALEXA->date(date('d.m.y', strtotime($post['post_date'])));
				$id.=','.$post['ID'];

				$t_display['items'][]=[
					'token' => $post['ID'],
					'listItemIdentifier' => $post['ID'],
					'ordinalNumber' => $items,
					'image' => [
						'contentDescription' => 'icon',
						'sources' => [['url' => getImage($post['ID'], $post['post_content'])]]	
					],
					'textContent' => [
						'primaryText' => ['text' => utf8_encode(preg_replace('/\//msi', '$0 ', $post['post_title'])), 'type' => 'PlainText'],
						'secondaryText' =>['text' =>date('d.m.Y', strtotime($post['post_date'])),'type' =>'PlainText'],
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
			$column = $ALEXA->IntentName == 'LOOKUP_RECEIPTS' ? 'post_content' : 'post_title';
			$list=$mysqli->query("SELECT * FROM wp_posts WHERE " . $column . " LIKE '%" . $contains . "%' AND post_status='publish' and post_type='post' ORDER BY ID DESC");
			if ($list->num_rows) {
				$OUTPUT->speak = $ALEXA->IntentName == 'LOOKUP_RECEIPTS'?
						'Es gibt ' . $list->num_rows . ' Rezepte mit ' . ucfirst($contains) . ': ':
						'Es gibt ' . $list->num_rows . ' Rezepte für ' . ucfirst($contains) . ': ';

				$t_display['title'] = $OUTPUT->speak;
				while($post = $list->fetch_assoc()){
					$OUTPUT->speak .= (++$items < $list->num_rows || $list->num_rows < 2 ? ', ' : " und ") . ($list->num_rows > 1 ? $ALEXA->number($items . '.') : '') . ' ' . utf8_encode($post['post_title']) . ' vom ' . $ALEXA->date(date('d.m,Y', strtotime($entry['post_date'])));
					$id .= ',' . $post['ID'];

					$t_display['items'][] = [
						'token' => $post['ID'],
						'listItemIdentifier' => $post['ID'],
						'ordinalNumber' => $items,
						'image' => ['contentDescription' => 'icon', 'sources' => [['url' => getImage($post['ID'], $post['post_content'])]]],
						'textContent' => ['primaryText' => ['text' => utf8_encode(preg_replace('/\//msi', '$0 ', $post['post_title'])), 'type' => 'PlainText'],
										'secondaryText' =>['text' =>date('d.m.Y', strtotime($entry['post_date'])),'type' =>'PlainText'],
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
				$OUTPUT->card->text = $OUTPUT->display->text = stripMarkup($article['text']);
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
			$OUTPUT->card->text = $OUTPUT->display->text = stripMarkup($article['text']);
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
				$raw = 'Das Rezept f&uuml;r <strong>' . htmlentities(utf8_decode($article['title'])) . '</strong> findest du unter dem Link<br /><a href="https://annebackt.de/' . $article['permalink'] . '">https://annebackt.de/' . $article['permalink'] . '</a><br />'
				.'<br /><small>Du hast im Alexa-Skill die Freigabe zur Nutzung Deiner eMail-Adresse und zur Zusendung des Links erteilt.</small>';
				if ($ALEXA->send_email('anne@annebackt.de',
					'Anne backt via Alexa Skill',
					$usermail,
					htmlentities(utf8_decode('Rezept für '.$article['title'])),
					$raw,
					False,
					'body {background:url("https://annebackt.de/wp-content/uploads/2021/11/bg.jpg")}')) $OUTPUT->speak = 'die email wurde versandt. kann ich sonst noch etwas für dich tun?';
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
        $OUTPUT->card->image = 'https://annebackt.de/wp-content/uploads/2021/12/ablogo.png';
		$OUTPUT->card->text = str_replace(['<br />', '<em>', '</em>'], ["\r\n", '', ''], $stardardText)
			."Du kannst dir die Rezepte in der App anzeigen und per eMail zusenden lassen. "
			."Wenn es mehr als ein Rezept auf deine Frage hin gibt sage\r\n"
			."'Zeige mir Rezept Nummer (z.B.) zwei.' oder \r\n"
			."'Schicke mir Rezept drei.'\r\n \r\n"
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
						
		$OUTPUT->card->image = $OUTPUT->display->image = $randcat->src->medium;
		$OUTPUT->card->text = $OUTPUT->display->text = "Hier, ein Bild von einer Katze...";
		$OUTPUT->card->subtext = $OUTPUT->display->subtext = "(von " . $randcat->photographer . ")";
	}
}

//	$OUTPUT->display->token='Anne backt';
//	$OUTPUT->display->title='Anne backt';
	$OUTPUT->display->bgimage = 'https://annebackt.de/wp-content/uploads/2021/11/bg.jpg';//.getImage('','');
	$OUTPUT->display->skillogo = 'https://annebackt.de/wp-content/uploads/2021/12/ablogo.png';
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
            'values' => [
                'color' => '#000000',
                'fontSize' => 27,
                'backgroundColor' => 'transparent', //'#EBB1D7',
                'textAlignVertical' => 'center'
                ]
        ],
        'customHint' => [
            'values' => [
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