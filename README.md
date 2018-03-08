## Video-Transcript Generator for Plenary Protocols of the German Bundestag

![Application Screenshot](_client/img/screenshot.png?raw=true)

Generates time-based transcripts from parliamentary protocols published via the Bundestag Open Data service ([https://www.bundestag.de/service/opendata](https://www.bundestag.de/service/opendata)).

Implemented for the **[abgeordnetenwatch.de](https://abgeordnetenwatch.de) goes Video** project as part of [demokratie.io](https://demokratie.io/gewinnerprojekte/).

-------------

### Demo

If you're just looking for a demo of already processed speeches, check out:  
**[https://openhypervideo.github.io/VideoTranscriptGenerator/](https://openhypervideo.github.io/VideoTranscriptGenerator/)**

-------------

### Data

The timings for already processed speeches are published at:  
**[_server/output/](_server/output/)**

-------------

### Usage

#### Prerequisites

* **PHP**
* [**Aeneas**](https://www.readbeyond.it/aeneas/) ("automagically synchronize audio and text")
    * Aeneas Dependencies: **Python** (2.7.x preferred), **FFmpeg**, and **eSpeak**

#### Step 1: Install Aeneas

See [https://github.com/readbeyond/aeneas/blob/master/wiki/INSTALL.md](https://github.com/readbeyond/aeneas/blob/master/wiki/INSTALL.md).

For Mac OS, there is an all-in-one installer, which takes care of the dependencies: [https://github.com/sillsdev/aeneas-installer/releases](https://github.com/sillsdev/aeneas-installer/releases).

#### Step 2: Input files (XML)

XML input files are located at [_server/input/xml/](_server/input/xml/). Some example files are already included. To add new files, download the file from  [https://www.bundestag.de/service/opendata](https://www.bundestag.de/service/opendata) and place it into the same directory.

#### Step 3: Scrape Media IDs (only if new XML files were added)

Use [_server/scrapeMediaIDs.php](_server/scrapeMediaIDs.php) to scrape Media IDs for all speeches in all XML files inside [_server/input/xml/](_server/input/xml/).

**Careful:** This potentially sends thousands of requests to Bundestag servers!

**Explaination:**
During the forced alignment process, we need access to a local copy of the respective audio file (depending on a single speech, agenda item or entire meeting). In order to show a preview upon completion, we also need to get the remote URL of the video file. Both file URLs can be retrieved when the Media ID is known. As Media IDs are not included in the original XML files from [https://www.bundestag.de/service/opendata](https://www.bundestag.de/service/opendata), we need to scrape them from the Bundestag Mediathek RSS Feed and write them to the respective XML nodes (eg. `<rede [...] media-id="1234567">`).

#### Step 4: Generate Video-Transcript (Force align XML & Audio)

Go to `http://localhost/VideoTranscriptGenerator/index.html` (or wherever you placed the scripts) -> Choose XML file -> Optionally choose agenda item or single speech -> Generate Video-Transcript -> DONE!

The generated JSON, XML and HTML files are saved inside `_server/output/`.

-----------------

### Known Issues

- Functionality is currently limited to the 19th electoral period (previous periods are published in a different format)
- Media IDs can only be scraped for single speeches. Agenda items (Tagesordnungspunkte) and entire meetings (Sitzungen) also exist as media files, but are currently not retrieved automatically. In order to get transcripts for single agenda items, look up the Media ID via the [Bundestag Mediathek](https://www.bundestag.de/mediathek) (-> Download -> MP3 -> first 7 digits of the filename) and manually add an attribute `media-id` to the XML node (example: `<tagesordnungspunkt top-id="Tagesordnungspunkt 1" media-id="1234567">`). We hope to automate this process in future developments.
- The forced alignment algorithm needs some configuration tweaks to better deal with the first few sentences and non-speech periods.
- In the XML format, there is currently no differentiation between actual speeches (for which a media file exists) and other speaker contributions, for example during electoral proceedings (eg. "Wahl des Bundestagspräsidenten") or discussion formats (eg. "Befragung der Bundesregierung", "Fragestunde"). Thus, there is sometimes no or a wrong Media ID assigned to the `<rede>` nodes. Right now they are thus ignored and not processed.
- Make sure the `_server/input/audio` and `_server/output` directories exist and are writable
