<?php include ('APL.php'); // basic functions library

include ('nonpublic.php'); // application ids, database connections, etc.

if ($ALEXA->verified($post, $rawpost, $RemindmeAPPId)){
	
	//read slots

	// $post->request->locale might be 'de_DE' or 'en_US' or something like that and can be used to determine language output
	// since this shall support various english regions i concentrate on the language and not on the region
	$lang = substr($post->request->locale, 0, 2);
	// amazon does not allow recurring reminders less that 4 hours apart, and onyl dayly, weekly and monthly recurrances therefore
	// no intervals of e.g. 28 hours
	$timelimit=[3600*4, 3600*24]; // min, max
	
	$answers = [
		'start' => [	'de' => 'ich versuche dich regelmäßig zu erinnern',
						'en' => 'i will try to remind you regulary'],
		'reminder_permission' => ['de' => 'Für die Erinnerungen ist deine Freigabe erforderlich.',
						'en' => 'For reminders your permission is necessary.'],
		'set_topic_reprompt' => ['de' => 'in welchen abständen darf ich dich erinnern?',
						'en' => 'in which intervals i should remind you?'],
		'error' => [	'de' => 'es trat ein fehler auf, bitte versuche es nochmal',
						'en' => 'an error occured. please try again.'],
		'errornotsupported' => ['de' => 'es trat ein fehler auf. dieses gerät unterstützt möglicherweise keine erinnerungen.',
						'en' => 'an error occured. this device might not support reminders.'],
		'errortimelimit' => ['de' => 'es trat ein fehler auf. amazon erlaubt keine kürzeren intervalle als 4 oder längere als 24 stunden.',
						'en' => 'an error occured. amazon does not allow intervals less than 4 or more than 24 hours.'],
		'no_reminders' => ['de' => 'du hast keine aktiven erinnerungen',
						'en' => 'there are no active reminders'],
		'unset_reprompt' => ['de' => 'soll ich eine erinnerung beenden?',
						'en' => 'shall i cancel a reminder?'],
		'conjunction' => ['de' => 'und',
						'en' => 'and'],
		'every'	=> [	'de' => 'alle',
						'en' => 'every'],
		'to' => [		'de' => 'an',
						'en' => 'to'],
		'default' => [	'de' => 'und jetzt?',
						'en' => 'what now?'],
		'bye' => [		'de' => 'ok, wir hören voneinander',
						'en' => 'ok, we´ll keep in touch'],
		'help' => [		'de' => 'dieser skill kann dich regelmäßig an etwas erinnern. sage "erinnere mich alle zwei stunden an trinken", "was sind meine erinnerungen" oder "beende erinnerung an trinken". mehr informationen stehen in deiner alexa-app.',
						'en' => 'this skill can remind you regulary. say "remind me every two hours to drink", "what are my reminders" and "stop reminding me of drink". you  will find more information in your alexa-app.'],
		'help_card_title' => ['de' => "Was kann 'Erinnere mich'?",
						'en' => "What does 'remind me' do?"],
		'help_card_text' => ['de' => "Dieser Skill kann dich in regelmäßigen Abständen an etwas erinnern.\r\n"
								."Zur Zeit unterstützt der Skill wiederholende Erinnerungen innerhalb eines Tages. "
								."Du kannst dich so an \"Trinken\", \"Ausruhen\" oder dein \"Kurz-Workout\" erinnern lassen. "
								."Starte den Skill und sage: \"Erinnere mich alle zwei Stunden an Trinken\".\r\n"
								."Um eine Übersicht über deine Erinnerungen zu erhalten sage: \"Was sind meine Erinnerungen\".\r\n"
								."Löschen kannst du, indem du sagst: \"Beende Erinnerung an {was auch immer}\".\r\n \r\n"
								."Du musst in deiner Alexa-App die Berechtigung für die Erinnerungsfunktion erteilen. "
								."Aktuell haben die wiederkehrenden Erinnerungen eine Laufzeit von einem Jahr.",
						'en' => "This skill reminds you regulary.\r\n"
								."Currently the skill supports repetitive reminders within a day. "
								."You can remind yourself to \"drink\", \"relax\" or your \"short workout\" this way. "
								."Start the skill and say: \"remind me every two hours to drink\".\r\n"
								."To get an overview of current reminders say: \"what are my reminders\".\r\n"
								."Delete reminders by saying: \"stop reminding me of {whatever}\".\r\n \r\n"
								."You have to give the permission for reminders within the Alexa-app. "
								."Currently the reccuring reminders have a lifetime of one year."],
	];
	
	if ($post->request->type == "LaunchRequest"){
		$OUTPUT->speak = $answers['start'][$lang];
		$OUTPUT->reprompt = $answers['help'][$lang]; //learnt that a reprompt is expected on launch. the skill works otherwise but the console throws an error.
	}
	elseif ($IntentName == "AMAZON.HelpIntent"){
		$OUTPUT->speak = $OUTPUT->reprompt = $answers['help'][$lang];
	}
	elseif ($IntentName == "set_topic"){
		$topic = $post->request->intent->slots->topic->value;
		$answers['set_topic'] = [	'de' => 'in welchen abständen darf ich dich an ' . $topic . ' erinnern?',
									'en' => 'in which intervals i should remind you to ' . $topic . '?'];

		$OUTPUT->sessionAttributes = ['TOPIC'=>$topic];
		$OUTPUT->speak = $answers['set_topic'][$lang];
		$OUTPUT->reprompt = $answers['set_topic_reprompt'][$lang];

	}
	elseif ($IntentName == "set_time" || $post->session->attributes->TOPIC){
		if (!$ALEXA->getactivereminders($AccessToken)) {
			$OUTPUT->speak = $answers['reminder_permission'][$lang];
			$OUTPUT->permission = $ALEXA->askforreminderpermission($answers['reminder_permission'][$lang]);
		}
		else {
			$topic = $post->session->attributes->TOPIC;
			$topic = !$topic ? $post->request->intent->slots->topic->value : $topic;
			$interval = $post->request->intent->slots->timespan->value;
			if ($interval && $topic){
				$activereminders=$ALEXA->getactivereminders($AccessToken);
				if (key_exists($topic, $activereminders)) $ALEXA->deletereminder($AccessToken,$activereminders[$topic]['id']);

				$duration = $ALEXA->resolveInterval($interval, $lang);
				if ($duration['seconds']<$timelimit[0] || $duration['seconds']>$timelimit[1]){
					$say= $answers['errortimelimit'][$lang];
				}
				else {
					$set= $ALEXA->setrecurringreminder($post, ['interval' => $duration['seconds'], 'text' => $topic, 'duration' => 3600*24*365]);
					if (strstr($set[0], '1.1 403')){
						$say = $answers['errornotsupported'][$lang];
					}
					else {
						$answers['set_time'] = ['de' => 'ich erinnere dich alle ' . $duration['speech'] . ' an ' . $topic,
												'en' => 'i try to remind you every ' . $duration['speech'] . ' to ' . $topic];
						$say = $answers['set_time'][$lang];
					}
				}
			}
			else $say = $answers['error'][$lang];
			$OUTPUT->speak = $say;	
		}
	}
	elseif ($IntentName == "unset"){
		if (!$ALEXA->getactivereminders($AccessToken)) {
			$OUTPUT->speak = $answers['reminder_permission'][$lang];
			$OUTPUT->permission = $ALEXA->askforreminderpermission($answers['reminder_permission'][$lang]);
		}
		else {
			$activereminders=$ALEXA->getactivereminders($AccessToken);
			if (count($activereminders) > 1){
				$result_array=[];
				foreach ($activereminders as $key => $value){
					if ($value!=0) $result_array[] = [$answers['to'][$lang], $key];
				}
				for ($i=0 ; $i<count($result_array) ; $i++){
					$result .= ($i < count($result_array)-1 || count($result_array) < 2) ? ', ' : ' ' . $answers['conjunction'][$lang] . ' ';
					$result .= implode(' ', $result_array[$i]);
				}
				$result = substr($result, 1);

				$answers['reminders'] = [	'de' => 'zur zeit erinnere ich dich ' . $result . '. wenn ich etwas beenden soll sage zum beispiel: beende erinnerung für ' . $result_array[rand(0, count($result_array) - 1)][1],
											'en' => 'currently i remind you ' . $result . '. if i should stop a reminder say some thing like: stop reinding me of ' . $result_array[rand(0, count($result_array) - 1)][1]];
				$OUTPUT->speak = $answers['reminders'][$lang];
				$OUTPUT->reprompt = $answers['unset_reprompt'][$lang];
			}
			else $OUTPUT->speak = $answers['no_reminders'][$lang];
		}
	}
	elseif ($IntentName == "unset_topic"){
		if (!$ALEXA->getactivereminders($AccessToken)) {
			$OUTPUT->speak = $answers['reminder_permission'][$lang];
			$OUTPUT->permission = $ALEXA->askforreminderpermission($answers['reminder_permission'][$lang]);
		}
		else {
			$topic = $post->request->intent->slots->topic->value;
			$activereminders=$ALEXA->getactivereminders($AccessToken);
			if (key_exists($topic, $activereminders)){
				$ALEXA->deletereminder($AccessToken,$activereminders[$topic]['id']);
				$answers['deleted'] = [	'de' => 'ich erinnere dich nun nicht mehr an ' . $topic,
										'en' => 'i will not longer remind you of ' . $topic];
			}
			else {
				$answers['deleted'] = [	'de' => 'ich habe dich vorher schon nicht an ' . $topic . ' erinnert.',
										'en' => 'i did not remind you of ' . $topic . ' before.'];
			}
			$OUTPUT->speak = $answers['deleted'][$lang];
		}
	}
	elseif ($IntentName == "AMAZON.HelpIntent"){
		$OUTPUT->speak = $answers['help'][$lang];
        $OUTPUT->card->title = $answers['help_card_title'][$lang];
        $OUTPUT->card->image = "https://erroronline.one/column4/sslmedia.php?../../asb/design/icon256x256.png";
		$OUTPUT->card->text = $answers['help_card_text'][$lang];
	}
	elseif ($IntentName == "AMAZON.StopIntent" || $IntentName == "AMAZON.CancelIntent"){
		$OUTPUT->speak = $answers['bye'][$lang];
	}
	else {
		$OUTPUT->speak = $answers['default'][$lang];
		$OUTPUT->reprompt = $answers['help'][$lang];
	}
	
	$OUTPUT->answer();
} else $ALEXA->verificationfailed();
?>