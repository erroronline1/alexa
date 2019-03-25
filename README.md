# alexa
amazon alexa skills. more or less useful

i do them in php for the easiest implementation on my webspace.
i have only one ssl certificate, therefore the unusual hostname and media-file-workarounds within the skills endpoints.
playing with alexa once in a while i built a small libray (that might evolve in the future) to be able to concentrate on the specific tasks of the skill
feel free to share your opinion and improvements.
the skills are mostly in german language. not the sourcecode and variable names so i hope it to be still understandable.

## chefsekretaerin
this is my first skill where one can ask who makes the rules. the name can be set.
it contains session-variables, storing and fetching database information on the endpoint. the use of the alexa-libray was implemented later, for some reason it was not fully possible, but it works now.
[live skill](https://www.amazon.de/dp/B07B6NVYQP/)

## annebackt
my second skill is capable of asking for recent entries of the baking-blog of my wife. one can display the receipts within the alexa-app and send a link via email.
it contains session variables and email-permission handling.
[live skill](https://www.amazon.de/dp/B07LGDL4BV)

## experten
this skill is inspired by [experten im internet](https://www.amazon.de/dp/B01N5PB05L) by marvin menzerath.
since i thought the answers could fit better to the invocation i did it myself. it is just a nonsense skill.
but i messed around with multi-language support for german and english.
[live skill](https://www.amazon.de/dp/B07Q1C8Z61)