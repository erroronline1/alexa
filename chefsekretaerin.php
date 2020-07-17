<?php  include ('APL.php'); // basic functions library

include ('nonpublic.php'); // application ids, database connections, etc.

if ($ALEXA->verified($ChefsekretaerinAppId)){

	$boss = ["der boss", "der könig", "die königin", "der chef", "die chefin", "der bestimmer", "die bestimmerin"];
	$wer = $ALEXA->post->request->intent->slots->NAME->value;
	$wo = $ALEXA->post->request->intent->slots->WHERE->value;
	$was = $ALEXA->post->request->intent->slots->BOSS->value;
	$userID = $ALEXA->post->session->user->userId;

	$tchef = $ChefsekretaerinMysqli->query("SELECT name FROM wer_ist_der_chef WHERE amazon_id='" . $userID . "' LIMIT 1")->fetch_assoc();

	$chef = $tchef['name'] ? $tchef['name'] : 'noch niemand';

	if ($ALEXA->post->request->type == "LaunchRequest"){
		$OUTPUT->speak = 'frag mich wer hier der chef ist. oder nach hilfe.';
		if (!$tchef['name']) $OUTPUT->speak .= ' ich weiß aber noch nicht wer hier der chef ist. damit ich mir das merke, sag etwas wie: ich bin alexa und ich bin der chef.';
		$OUTPUT->reprompt = 'frag mich wer der chef ist';
	}
	elseif ($ALEXA->post->request->type == 'SessionEndedRequest') {
		$OUTPUT->speak = 'dann bis bald. ich hoffe ich konnte helfen';
	}
	elseif ($ALEXA->post->request->type == "IntentRequest"){
		if ($ALEXA->IntentName == "DEFAULT"){
			if (in_array($was, $boss)) $OUTPUT->speak = $chef . ' ist ' . $wo . ' ' . $was;
			else $OUTPUT->speak = 'du bist vielleicht ' . $wo . ' ' . $was . ', aber ' . $chef . ' ist hier der chef';
		}
		elseif ($ALEXA->IntentName == "NAME_REQUEST"){
			if ($wer == $chef && in_array($was, $boss)) $OUTPUT->speak = 'ja! ja, ' . $wer . ' ist ' . $wo . ' ' . $was;
			elseif ($wer != $chef && in_array($was, $boss)) $OUTPUT->speak = 'nein, ' . $wer . ' ist ' . $wo . ' nicht ' . $was . ', das ist ' . $chef;
			elseif ($wer == $chef && !in_array($was, $boss)) $OUTPUT->speak = 'nein, nein! ' . $chef . ' ist ' . $wo . ' der chef';
			else $OUTPUT->speak = 'kann sein, dass ' . $wer . ' ' . $wo . ' ' . $was . ' ist, aber ' . $chef . ' ist hier der chef';
		}
		elseif ($ALEXA->IntentName == "SELF_REQUEST"){
			if ($ALEXA->post->session->attributes->sessionWer) {
				if ($ALEXA->post->session->attributes->sessionWer == $chef && in_array($was, $boss)) $OUTPUT->speak = 'ja ' . $chef . ', du bist ' . $wo . ' ' . $was;
				elseif ($ALEXA->post->session->attributes->sessionWer == $chef && !in_array($was, $boss)) $OUTPUT->speak = 'nein ' . $chef . ', du bist hier der chef';
				elseif ($ALEXA->post->session->attributes->sessionWer != $chef && in_array($was, $boss)) $OUTPUT->speak = 'nein, das bist du nicht, das ist ' . $chef;
				else $OUTPUT->speak = 'das kann schon sein, aber so gut kennen wir uns ja auch nicht';
			}
			else {
				$OUTPUT->speak = $OUTPUT->reprompt = 'wie heißt du denn?';
				$OUTPUT->sessionAttributes = ['sessionWas' => $was, 'sessionWo' => $wo];
			}
		}
		elseif ($ALEXA->IntentName == "SELF_REQUEST_SET_NAME"){
			if ($ALEXA->post->session->attributes->sessionWas) {
				$was = $ALEXA->post->session->attributes->sessionWas;
				if ($wer == $chef && in_array($was, $boss)) $OUTPUT->speak = 'ja ' . $chef . ', du bist ' . $wo . ' ' . $was;
				elseif ($wer == $chef && !in_array($was, $boss)) $OUTPUT->speak = 'nein ' . $chef . ', du bist hier der chef';
				elseif ($wer != $chef && in_array($was, $boss)) $OUTPUT->speak = 'nein, du bist nicht ' . $was . ', das ist ' . $chef;
				else $OUTPUT->speak = 'das kann schon sein, aber so gut kennen wir uns ja auch nicht';
			}
			else {
				$OUTPUT->speak = 'hallo ' . $wer . ', frag mich ob du der chef bist';
				$OUTPUT->reprompt = 'frag mich wer der chef ist';
			}
			$OUTPUT->sessionAttributes = ['sessionWer' => $wer];
		}
		elseif ($ALEXA->IntentName == "SELF_REQUEST_STORE_NAME"){
			if ($wer && in_array($was, $boss)) {
				$ChefsekretaerinMysqli->query("INSERT INTO wer_ist_der_chef VALUES ('" . $userID . "','" . $wer . "') ON DUPLICATE KEY UPDATE name='" . $wer . "'");
				$OUTPUT->speak = 'hallo ' . $wer . ', es freut mich dich kennen zu lernen';
				$OUTPUT->card->title = 'Wer ist hier der Chef?';
				$OUTPUT->card->text = $wer . ' ist hier jetzt offiziell ' . $was . ' ' . $ChefsekretaerinMysqli->error;
			}
			else {
				$OUTPUT->speak = 'ich habe dich nicht verstanden. versuchs nochmal';
				$OUTPUT->reprompt = 'ich habe dich nicht verstanden. sage mir wer der chef ist oder frage nach hilfe';
			}
		}
		elseif ($ALEXA->IntentName == "AMAZON.HelpIntent"){
			$OUTPUT->speak=$OUTPUT->reprompt = 'ich kann dir sagen, wer hier der chef ist. oder die königin. oder der bestimmer. frag einfach. damit ich mir das merke, sag etwas wie: ich bin alexa und ich bin der chef.';
			$OUTPUT->card->title = 'Bestimme wer der Chef ist.';
			$OUTPUT->card->text= ($chef ? 'Zur Zeit ist ' . ucfirst($chef) . ' hier der Chef. Wenn Du das ändern möchtest s' : 'S').'ag "Ich bin {Name} und ich bin der Chef".';
		}
		elseif ($ALEXA->IntentName == "AMAZON.StopIntent" || $ALEXA->IntentName == "AMAZON.CancelIntent"){
			$OUTPUT->speak = 'dann bis bald. ich hoffe ich konnte helfen';
		}
	}
	$OUTPUT->answer();
} else $ALEXA->verificationfailed();
?>