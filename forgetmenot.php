<?php include ('APL.php'); // basic functions library

include ('nonpublic.php'); // application ids, database connections, etc.

if ($ALEXA->verified($RemindmeAPPId)){
	// $ALEXA->post->request->locale might be 'de_DE' or 'en_US' or something like that and can be used to determine language output
	// since this shall support various english regions i concentrate on the language and not on the region
	$lang = substr($ALEXA->post->request->locale, 0, 2);
	// amazon does not allow recurring reminders less that 4 hours apart, and only dayly, weekly and monthly recurrances therefore
	// no intervals of e.g. 28 hours
	$timelimit=[3600*4, 3600*24]; // min, max
	
	class answers{
		public $lang;
		function __construct($lang){
			$this->lang = $lang;
		}
	
		public function get($chunk, ...$param){
			$answers= [
				'reminder_from' => [		'de' => 'Vergissmeinnicht erinnert: ',
											'en' => 'forgetmenot reminds: '],
				'start' => [				'de' => 'lass dich vom vergiss-mein-nicht regelmäßig erinnern. frage zum beispiel "was sind meine erinnerungen" oder erstelle eine neue. frage nach hilfe, wenn du nicht weiter weißt. ',
											'en' => 'let the forget-me-not remind you regulary. ask for instance "what are my reminders" or set up a new one. ask for help if you get stuck. '],
				'welcomeback' => [			'de' => 'willkommen zurück! ',
											'en' => 'welcome back! '],
				'reminder_permission' => [	'de' => 'Für die Erinnerungen von Vergissmeinnicht ist deine Freigabe erforderlich.',
											'en' => 'For reminders by forget-me-not your permission is necessary.'],
				'reminder_permission2' => [	'de' => ' die freigabe für erinnerungen durch diesen skill solltest du vor der benutzung erteilen. dies kannst du jetzt in deiner alexa-app tun. starte anschließend den skill erneut.',
											'en' => ' the permission for reminders through this skill should be granted before use. you can do this now from the alexa-app. afterwards restart the skill.'],
				'set_topic_reprompt' => [	'de' => 'in welchen abständen darf ich dich erinnern?',
											'en' => 'in which intervals i should remind you?'],
				'error' => [				'de' => 'es trat ein fehler auf, bitte versuche es nochmal',
											'en' => 'an error occured. please try again.'],
				'errornotsupported' => [	'de' => 'es trat ein fehler auf. dieses gerät unterstützt möglicherweise keine erinnerungen oder es gibt ein problem mit den accountberechtigungen.',
											'en' => 'an error occured. this device might not support reminders or there might be an issue with account permissions.'],
				'errortimelimit' => [		'de' => 'tut mir leid, amazon erlaubt keine kürzeren intervalle als 4 oder längere als 24 stunden.',
											'en' => 'i am sorry, amazon does not allow intervals less than 4 or more than 24 hours.'],
				'no_reminders' => [			'de' => 'du hast keine aktiven erinnerungen. was kann ich für dich tun?',
											'en' => 'there are no active reminders. what can i do for you?'],
				'unset_reprompt' => [		'de' => 'soll ich eine erinnerung beenden?',
											'en' => 'shall i cancel a reminder?'],
				'conjunction' => [			'de' => 'und',
											'en' => 'and'],
				'every'	=> [				'de' => 'alle',
											'en' => 'every'],
				'to' => [					'de' => 'an',
											'en' => 'to'],
				'default' => [				'de' => 'und jetzt?',
											'en' => 'what now?'],
				'bye' => [					'de' => 'ok, wir hören voneinander',
											'en' => 'ok, we´ll keep in touch'],
				'help' => [					'de' => 'dieser skill kann dich regelmäßig an etwas erinnern. sage "erinnere mich alle vier stunden an trinken", "was sind meine erinnerungen" oder "beende erinnerung an trinken". mehr informationen stehen in deiner alexa-app.',
											'en' => 'this skill can remind you regulary. say "remind me every four hours to drink", "what are my reminders" and "stop reminding me of drink". you  will find more information in your alexa-app.'],
				'help_card_title' => [		'de' => "Was kann 'Erinnere mich'?",
											'en' => "What does 'remind me' do?"],
				'help_card_text' => [		'de' => "Dieser Skill kann dich in regelmäßigen Abständen an etwas erinnern.\r\n"
													."Zur Zeit unterstützt der Skill wiederholende Erinnerungen innerhalb eines Tages. "
													."Du kannst dich so an \"Trinken\", \"Ausruhen\" oder dein \"Kurz-Workout\" erinnern lassen. "
													."Starte den Skill und sage: \"Erinnere mich alle vier Stunden an Trinken\".\r\n"
													."Du kannst Erinnerungen zwischen 4 und 24 Stunden einrichten."
													."Um eine Übersicht über deine Erinnerungen zu erhalten sage: \"Was sind meine Erinnerungen\".\r\n"
													."Löschen kannst du, indem du sagst: \"Beende Erinnerung an {was auch immer}\".\r\n \r\n"
													."Du musst in deiner Alexa-App die Berechtigung für die Erinnerungsfunktion erteilen. "
													."Aktuell haben die wiederkehrenden Erinnerungen eine Laufzeit von einem Jahr. "
													."Wenn der Tag nicht gleichmäßig in deine Abstände eingeteilt werden kann, ist es über Mitternacht etwas ungenau.",
											'en' => "This skill reminds you regulary.\r\n"
													."Currently the skill supports repetitive reminders within a day. "
													."You can remind yourself to \"drink\", \"relax\" or your \"short workout\" this way. "
													."Start the skill and say: \"remind me every four hours to drink\".\r\n"
													."You can set up reminders between 4 and 24 hours"
													."To get an overview of current reminders say: \"what are my reminders\".\r\n"
													."Delete reminders by saying: \"stop reminding me of {whatever}\".\r\n \r\n"
													."You have to give the permission for reminders within the Alexa-app. "
													."Currently the reccuring reminders have a lifetime of one year. "
													."If the day can not be parted evenly by your intervals it gets a bit fuzzy around midnight."],
				'reminders' => [			'de' => 'zur zeit erinnere ich dich ' . $param[0] . '. wenn ich etwas beenden soll sage zum beispiel: beende erinnerung für ' . $param[1],
											'en' => 'currently i remind you ' . $param[0] . '. if i should stop a reminder say something like: stop reminding me of ' . $param[1]],
				'set_topic' => [			'de' => 'in welchen abständen darf ich dich an ' . $param[0] . ' erinnern?',
											'en' => 'in which intervals i should remind you to ' . $param[0] . '?'],
				'set_time' => [				'de' => 'ich soll dich alle ' . $param[0] . ' an ' . $param[1] . ' erinnern?',
											'en' => 'you want me to remind you every ' . $param[0] . ' to ' . $param[1] . '?'],
				'confirm_time' => [			'de' => 'ok, ich erinnere dich das nächste mal um ' . $param[0] . ' an ' . $param[1],
											'en' => 'ok, the next time i remind you at ' . $param[0] . ' to ' . $param[1]],
				'deleted' => [				'de' => 'ich erinnere dich nun nicht mehr an ' . $param[0],
											'en' => 'i will not longer remind you of ' . $param[0]],
				'not_found' => [			'de' => 'ich habe dich vorher schon nicht an ' . $param[0] . ' erinnert.',
											'en' => 'i did not remind you of ' . $param[0] . ' before.'],
			];
			return $answers[$chunk][$this->lang];
		}
	}
	$answer2=new answers($lang);
	
	if ($ALEXA->post->request->type == "LaunchRequest" || $ALEXA->IntentName == "unset"){
		$OUTPUT->speak = $answer2->get('start');
		if (!$ALEXA->reminderconsent()) {
			$OUTPUT->speak .= $answer2->get('reminder_permission') . $answer2->get('reminder_permission2');
			$OUTPUT->permission = $ALEXA->askforreminderpermission($answer2->get('reminder_permission'));
		}
		else {
			if ($ALEXA->post->request->type == "LaunchRequest") $OUTPUT->speak = $answer2->get('welcomeback');
			else $OUTPUT->speak = '';

			$activereminders=$ALEXA->getactivereminders();
			if (count($activereminders) > 1){
				$result_array=[];
				foreach ($activereminders as $key => $value){
					if ($value!=0) $result_array[] = [$answer2->get('to'), str_replace($answer2->get('reminder_from'), '', $key)];
				}
				for ($i=0 ; $i<count($result_array) ; $i++){
					$result .= ($i < count($result_array)-1 || count($result_array) < 2) ? ', ' : ' ' . $answer2->get('conjunction') . ' ';
					$result .= implode(' ', $result_array[$i]);
				}
				$result = substr($result, 1);

				$OUTPUT->speak .= $answer2->get('reminders', $result, $result_array[rand(0, count($result_array) - 1)][1]);
				$OUTPUT->reprompt = $answer2->get('unset_reprompt');
			}
			else $OUTPUT->speak .= $answer2->get('no_reminders');
			$OUTPUT->reprompt = $OUTPUT->reprompt ? : $answer2->get('help'); //learnt that a reprompt is expected on launch. the skill works otherwise but the console throws an error.
		}
	}
	elseif ($ALEXA->IntentName == "AMAZON.HelpIntent"){
		$OUTPUT->speak = $OUTPUT->reprompt = $answer2->get('help');
	}
	elseif ($ALEXA->IntentName == "set_topic"){
		if (!$ALEXA->reminderconsent()) {
			$OUTPUT->speak = $answer2->get('reminder_permission') . $answer2->get('reminder_permission2');
			$OUTPUT->permission = $ALEXA->askforreminderpermission($answer2->get('reminder_permission'));
		}
		else {
			$topic = $ALEXA->post->request->intent->slots->topic->value;

			$OUTPUT->sessionAttributes = ['TOPIC'=>$topic];
			$OUTPUT->speak = $answer2->get('set_topic', $topic);
			$OUTPUT->reprompt = $answer2->get('set_topic_reprompt');
		}
	}
	elseif ($ALEXA->IntentName == "set_time" || ($ALEXA->post->session->attributes->TOPIC && !$ALEXA->post->session->attributes->INTERVAL)){
		if (!$ALEXA->reminderconsent()) {
			$OUTPUT->speak = $answer2->get('reminder_permission') . $answer2->get('reminder_permission2');
			$OUTPUT->permission = $ALEXA->askforreminderpermission($answer2->get('reminder_permission'));
		}
		else {
			$topic = $ALEXA->post->session->attributes->TOPIC;
			$topic = !$topic ? $ALEXA->post->request->intent->slots->topic->value : $topic;
			$interval = $ALEXA->post->request->intent->slots->timespan->value;
			if ($interval && $topic){
				$duration = $ALEXA->resolveInterval($interval, $lang);
				if ($duration['seconds']<$timelimit[0] || $duration['seconds']>$timelimit[1]){
					$say= $answer2->get('errortimelimit');
				}
				else {
					$say = $answer2->get('set_time', $duration['speech'], $topic);
					$OUTPUT->sessionAttributes = ['TOPIC' => $topic, 'INTERVAL' => $duration['seconds']];
					$OUTPUT->reprompt = $say;
				}
			}
			else $say = $answer2->get('error');
			$OUTPUT->speak = $say;
		}
	}
	elseif ($ALEXA->IntentName == "AMAZON.YesIntent" && $ALEXA->post->session->attributes->TOPIC && $ALEXA->post->session->attributes->INTERVAL){
		$topic = $ALEXA->post->session->attributes->TOPIC;
		$interval = $ALEXA->post->session->attributes->INTERVAL;
		$activereminders = $ALEXA->getactivereminders();
		
		if (key_exists($topic, $activereminders)) $ALEXA->deletereminder($activereminders[$topic]['id']);
		
		$set= $ALEXA->setrecurringreminder(['interval' => $interval, 'text' => $topic, 'ssml'=> '<speak>' . $answer2->get('reminder_from') . $topic . '</speak>', 'duration' => 3600*24*365], $ALEXA->post->request->timestamp);
		if (!strstr($set[0], '1.1 201')){
			$OUTPUT->speak = $answer2->get('errornotsupported');
			$OUTPUT->card->title = "error";
			$OUTPUT->card->text = implode(' | ', $set);
		}
		else {
			$OUTPUT->speak = $answer2->get('confirm_time', $ALEXA->tellTime($set[1], $lang), $topic);
		}
	}
	elseif ($ALEXA->IntentName == "unset_topic"){
		if (!$ALEXA->reminderconsent()) {
			$OUTPUT->speak = $answer2->get('reminder_permission') . $answer2->get('reminder_permission2');
			$OUTPUT->permission = $ALEXA->askforreminderpermission($answer2->get('reminder_permission'));
		}
		else {
			$topic = $ALEXA->post->request->intent->slots->topic->value;
			$activereminders=$ALEXA->getactivereminders();
			if (key_exists($topic, $activereminders)){
				$ALEXA->deletereminder($activereminders[$topic]['id']);
				$OUTPUT->speak = $answer2->get('deleted', $topic);
			}
			else {
				$OUTPUT->speak = $answer2->get('not_found', $topic);
			}
		}
	}
	elseif ($ALEXA->IntentName == "AMAZON.HelpIntent"){
		$OUTPUT->speak = $answer2->get('help');
		$OUTPUT->card->title = $answer2->get('help_card_title');
		$OUTPUT->card->image = "https://erroronline.one/column4/sslmedia.php?../../asb/design/icon256x256.png";
		$OUTPUT->card->text = $answer2->get('help_card_text');
	}
	elseif ($ALEXA->IntentName == "AMAZON.StopIntent" || $ALEXA->IntentName == "AMAZON.CancelIntent" || $ALEXA->IntentName == "AMAZON.NoIntent"){
		$OUTPUT->speak = $answer2->get('bye');
	}
	else {
		$OUTPUT->speak = $answer2->get('default');
		$OUTPUT->reprompt = $answer2->get('help');
	}
	
	$OUTPUT->answer();
} else $ALEXA->verificationfailed();
?>