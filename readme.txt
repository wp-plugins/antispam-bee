=== Antispam Bee ===
Contributors: sergej.mueller
Tags: antispam, spam, comments, trackback
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=5RDDW9FEHGLG6
Requires at least: 3.4
Tested up to: 3.5
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html



Say Goodbye zu Spam in deinem WordPress-Blog. Kostenlos, werbefrei und datenschutzkonform. Für Kommentare und Trackbacks.



== Description ==

Blog-Spam bekämpfen ist die Stärke von *Antispam Bee*. Seit Jahren wird das Plugin darauf trainiert, Spam-Kommentare zuverlässig zu erkennen (auf Wunsch auch sofort zu beseitigen). Dabei greift *Antispam Bee* auf unterschiedliche Techniken zu, die sich zur Identifizierung von Spam-Nachrichten bewährt haben.

Im Vergleich zum - für die meisten Blogger kostenpflichtigen - Plugin Akismet, überträgt *Antispam Bee* keine Blog- und Kommentardaten an einen entfernten Server. Ob ein Kommentar böswillig ist, entscheidet das kostenlose Plugin vor Ort im heimischen Blog. Hierfür stehen dem Nutzer zahlreiche Funktionen zur Verfügung, die per Mausklick ein- und abgeschaltet werden können.

Als Unterstützung der Erkennung greift *Antispam Bee* auf eine öffentlich zugängliche und seit mehreren Jahren gepflegte [Datenbank](http://opm.tornevall.org) mit aktuellen Spammer-Referenzen zu. Anhand der IP-Adresse des Kommentators kann schnell und unbürokratisch entschieden werden, ob der Kommentar-Absender ein in der Welt bekannter Spam-Vertreiber ist. Aber auch dieser Filter kann im Antispam-Plugin jedezeit deaktiviert werden.

= Pluspunkte =
* Aktive Weiterentwicklung seit 2009
* Über 20 untereinander kombinierbare Funktionen
* Keine Speicherung von personenbezogenen Daten
* Volle Transparenz bei der Prüfung der Kommentare
* Keine Registrierung notwendig
* Kostenlos auch für kommerzielle Projekte
* Keine Anpassung von Theme-Templates vonnöten
* Alle Funktionen vom Nutzer anpassbar
* Statistik der letzten 30 Tage als Dashboard-Widget

= Einstellungen =
Nach der Aktivierung nimmt *Antispam Bee* den regulären Betrieb auf, indem vordefinierte Schutzmechanismen scharf geschaltet werden. Es empfiehlt sich jedoch, die Seite mit Plugin-Einstellungen aufzurufen und sich mit wirkungsvollen Optionen auseinander zu setzen. Alle Optionsschalter sind in der [Online-Dokumentation](http://playground.ebiene.de/antispam-bee-wordpress-plugin/) detailliert vorgestellt.

Die meisten Auswahlmöglichkeiten innerhalb der Optionsseite sind konfigurierbare Antispam-Filter, die der Blog-Administrator nach Bedarf aktiviert. Zahlreiche Wahlmöglichkeiten steuern hingegen die Benachrichtigungs- und die automatische Löschfunktion des Plugins. Die *Antispam Bee* Optionen in der Kurzfassung:

* Genehmigten Kommentatoren vertrauen
* Öffentliche Spamdatenbank berücksichtigen
* IP-Adresse des Kommentators validieren
* Lokale Spamdatenbank einbeziehen
* Bestimmte Länder blockieren bzw. erlauben
* Kommentare nur in einer Sprache zulassen
* Erkannten Spam kennzeichnen, nicht löschen
* Bei Spam via E-Mail informieren
* Spamgrund im Kommentar nicht speichern
* Vorhandenen Spam nach X Tagen löschen
* Aufbewahrung der Spam-Kommentare für einen Typ
* Bei definierten Spamgründen sofort löschen
* Statistiken als Dashboard-Widget generieren
* Spam-Anzahl auf dem Dashboard anzeigen
* Eingehende Ping- und Trackbacks ignorieren
* Kommentarformular befindet sich auf Archivseiten

Installiert, probiert die Antispam-Lösung für WordPress aus. Anmeldefrei und ohne lästige Captchas.

= Unterstützung =
* Per [Flattr](https://flattr.com/donation/give/to/sergej.mueller)
* Per [PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=5RDDW9FEHGLG6)

= Systemanforderungen =
* PHP 5.1.2
* WordPress 3.4

= Dokumentation =
* [Antispam Bee: Antispam für WordPress](http://playground.ebiene.de/antispam-bee-wordpress-plugin/)

= Autor =
* [Google+](https://plus.google.com/110569673423509816572 "Google+")
* [Plugins](http://wpcoder.de "Plugins")

= Inkompatibilität =
* Disqus
* Jetpack Comments


== Changelog ==

= 2.4.5 =
* Überarbeitetes Layout der Einstellungen
* Streichung von Project Honey Pot
* TornevallNET als neuer DNSBL-Dienst
* WordPress 3.4 als Mindestvoraussetzung
* WordPress 3.5 Unterstützung

= 2.4.4 =
* Technical and visual support for WordPress 3.5
* Modification of the file structure: from `xyz.dev.css` to `xyz.min.css`
* Retina screenshot

= 2.4.3 =
* Check for basic requirements
* Remove the sidebar plugin icon
* Set the Google API calls to SSL
* Compatibility with WordPress 3.4
* Add retina plugin icon on options
* Depending on WordPress settings: anonymous comments allowed

= 2.4.2 =
* New geo ip location service (without the api key)
* Code cleanup: Replacement of `@` characters by a function
* JS-Fallback for missing jQuery UI

= 2.4.1 =
* Add russian translation
* Fix for the textarea replace
* Detect and hide admin notices

= 2.4 =
* Support for IPv6
* Source code revision
* Delete spam by reason
* Changing the user interface
* Requirements: PHP 5.1.2 and WordPress 3.3

= 2.3 =
* Xmas Edition

= 2.2 =
* Interactive Dashboard Stats

= 2.1 =
* Remove Google Translate API support

= 2.0 =
* Allow comments only in certain language (English/German)
* Consider comments which are already marked as spam
* Dashboard Stats: Change from canvas to image format
* System requirements: WordPress 2.8
* Removal of the migration script
* Increase plugin security

= 1.9 =
* Dashboard History Stats (HTML5 Canvas)

= 1.8 =
* Support for the new IPInfoDB API (including API Key)

= 1.7 =
* Black and whitelisting for specific countries
* "Project Honey Pot" as a optional spammer source
* Spam reason in the notification email
* Visual refresh of the notification email
* Advanced GUI changes + Fold-out options

= 1.6 =
* Support for WordPress 3.0
* System requirements: WordPress 2.7
* Code optimization

= 1.5 =
* Compatibility with WPtouch
* Add support for do_action
* Translation to Portuguese of Brazil

= 1.4 =
* Enable stricter inspection for incomming comments
* Do not check if the author has already commented and approved

= 1.3 =
* New code structure
* Email notifications about new spam comments
* Novel Algorithm: Advanced spam checking

= 1.2 =
* Antispam Bee spam counter on dashboard

= 1.1 =
* Adds support for WordPress new changelog readme.txt standard
* Various changes for more speed, usability and security

= 1.0 =
* Adds WordPress 2.8 support

= 0.9 =
* Mark as spam only comments or only pings

= 0.8 =
* Optical adjustments of the settings page
* Translation for Simplified Chinese, Spanish and Catalan

= 0.7 =
* Spam folder cleanup after X days
* Optional hide the &quot;MARKED AS SPAM&quot; note
* Language support for Italian and Turkish

= 0.6 =
* Language support for English, German, Russian

= 0.5 =
* Workaround for empty comments

= 0.4 =
* Option for trackback and pingback protection

= 0.3 =
* Trackback and Pingback spam protection



== Screenshots ==

1. Antispam Bee Optionen (Antispam-Filter)
2. Antispam Bee Optionen (Erweitert)
3. Antispam Bee Optionen (Sonstiges)