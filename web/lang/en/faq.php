<?php

return [

    'heading' => 'Frequently asked questions',

    'intro' => 'WhereIveBeen is a route tracker for World of Warcraft. An addon records where your character goes and what happens along the way — mounts, flight paths, deaths, levels, loot, quests. You export the session as one line, paste it here, and the route is drawn on the zone map with every event marked on it. The project is free and has no paid features.',

    'items' => [
        [
            'question' => 'What does WhereIveBeen do?',
            'answer' => 'It keeps a map of where you have actually been. The addon writes down your position while you play, together with the events that happen on the way. After the import the whole session becomes a route on the zone map: where you walked, where you died, where you took a flight path, where you picked something up.',
        ],
        [
            'question' => 'Which World of Warcraft clients are supported?',
            'answer' => 'Vanilla, The Burning Crusade, Wrath of the Lich King, Cataclysm, Mists of Pandaria and Retail. One archive covers all of them — the client picks the right file itself.',
        ],
        [
            'question' => 'How do I install the addon?',
            'answer' => 'Download the archive, unpack it and move the folder into World of Warcraft/<client>/Interface/AddOns/. The folder must be named exactly WhereIveBeen — under any other name the addon loads but records nothing. Then restart the client or type /reload.',
        ],
        [
            'question' => 'Do I have to start the recording myself?',
            'answer' => 'No. Recording starts on its own when you log in and survives /reload and zone changes. The commands /wivbn start, /wivbn end and /wivbn status are there if you want to control a session by hand.',
        ],
        [
            'question' => 'How do I import a session?',
            'answer' => 'Type /wivbn get_sessions in game, pick a session and copy the line from the window that opens. On the site, sign in and paste that line on the Imports page. The route appears once the import finishes.',
        ],
        [
            'question' => 'How do I import every session at once?',
            'answer' => 'Instead of copying sessions one by one, upload the addon save file. Log out of the game first, then open the Imports page and drop WhereIveBeen.lua onto the upload area. The file lives in World of Warcraft/_classic_era_/WTF/Account/<ACCOUNT>/SavedVariables/WhereIveBeen.lua — use _retail_ or _classic_ for other clients. Every session in the file is imported at once, and sessions you already imported are refreshed rather than duplicated.',
        ],
        [
            'question' => 'What exactly is recorded?',
            'answer' => 'Map coordinates with a timestamp, plus in-game events: level ups, deaths, loot, quests taken and handed in, mounts, flight paths, vendors, trainers and similar interactions. Character name, realm, faction, class and level are stored with the session so routes can be told apart.',
        ],
        [
            'question' => 'Does the addon send anything to the internet?',
            'answer' => 'Never. The addon has no network access at all — it only writes to its own SavedVariables file inside your game folder. Nothing leaves your computer until you copy a session out yourself and paste it here.',
        ],
        [
            'question' => 'Is it against the rules to use it?',
            'answer' => 'The addon uses the regular API the game exposes to any Lua addon and only reads information your client already has. It does not automate play in any way.',
        ],
        [
            'question' => 'Does the site use cookies?',
            'answer' => 'Only the ones it needs to work: a session cookie that keeps you signed in, a CSRF token, and small cookies that remember your language and interface settings. There is no analytics, no advertising and no third-party tracking, so there is nothing to opt out of.',
        ],
        [
            'question' => 'Is WhereIveBeen free?',
            'answer' => 'Yes. There are no paid features and no ads. Donations on the support page are voluntary and unlock nothing.',
        ],
        [
            'question' => 'The addon is installed but records nothing. What is wrong?',
            'answer' => 'In almost every case the folder has the wrong name — it must be WhereIveBeen, without the version suffix left over from unpacking. Check /wivbn status in game: it tells you whether a session is currently active.',
        ],
    ],

];
