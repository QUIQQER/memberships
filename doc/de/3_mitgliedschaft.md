Mitgliedschaft
===

Über die Mitgliedschafts-Verwaltung lässt sich für jede Mitgliedschaft ein Verwaltungs-Panel öffnen.
Dies ist in folgende Bereiche aufgeteilt:

## Grund-Einstellungen
### Mitgliedschafts-Informationen
Hier lässt sich die Grundkonfiguration für die Mitgliedschaft festlegen. **Titel**, **Kurzbeschreibung** und
**Ausführliche Beschreibung** lassen sich hier multilingual eintragen. Diese Texte dienen der Identifikation von Mitgliedschaften
für Benutzer. Sie können im Frontend-Bereich (sofern ein Profil-Seite eingerichtet ist) zur jeweiligen Mitgliedschaft
eingesehen werden.

### Gruppen
Unter `Gruppen` lassen sich die QUIQQER Benutzergruppen festlegen, denen Benutzer zugeordnet werden, sobald sie
in die Mitgliedschaft kommen. Wird ein Benutzer aus der Mitgliedschaft entfernt, wird er auch aus diesen Gruppen entfernt.

**Ausnahme:** Benutzer können weiterhin über die normale QUIQQER Benutzer-/Gruppenverwaltung Gruppen hinzugefügt oder
aus Gruppen entfernt werden.

### Laufzeit-Einstellungen
Bestimmt, wie lange Benutzer in der Mitgliedschaft bleiben, nachdem sie hinzugefügt wurden.

Die Laufzeit gilt für jeden Benutzer **individuell** und bestimmt sich nach dem Zeitpunkt, an dem ein Benutzer der Mitgliedschaft
zugeordnet wird.

Am Ende der Laufzeit wird ein Benutzer automatisch aus der Mitgliedschaft entfernt. Ist die **automatische Verlängerung**
aktiviert, wird der Benutzer nur entfernt, wenn er **gekündigt** hat. Dann wird er zum Ende des aktuellen Turnus entfernt.

**WICHTIG:** Damit regelmäßig geprüft wird, welche Benutzer in welchen Mitgliedschaften sein dürfen und Benutzer ggf.
entfernt werden, muss der Cron **Mitgliedschaften prüfen/verlängern** eingerichtet sein
(s. [Konfiguration & Einstellungen](4_konfiguration_und_einstellungen.md)).

## Benutzer
In der "Benutzer"-Kategorie werden alle Benutzer gelistet, die **aktuell in der Mitgliedschaft sind**. Im Mitgliedschafts-Panel
erscheint bei Auswahl der Kategorie oben eine "Mitglieder-Suche", in der nach **Benutzernamen**, **Vor-** oder **Nachnamen**
eines Benutzers gesucht werden kann.

### History
Ist eine Zeile markiert, kann über den Button **History** die Verlaufsgeschichte der aktuellen Mitgliedschaftszugehörigkeit
eines Benutzers eingesehen werden. Hier ist jede Verlängerung, Änderung und ggf. weitere Informationen
 (z.B. Zuordnung zu einer Bestellung) einsehbar. 
 
### Aktionen
Über den **Aktionen**-Button können Benutzer manuell zu einer Mitgliedschaft hinzugefügt oder aus ihr entfernt werden.

Zusätzlich kann die Mitgliedschaftszugehörigkeit eines Benutzers **editiert** werden. Beginn und Ende des aktuellen Turnus
können angepasst werden und der "Gekündigt"-Status kann an- oder ausgeschaltet werden. Wird der "Gekündigt"-Status
durch einen Administrator gesetzt, wird der Benutzer so behandelt, als hätte er eigenständig die Mitgliedschaft gekündigt
und bestätigt. Er wird dann zum Ende des Turnus aus der Mitgliedschaft entfernt.

## Benutzer-Archiv
Das "Benutzer-Archiv" ist ähnlich der "Benutzer"-Kategorie, listet aber alle Benutzer, die aktuell **nicht** mehr in der
Mitgliedschaft sind.

Benutzer können hier mehrfach gelistet werden. Jeder Eintrag bezieht sich auf **einen** Zeitraum, indem ein Benutzer in
der Mitgliedschaft war.