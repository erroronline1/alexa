# alexa
<img src="https://m.media-amazon.com/images/G/01/AlexaDevPortal/alexa.CB473388935._CB1539290662_.png" alt="amzon alexa logo" style="float:left; height:64px; padding-right:1em;" />amazon alexa skills. more or less useful

i do them in php for the easiest implementation on my webspace.
in the beginning i had only one ssl certificate, therefore the unusual hostname in former versions and the media-file-workaround within the skills endpoints. i could easily resolve the latter now, but it may as well serve as an example.
playing with alexa once in a while i built a ~~small~~ framework (that most possibly evolves with every new skill) to be able to concentrate on the specific tasks of the skill.
skills that respond or are distributed in german only still try to have english code that still might be comprehensible.

feel free to share your opinion and improvements.

## chefsekretaerin
<img src="assets/chefsekretaerin108.png" alt="chefsekretaerin skill logo" style="float:left; height:32px; padding-right:1em;" /> *Wer ist hier der Chef?* is my first skill where one can ask who makes the rules. the name can be set.

notable techniques:
* session-variables
* storing and fetching database information on the endpoint

no special interface settings needed

[-> live skill](https://www.amazon.de/dp/B07B6NVYQP/)

## annebackt
<img src="assets/annebackt108.png" alt="annebackt skill logo" style="float:left; height:32px; padding-right:1em;" /> *Anne backt* is capable of asking for recent entries of the [baking-blog of my wife](https://annebackt.de). one can display the receipts within the alexa-app and send a link via email.

notable techniques:
* session variables
* email permission handling
* storing and fetching database information on the endpoint
* update 6/20: displays on echo show.

needed interface settings: display interface, apl for all types of hubs

[-> live skill](https://www.amazon.de/dp/B07LGDL4BV)

## experten
<img src="assets/experten108.png" alt="experten skill logo" style="float:left; height:32px; padding-right:1em;" /> *experts of the world / experten der welt* is inspired by [experten im internet](https://www.amazon.de/dp/B01N5PB05L) by marvin menzerath.
since i thought the answers could fit better to the invocation i did it myself. it is just a nonsense skill.

notable techniques:
* multi-language support for german and english.

no special interface settings needed

[-> live skill](https://www.amazon.de/dp/B07Q1C8Z61)

## forgetmenot
<img src="assets/forgetmenot108.png" alt="forget me not skill logo" style="float:left; height:32px; padding-right:1em;" /> *forget-me-not / vergissmeinnicht* might actually be useful since it can remind you regulary for custom things. it fights with some silly amazon restrictions though. just do not hold me liable if it does not remind you of your important medication. i figured out that reminders might not be pushed belated if the device is temporarily not connected - that is if you only use your phone. also changing accounts in the companion app might result in stopping reminders even if they are still visible.

notable techniques:
* possible dynamic multi language support (with oop)
* reminder and related permission handling

no special interface settings needed

certification pending. current issues involve self deleting reminders. not on my device though. still awaiting feedback from amazon.

# certification feedback
* sessions must be closed by default unless there is a action encouraging reprompt
* skills containing hardcoded words like *mom* or *dad* let certification conclude the skill might be directed to children below 13 years of age, that will exclusively published within the usa even if the publishing area is stated otherwise
* self hosted endpoints have to verify the request
* pronouns and conjunctions are not allowed for invocation
* invocation must be at least two words, german compound words do work - but can have weird recommendations from non german speaking certification staff that knew way better than me 
* recurring reminders have to summarize the setup before storing and must provide the next occurence
* reminders must state the skills name, but on audio output only

# other findings
* published skills can not be deleted easily and will show up in your developer console for eternity
* alexa has issues processing names in general
* a self hosted ssl-endpoint at strato must have server-side spam protection deactivated, resulting in status 403 otherwise - took me some days
* the developer board is not exactly helpful
* it's not easy to find an invocation name that fits in natural language patterns (especially german)
* the documentation is ~~shit~~ quite incomplete, e.g. server error responses due to faulty requests, apl structure (where to the freaking payloads go?) are not well documentated