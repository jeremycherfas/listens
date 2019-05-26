# Listens

I wrote this script based on https://github.com/cleverdevil/Known-Listen to get the artwork and summary of recently listened podcasts from Overcast.

For each podcast, it creates a new entry in Grav, using my particular installation details. These are easily modified. You will need to create your own template.

I currently run it by hand every couple of days, taking care to delete previously finished podcasts from my Overcast queue. The very first time it runs you will get a warning because some of the housekeeping files do not exist.

Currently it does not create entries using micropub, but I believe that it would not be difficult to modify the post creation process to do that.

Be careful not to call the OPML file too frequently when setting up and testing or you will run into problems with rate limiting. I save the `opml.json` file specifically to use that with other testing scripts.

Likwewise, I save `shiny.json` as a fallback that I can use if I notice anything has gone wrong.
