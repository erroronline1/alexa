# alexa
amazon alexa skills. more or less useful

i do them in php for the easiest implementation on my webspace.
in the beginning i had only one ssl certificate, therefore the unusual hostname in former versions and the media-file-workaround within the skills endpoints. i could resolve the latter, but it may as well serve as an example.
playing with alexa once in a while i built a small framework (that most possibly evolves with every new skill) to be able to concentrate on the specific tasks of the skill.
skills that respond or are distributed in german only still try to have english code that still might be comprehensible.

feel free to share your opinion and improvements.

## chefsekretaerin
<img src="assets/chefsekretaerin108.png" alt="chefsekretaerin skill logo" style="float:left; height:50px; padding-right:1em;" /> *Wer ist hier der Chef?* is my first skill where one can ask who makes the rules. the name can be set. it contains session-variables, storing and fetching database information on the endpoint. the use of the alexa-libray was implemented later, for some reason it was not fully possible, but it works now.
[live skill](https://www.amazon.de/dp/B07B6NVYQP/)

## annebackt
<img src="assets/annebackt108.png" alt="annebackt skill logo" style="float:left; height:50px; padding-right:1em;" /> *Anne backt* is capable of asking for recent entries of the [baking-blog of my wife](https://annebackt.de). one can display the receipts within the alexa-app and send a link via email. update 6/20: displays on echo show.
it contains session variables and email-permission handling.
[live skill](https://www.amazon.de/dp/B07LGDL4BV)

## experten
<img src="assets/experten108.png" alt="experten skill logo" style="float:left; height:50px; padding-right:1em;" /> *experts of the world / experten der welt* is inspired by [experten im internet](https://www.amazon.de/dp/B01N5PB05L) by marvin menzerath.
since i thought the answers could fit better to the invocation i did it myself. it is just a nonsense skill.
but i messed around with multi-language support for german and english.
[live skill](https://www.amazon.de/dp/B07Q1C8Z61)

## forgetmenot
<img src="assets/forgetmenot108.png" alt="remind me skill logo" style="float:left; height:50px; padding-right:1em;" /> *forget-me-not / vergissmeinnicht* might actually be useful since it can remind you regulary for custom things. it fights with some silly amazon restrictions though. just do not hold me liable if it does not remind you of your important medication. it has multi-language support and handles reminder permissions. i figured out that reminders will not be pushed belated if the device is temporarily not connected. also changing account in the companion app will result in stopping reminders even if they are still visible.
