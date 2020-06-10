<?php include ('APL.php'); // basic functions library

include ('nonpublic.php'); // application ids, database connections, etc.

if ($ALEXA->verified($post, $rawpost, $ExpertenAppId)){

	//read slots
	$lang=substr($post->request->locale,0,2);

	$experts=[	'de'=>['experten','spezialisten','fachleute','koryphäen'],
				'en'=>['experts','specialists','professionals','pundits']];

	$iex=$experts[$lang][rand(0,count($experts[$lang])-1)];
	$OUTPUT->debug="".$iex;
	$answers=[
		['de'=>'frage die experten der welt ob du recht hast','en'=>'ask the experts of the world if you are right'],
		['de'=>'sage etwas wie: "frage experten der welt ob ich recht habe"','en'=>'say something like: "ask experts of the world if i am right"'],
		['de'=>'die '.$iex.' stimmen dir zu','en'=>'the '.$iex.' agree with you'],
		['de'=>'die '.$iex.' pflichten dir bei','en'=>'the '.$iex.' endorse you'],
		['de'=>'die '.$iex.' unterstützen deine ansicht','en'=>'the '.$iex.' support your view'],
		['de'=>'laut meinung der '.$iex.' hast du recht','en'=>'according to the '.$iex.' you are right'],
		['de'=>'alles deutet auf einen nobelpreis hin','en'=>'everything points to a nobel prize'],
		['de'=>'ich habe mich umgehört und alle unterstützen dich','en'=>'i asked around and everyone agrees'],
	];

	// $post->request->locale might be 'de_DE' or 'en_US' or something like that and can be used to determine language output
	// since this shall support various english regions i concentrate on the language and not on the region

	if ($post->request->type=="LaunchRequest"){
		$OUTPUT->speak=$answers[0][$lang];
		$OUTPUT->reprompt=$answers[1][$lang]; //learnt that a reprompt is expected on launch. the skill works otherwise but the console throws an error.
	}
	elseif ($IntentName=="AMAZON.HelpIntent"){
		$OUTPUT->speak=$OUTPUT->reprompt=$answers[1][$lang];
	}
	else $OUTPUT->speak=$answers[rand(2,count($answers)-1)][$lang];


	$OUTPUT->answer();
} else $ALEXA->verificationfailed();
?>