<?php  include ('APL.php'); // basic functions library

include ('nonpublic.php'); // application ids, database connections, etc.

if ($ALEXA->verified($post, $rawpost, $ChefsekretaerinAppId)){

$boss=["der boss","der könig","die königin","der chef","die chefin","der bestimmer","die bestimmerin"];
$wer=$post->request->intent->slots->NAME->value;
$wo=$post->request->intent->slots->WHERE->value;
$was=$post->request->intent->slots->BOSS->value;
$shoudEndSession=true;
$output="hallo";

$tchef=$ChefsekretaerinMysqli->query("SELECT name FROM wer_ist_der_chef WHERE amazon_id='".$post->session->user->userId."' LIMIT 1")->fetch_assoc();

$chef=$tchef['name']?$tchef['name']:'noch niemand';

if ($post->request->type=="IntentRequest"){
    if ($post->request->intent->name=="DEFAULT"){
        if (in_array($was,$boss)) $output=$chef.' ist '.$wo.' '.$was;
    else $output='du bist vielleicht '.$wo.' '.$was.', aber '.$chef.' ist hier der chef';
    }
    elseif ($post->request->intent->name=="NAME_REQUEST"){
        if ($wer==$chef && in_array($was,$boss)) $output='ja! ja, '.$wer.' ist '.$wo.' '.$was;
        elseif ($wer!=$chef && in_array($was,$boss)) $output='nein, '.$wer.' ist '.$wo.' nicht '.$was.', das ist '.$chef;
        elseif ($wer==$chef && !in_array($was,$boss)) $output='nein, nein! '.$chef.' ist '.$wo.' der chef';
        else $output='kann sein, dass '.$wer.' '.$wo.' '.$was.' ist, aber '.$chef.' ist hier der chef';
    }
    elseif ($post->request->intent->name=="SELF_REQUEST"){
        if ($post->session->attributes->sessionWer) {
            if ($post->session->attributes->sessionWer==$chef && in_array($was,$boss)) $output='ja '.$chef.', du bist '.$wo.' '.$was;
            elseif ($post->session->attributes->sessionWer==$chef && !in_array($was,$boss)) $output='nein '.$chef.', du bist hier der chef';
            elseif ($post->session->attributes->sessionWer!=$chef && in_array($was,$boss)) $output='nein, das bist du nicht, das ist '.$chef;
            else $output='das kann schon sein, aber so gut kennen wir uns ja auch nicht';
        }
        else { $output='wie heißt du denn?';
            $sessionAttributes=['sessionWas'=>$was,'sessionWo'=>$wo];
            $shoudEndSession=false;
        }
    }
    elseif ($post->request->intent->name=="SELF_REQUEST_SET_NAME"){
        if ($post->session->attributes->sessionWas) {
            $was=$post->session->attributes->sessionWas;
            if ($wer==$chef && in_array($was,$boss)) $output='ja '.$chef.', du bist '.$wo.' '.$was;
            elseif ($wer==$chef && !in_array($was,$boss)) $output='nein '.$chef.', du bist hier der chef';
            elseif ($wer!=$chef && in_array($was,$boss)) $output='nein, du bist nicht '.$was.', das ist '.$chef;
            else $output='das kann schon sein, aber so gut kennen wir uns ja auch nicht';
        }
        else {
            $output='hallo '.$wer.', frag mich ob du der chef bist';
            $shoudEndSession=false;
            $reprompt=['outputSpeech'=>['type'=>'PlainText', 'text'=>'frag mich wer der chef ist']];
        }
        $sessionAttributes=['sessionWer'=>$wer];
    }
    elseif ($post->request->intent->name=="SELF_REQUEST_STORE_NAME"){
        if ($wer && in_array($was,$boss)) {
        $ChefsekretaerinMysqli->query("INSERT INTO wer_ist_der_chef VALUES ('".$post->session->user->userId."','".$wer."') ON DUPLICATE KEY UPDATE name='".$wer."'");
        $output='hallo '.$wer.', es freut mich dich kennen zu lernen';
        $card=['type'=>'Simple',
            'title'=>'Wer ist hier der Chef?',
            'content'=>$wer. ' ist hier jetzt offiziell '.$was.' '.$ChefsekretaerinMysqli->error
        ];
    }
        else { $output='ich habe dich nicht verstanden. versuchs nochmal'; $shoudEndSession=false; $reprompt=['outputSpeech'=>['type'=>'PlainText', 'text'=>'ich habe dich nicht verstanden. sage mir wer der chef ist oder frage nach hilfe']];
        }
    }
    elseif ($post->request->intent->name=="AMAZON.StopIntent"){
        $output='dann bis bald. ich hoffe ich konnte helfen';
    }
    elseif ($post->request->intent->name=="AMAZON.HelpIntent"){
        $output='ich kann dir sagen, wer hier der chef ist. oder die königin. oder der bestimmer. frag einfach. damit ich mir das merke, sag etwas wie: ich bin alexa und ich bin der chef.';
        $card=['type'=>'Simple',
            'title'=>'Bestimme wer der Chef ist.',
            'content'=>($chef?'Zur Zeit ist '.ucfirst($chef).' hier der Chef. Wenn Du das ändern möchtest s':'S').'ag "Ich bin {Name} und ich bin der Chef".'
        ];
        $shoudEndSession=false;
    }
    elseif ($post->request->intent->name=="AMAZON.CancelIntent"){
        $output='dann bis bald. ich hoffe ich konnte helfen';
    }

}
elseif ($post->request->type=="LaunchRequest"){
    $output='frag mich wer hier der chef ist. oder nach hilfe.';
    if (!$tchef['name']) $output.=' ich weiß aber noch nicht wer hier der chef ist. damit ich mir das merke, sag etwas wie: ich bin alexa und ich bin der chef.';
    $shoudEndSession=false;
    $reprompt=['outputSpeech'=>['type'=>'PlainText', 'text'=>'frag mich wer der chef ist']];
}
elseif ($post->request->type== 'SessionEndedRequest') {
    $output='dann bis bald. ich hoffe ich konnte helfen';
}

    $responseArray = [
        'version' => '1.0',
        'response' => [
              'outputSpeech' => [
                    'type' => 'PlainText',
                    'text' => $output
              ],
             'card'=>$card,
             'reprompt'=>$reprompt,
             'shouldEndSession' => $shoudEndSession
            ],
        'sessionAttributes'=>$sessionAttributes
    ];
    header('Content-Type: application/json;Content-Length:'.strlen(json_encode($responseArray)));
    echo json_encode($responseArray);



} else $ALEXA->verificationfailed();
?>